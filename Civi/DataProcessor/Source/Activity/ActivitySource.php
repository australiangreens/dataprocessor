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
use Civi\DataProcessor\DataFlow\MultipleDataFlows\SimpleNonRequiredJoin;
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

  /**
   * @var SqlTableDataFlow
   */
  protected $activityAggregationDataFlow;

  /**
   * @var SqlTableDataFlow
   */
  protected $activityAggregationContactDataFlow;

  /**
   * @var SqlTableDataFlow
   */
  protected $activityAggregationCaseDataFlow;

  public function __construct() {
    parent::__construct();

    // Create the activity data flow and data flow description
    $this->activityDataFlow = new SqlTableDataFlow($this->getTable(), $this->getSourceName().'_activity');
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

      $this->entityDataFlow = new SubqueryDataFlow($this->getSourceName(), $this->activityDataFlow->getTable(), $this->activityDataFlow->getTableAlias());
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
      $this->activityAggregationDataFlow = new SqlTableDataFlow($this->getTable(), $this->getSourceName().'_activity_aggregate_');
      $this->activityAggregationContactDataFlow = new SqlTableDataFlow('civicrm_activity_contact', $this->getSourceName().'_activity_contact_aggregate_');
      $this->activityAggregationCaseDataFlow = new SqlTableDataFlow('civicrm_case_activity', $this->getSourceName().'_activity_case_aggregate_');

      $activityDataDescription = new DataFlowDescription($this->activityAggregationDataFlow);
      $contactJoin = new SimpleNonRequiredJoin($this->activityAggregationDataFlow->getTableAlias(), 'id', $this->activityAggregationContactDataFlow->getTableAlias(), 'activity_id', 'LEFT');
      $contactJoin->setDataProcessor($this->dataProcessor);
      $activityContactDataDescription = new DataFlowDescription($this->activityAggregationContactDataFlow, $contactJoin);

      $caseJoin = new SimpleNonRequiredJoin($this->activityAggregationDataFlow->getTableAlias(), 'id', $this->activityAggregationCaseDataFlow->getTableAlias(), 'activity_id', 'LEFT');
      $caseJoin->setDataProcessor($this->dataProcessor);
      $activityCaseDataDescription = new DataFlowDescription($this->activityAggregationCaseDataFlow, $caseJoin);

      $aggrgeate_field_spec = clone $this->getAvailableFields()->getFieldSpecificationByName($this->getAggregateField());
      $aggrgeate_field_spec->setMySqlFunction($this->getAggregateFunction());
      $aggrgeate_field_spec->alias = $this->getAggregateField();

      $aggretated_table_dataflow = new CombinedSqlDataFlow('', $this->activityAggregationDataFlow->getTable(),$this->activityAggregationDataFlow->getTableAlias());
      $aggretated_table_dataflow->addSourceDataFlow($activityDataDescription);
      $aggretated_table_dataflow->addSourceDataFlow($activityContactDataDescription);
      $aggretated_table_dataflow->addSourceDataFlow($activityCaseDataDescription);
      $this->activityAggregationDataFlow->getDataSpecification()->addFieldSpecification($aggrgeate_field_spec->name, $aggrgeate_field_spec);
      foreach ($groupByFields as $groupByField) {
        if (stripos($groupByField->alias, 'activity_contact_') === 0) {
          $this->activityAggregationContactDataFlow->getDataSpecification()
            ->addFieldSpecification($groupByField->name, $groupByField);
          $this->activityAggregationContactDataFlow->getGroupByDataSpecification()
            ->addFieldSpecification($groupByField->name, $groupByField);
        } elseif (stripos($groupByField->alias, 'activity_case_') === 0) {
          $this->activityAggregationCaseDataFlow->getDataSpecification()
            ->addFieldSpecification($groupByField->name, $groupByField);
          $this->activityAggregationCaseDataFlow->getGroupByDataSpecification()
            ->addFieldSpecification($groupByField->name, $groupByField);
        } else {
          $this->activityAggregationDataFlow->getDataSpecification()
            ->addFieldSpecification($groupByField->name, $groupByField);
          $this->activityAggregationDataFlow->getGroupByDataSpecification()
            ->addFieldSpecification($groupByField->name, $groupByField);
        }
      }
      $this->aggregationDateFlow = new SubqueryDataFlow('', $this->activityAggregationDataFlow->getTable(), $this->activityAggregationDataFlow->getTableAlias());
      $this->aggregationDateFlow->addSourceDataFlow(new DataFlowDescription($aggretated_table_dataflow));
    }

    return $this->aggregationDateFlow;
  }

  /**
   * Returns the aggregation data flow for a specific field.
   * This could be used to set additional filters on both flows.
   * So that aggregation even works when user enters certain filter criteria.
   *
   * @param \Civi\DataProcessor\DataSpecification\FieldSpecification $field
   *
   * @return \Civi\DataProcessor\DataFlow\CombinedDataFlow\SubqueryDataFlow|null
   * @throws \Exception
   */
  public function getAggregationDataFlowForField(FieldSpecification $field) {
    if ($this->isAggregationEnabled()) {
      $this->ensureEntity();
      if (stripos($field->name, 'activity_contact_')===0) {
        return $this->activityAggregationContactDataFlow;
      } elseif (stripos($field->name, 'activity_case_')===0) {
        return $this->activityAggregationContactDataFlow;
      }
      return $this->activityAggregationDataFlow;
    }
    return null;
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
        $this->activityAggregationContactDataFlow->addWhereClause(new SimpleWhereClause($this->activityAggregationContactDataFlow->getTableAlias(), $name, $op, $values, $filter->type, FALSE));
      } elseif (stripos($filter->name, 'activity_case_') === 0) {
        $name = str_replace('activity_case_', '', $filter->name);
        $this->activityAggregationCaseDataFlow->addWhereClause(new SimpleWhereClause($this->activityAggregationCaseDataFlow->getTableAlias(), $name, $op, $values, $filter->type, FALSE));
      } else {
        $this->activityAggregationDataFlow->addWhereClause(new SimpleWhereClause($this->activityAggregationDataFlow->getTableAlias(), $filter->name, $op, $values, $filter->type, FALSE));
      }
    }
  }

  /**
   * Adds an inidvidual filter to the data source
   *
   * @param $filter_field_alias
   * @param $op
   * @param $values
   *
   * @throws \Exception
   */
  protected function addFilter($filter_field_alias, $op, $values) {
    $spec = null;
    if ($this->getAvailableFields()->doesAliasExists($filter_field_alias)) {
      $spec = $this->getAvailableFields()->getFieldSpecificationByAlias($filter_field_alias);
    } elseif ($this->getAvailableFields()->doesFieldExist($filter_field_alias)) {
      $spec = $this->getAvailableFields()->getFieldSpecificationByName($filter_field_alias);
    }

    if ($spec) {
      if ($spec instanceof CustomFieldSpecification) {
        $customGroupDataFlow = $this->ensureCustomGroup($spec->customGroupTableName, $spec->customGroupName);
        $customGroupTableAlias = $customGroupDataFlow->getTableAlias();
        $customGroupDataFlow->addWhereClause(
          new SimpleWhereClause($customGroupTableAlias, $spec->customFieldColumnName, $op, $values, $spec->type, TRUE)
        );
      } else {
        $this->ensureEntity();
        if (stripos($spec->name, 'activity_contact_') === 0) {
          $name = str_replace('activity_contact_', '', $spec->name);
          $this->activityContactDataFlow->addWhereClause(new SimpleWhereClause($this->activityContactDataFlow->getTableAlias(), $name, $op, $values, $spec->type, FALSE));
        } elseif (stripos($spec->name, 'activity_case_') === 0) {
          $name = str_replace('activity_case_', '', $spec->name);
          $this->activityCaseDataFlow->addWhereClause(new SimpleWhereClause($this->activityCaseDataFlow->getTableAlias(), $name, $op, $values, $spec->type, FALSE));
        } else {
          $this->activityDataFlow->addWhereClause(new SimpleWhereClause($this->activityDataFlow->getTableAlias(), $spec->name, $op, $values, $spec->type, FALSE));
        }
        $this->addFilterToAggregationDataFlow($spec, $op, $values);
      }
    }
  }


  public function ensureField(FieldSpecification $field) {
    if (stripos($field->name, 'activity_contact_') === 0) {
      $this->ensureEntity();
      $field->name = str_replace('activity_contact_', '', $field->name);
      return $this->activityContactDataFlow;
    } elseif (stripos($field->name, 'activity_case_') === 0) {
      $this->ensureEntity();
      $field->name = str_replace('activity_case_', '', $field->name);
      return $this->activityCaseDataFlow;
    }
    if ($this->activityContactDataFlow->getDataSpecification()->doesFieldExist($field->name)) {
      return $this->activityContactDataFlow;
    }
    if ($this->activityCaseDataFlow->getDataSpecification()->doesFieldExist($field->name)) {
      return $this->activityCaseDataFlow;
    }

    return parent::ensureField($field);
  }

  /**
   * Ensure that filter field is accesible in the join part of the query
   *
   * @param FieldSpecification $field
   * @return \Civi\DataProcessor\DataFlow\AbstractDataFlow|null
   * @throws \Exception
   */
  public function ensureFieldForJoin(FieldSpecification $field) {
    if (stripos($field->name, 'activity_contact_') === 0) {
      $this->ensureEntity();
      return $this->entityDataFlow;
    } elseif (stripos($field->name, 'activity_case_') === 0) {
      $this->ensureEntity();
      return $this->entityDataFlow;
    }
    return parent::ensureFieldForJoin($field);
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
