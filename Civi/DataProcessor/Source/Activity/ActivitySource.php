<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

namespace Civi\DataProcessor\Source\Activity;

use Civi\DataProcessor\DataFlow\CombinedDataFlow\CombinedSqlDataFlow;
use Civi\DataProcessor\DataFlow\MultipleDataFlows\DataFlowDescription;
use Civi\DataProcessor\DataFlow\MultipleDataFlows\JoinInterface;
use Civi\DataProcessor\DataFlow\MultipleDataFlows\SimpleJoin;
use Civi\DataProcessor\DataFlow\CombinedDataFlow\SubqueryDataFlow;
use Civi\DataProcessor\DataFlow\SqlDataFlow\SimpleWhereClause;
use Civi\DataProcessor\DataFlow\SqlTableDataFlow;
use Civi\DataProcessor\DataSpecification\CustomFieldSpecification;
use Civi\DataProcessor\DataSpecification\DataSpecification;
use Civi\DataProcessor\DataSpecification\FieldSpecification;
use Civi\DataProcessor\Source\AbstractCivicrmEntitySource;
use Civi\DataProcessor\DataSpecification\Utils as DataSpecificationUtils;

use CRM_Dataprocessor_ExtensionUtil as E;

class ActivitySource extends AbstractCivicrmEntitySource {

  /**
   * @var SqlTableDataFlow
   */
  protected $activityDataFlow;

  /**
   * @var SqlTableDataFlow
   */
  protected $activityContactDataFlow;

  /**
   * @var SqlTableDataFlow
   */
  protected $activityCaseDataFlow;

  public function __construct() {
    parent::__construct();

    // Create the activity data flow and data flow description
    $this->activityDataFlow = new SqlTableDataFlow($this->getTable(), $this->getSourceName().'_activity', $this->getSourceTitle());
    DataSpecificationUtils::addDAOFieldsToDataSpecification('CRM_Activity_DAO_Activity', $this->activityDataFlow->getDataSpecification());

    // Create the activity contact data flow and data flow description
    $this->activityContactDataFlow = new SqlTableDataFlow('civicrm_activity_contact', $this->getSourceName().'_activity_contact');
    DataSpecificationUtils::addDAOFieldsToDataSpecification('CRM_Activity_DAO_ActivityContact', $this->activityContactDataFlow->getDataSpecification(), array('id'), '', 'activity_contact_', E::ts('Activity Contact :: '));

    // Create the activity contact data flow and data flow description
    $this->activityCaseDataFlow = new SqlTableDataFlow('civicrm_case_activity', $this->getSourceName().'_activity_case');
    DataSpecificationUtils::addDAOFieldsToDataSpecification('CRM_Case_DAO_CaseActivity', $this->activityCaseDataFlow->getDataSpecification(), array('id', 'activity_id'), '', 'activity_case_', E::ts('Case :: '));
  }

  /**
   * Returns the entity name
   *
   * @return String
   */
  protected function getEntity() {
    return 'Activity';
  }

  /**
   * Returns the table name of this entity
   *
   * @return String
   */
  protected function getTable() {
    return 'civicrm_activity';
  }

  /**
   * Returns the default configuration for this data source
   *
   * @return array
   */
  public function getDefaultConfiguration() {
    return array(
      'filter' => array(
        'is_current_revision' => array (
          'op' => '=',
          'value' => '1',
        ),
        'is_deleted' => array (
          'op' => '=',
          'value' => '0',
        ),
        'is_test' => array (
          'op' => '=',
          'value' => '0',
        ),
        'activity_contact_record_type_id' => array (
          'op' => 'IN',
          'value' => array(3), // Activity Targets
        )
      )
    );
  }

  /**
   * Returns an array with possible aggregate functions.
   * Return false when aggregation is not possible.
   *
   * @return array|false
   */
  protected function getPossibleAggregateFunctions() {
    return [
      'max_activity_date_time' => E::ts('Last one by activity date'),
      'min_activity_date_time' => E::ts('First one by activity date'),
      'max_created_date' => E::ts('Last one by created date'),
      'min_created_date' => E::ts('First one by created date'),
      'max_modified_date' => E::ts('Last one by modified date'),
      'min_modified_date' => E::ts('First one by modified date'),
      'max_id' => E::ts('Last one by activity id'),
      'min_id' => E::ts('Fist one by activity id'),
    ];
  }

  /**
   * @return \Civi\DataProcessor\DataFlow\SqlDataFlow
   * @throws \Exception
   */
  protected function getEntityDataFlow() {
    // Create the subquery data flow
    if (!$this->entityDataFlow) {
      $activityDataDescription = new DataFlowDescription($this->activityDataFlow);

      $contactJoin = new SimpleJoin($this->activityDataFlow->getTableAlias(), 'id', $this->activityContactDataFlow->getTableAlias(), 'activity_id');
      $contactJoin->setDataProcessor($this->dataProcessor);
      $activityContactDataDescription = new DataFlowDescription($this->activityContactDataFlow, $contactJoin);

      $caseJoin = new SimpleJoin($this->activityDataFlow->getTableAlias(), 'id', $this->activityCaseDataFlow->getTableAlias(), 'activity_id', 'LEFT');
      $caseJoin->setDataProcessor($this->dataProcessor);
      $activityCaseDataDescription = new DataFlowDescription($this->activityCaseDataFlow, $caseJoin);

      $this->entityDataFlow = new SubqueryDataFlow($this->getSourceName(), $this->getTable(), $this->getSourceName());
      $this->entityDataFlow->addSourceDataFlow($activityDataDescription);
      $this->entityDataFlow->addSourceDataFlow($activityContactDataDescription);
      $this->entityDataFlow->addSourceDataFlow($activityCaseDataDescription);
    }

    if (empty($this->configuration['aggregate_function'])) {
      return $this->entityDataFlow;
    } else {
      $this->getAggregationDataFlow();

      $aggregationDataFlow = new CombinedSqlDataFlow('', $this->aggregationDateFlow->getPrimaryTable(), $this->aggregationDateFlow->getPrimaryTableAlias());
      $aggregationDataFlow->addSourceDataFlow(new DataFlowDescription($this->aggregationDateFlow));
      $join = $this->getAggregationJoin($this->getSourceName());
      $dataFlowDescription = new DataFlowDescription($this->entityDataFlow, $join);
      $aggregationDataFlow->addSourceDataFlow($dataFlowDescription);
      return $aggregationDataFlow;
    }
  }

  /**
   * Sets the join specification to connect this source to other data sources.
   *
   * @param \Civi\DataProcessor\DataFlow\MultipleDataFlows\JoinInterface $join
   *
   * @return \Civi\DataProcessor\Source\SourceInterface
   */
  public function setJoin(JoinInterface $join) {
    parent::setJoin($join);

    if (!empty($this->configuration['aggregate_function'])) {
      if ($join instanceof SimpleJoin && $join->getLeftTable() == $this->getEntityTableAlias()) {
        $join->setLeftTable($this->aggregationDateFlow->getPrimaryTableAlias());
        $join->setLeftPrefix($this->aggregationDateFlow->getPrimaryTableAlias());
      }
      elseif ($join instanceof SimpleJoin && $join->getRightTable() == $this->getEntityTableAlias()) {
        $join->setRightTable($this->aggregationDateFlow->getPrimaryTableAlias());
        $join->setRightPrefix($this->aggregationDateFlow->getPrimaryTableAlias());
      }
    }
    return $this;
  }

  protected function getAggregationDataFlow() {
    $groupByFields = array();
    foreach($this->configuration['aggregate_by'] as $aggregate_by) {
      $field = clone $this->getAvailableFields()->getFieldSpecificationByName($aggregate_by);
      $field->alias = $aggregate_by;
      if (stripos($field->name, 'activity_contact_') === 0) {
        $field->name = substr($field->name, 17);
      }
      $groupByFields[] = $field;
    }

    if (!$this->aggregationDateFlow) {
      $activityDataFlow = new SqlTableDataFlow($this->getTable(), $this->getSourceName().'_activity_aggregate_');
      $activityContactDataFlow = new SqlTableDataFlow('civicrm_activity_contact', $this->getSourceName().'_activity_contact_aggregate_');

      $activityDataDescription = new DataFlowDescription($activityDataFlow);
      $contactJoin = new SimpleJoin($activityDataFlow->getTableAlias(), 'id', $activityContactDataFlow->getTableAlias(), 'activity_id');
      $contactJoin->setDataProcessor($this->dataProcessor);
      $activityContactDataDescription = new DataFlowDescription($activityContactDataFlow, $contactJoin);

      $aggrgeate_field_spec = clone $this->getAvailableFields()->getFieldSpecificationByName($this->getAggregateField());
      $aggrgeate_field_spec->setMySqlFunction($this->getAggregateFunction());
      $aggrgeate_field_spec->alias = $this->getAggregateField();

      $aggretated_table_dataflow = new CombinedSqlDataFlow('', $this->getTable(), '_aggregated_'.$this->getSourceName());
      $aggretated_table_dataflow->addSourceDataFlow($activityDataDescription);
      $aggretated_table_dataflow->addSourceDataFlow($activityContactDataDescription);
      $activityDataFlow->getDataSpecification()->addFieldSpecification($aggrgeate_field_spec->name, $aggrgeate_field_spec);
      foreach ($groupByFields as $groupByField) {
        if (stripos($groupByField->alias, 'activity_contact_') === 0) {
          $activityContactDataFlow->getDataSpecification()
            ->addFieldSpecification($groupByField->name, $groupByField);
          $activityContactDataFlow->getGroupByDataSpecification()
            ->addFieldSpecification($groupByField->name, $groupByField);
        } else {
          $activityDataFlow->getDataSpecification()
            ->addFieldSpecification($groupByField->name, $groupByField);
          $activityDataFlow->getGroupByDataSpecification()
            ->addFieldSpecification($groupByField->name, $groupByField);
        }
      }
      $this->aggregationDateFlow = new SubqueryDataFlow('', $activityDataFlow->getTable(), $activityDataFlow->getTableAlias());
      $this->aggregationDateFlow->addSourceDataFlow(new DataFlowDescription($aggretated_table_dataflow));
    }

    return $this->aggregationDateFlow;
  }

  /**
   * Add a filter to the aggregation data flow.
   *
   * @param \Civi\DataProcessor\DataSpecification\FieldSpecification $filter
   * @param $op
   * @param $values
   */
  protected function addFilterToAggregationDataFlow(FieldSpecification $filter, $op, $values) {
    if ($this->aggregationDateFlow) {
      if (stripos($filter->name, 'activity_contact_') === 0) {
        $name = str_replace('activity_contact_', '', $filter->name);
        $this->aggregationDateFlow->addWhereClause(new SimpleWhereClause($this->getSourceName().'_activity_contact_aggregate_', $name, $op, $values, $filter->type, FALSE));
      } else {
        $this->aggregationDateFlow->addWhereClause(new SimpleWhereClause($this->aggregationDateFlow->getPrimaryTableAlias(), $filter->name, $op, $values, $filter->type, FALSE));
      }
    }
  }

  /**
   * Returns true when aggregation is enabled for this data source.
   *
   * @return bool
   */
  protected function isAggregationEnabled() {
    return false;
  }

  /**
   * Load the fields from this entity.
   *
   * @param DataSpecification $dataSpecification
   * @throws \Civi\DataProcessor\DataSpecification\FieldExistsException
   */
  protected function loadFields(DataSpecification $dataSpecification, $fieldsToSkip=array()) {
    $daoClass = \CRM_Core_DAO_AllCoreTables::getFullName($this->getEntity());
    $aliasPrefix = $this->getSourceName().'_';

    DataSpecificationUtils::addDAOFieldsToDataSpecification($daoClass, $dataSpecification, $fieldsToSkip, '', $aliasPrefix);
    DataSpecificationUtils::addDAOFieldsToDataSpecification('CRM_Activity_DAO_ActivityContact', $dataSpecification, array('id', 'activity_id'), 'activity_contact_', $aliasPrefix, E::ts('Activity contact :: '));
    DataSpecificationUtils::addDAOFieldsToDataSpecification('CRM_Case_DAO_CaseActivity', $dataSpecification, array('id', 'activity_id'), 'activity_case_', $aliasPrefix);
  }

}
