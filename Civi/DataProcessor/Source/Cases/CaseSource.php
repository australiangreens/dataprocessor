<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

namespace Civi\DataProcessor\Source\Cases;

use Civi\DataProcessor\DataFlow\CombinedDataFlow\CombinedSqlDataFlow;
use Civi\DataProcessor\DataFlow\MultipleDataFlows\DataFlowDescription;
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

class CaseSource extends AbstractCivicrmEntitySource {

  /**
   * @var SqlTableDataFlow
   */
  protected $caseDataFlow;

  /**
   * @var SqlTableDataFlow
   */
  protected $caseContactDataFlow;

  public function __construct() {
    parent::__construct();

    // Create the case data flow and data flow description
    $this->caseDataFlow = new SqlTableDataFlow($this->getTable(), $this->getSourceName().'_case', $this->getSourceTitle());
    DataSpecificationUtils::addDAOFieldsToDataSpecification('CRM_Case_DAO_Case', $this->caseDataFlow->getDataSpecification());

    // Create the case contact data flow and data flow description
    $this->caseContactDataFlow = new SqlTableDataFlow('civicrm_case_contact', $this->getSourceName().'_case_contact');
    DataSpecificationUtils::addDAOFieldsToDataSpecification('CRM_Case_DAO_CaseContact', $this->caseContactDataFlow->getDataSpecification(), array('id'), '', 'case_contact_', E::ts('Client :: '));
  }

  /**
   * Returns the entity name
   *
   * @return String
   */
  protected function getEntity() {
    return 'Case';
  }

  /**
   * Returns the table name of this entity
   *
   * @return String
   */
  protected function getTable() {
    return 'civicrm_case';
  }

  /**
   * Returns the default configuration for this data source
   *
   * @return array
   */
  public function getDefaultConfiguration() {
    return array(
      'filter' => array(
        'is_deleted' => array (
          'op' => '=',
          'value' => '0',
        ),
      )
    );
  }

  /**
   * Initialize this data source.
   *
   * @throws \Exception
   */
  public function initialize() {
    if (!$this->primaryDataFlow) {
      $this->primaryDataFlow = $this->getEntityDataFlow();
    }
    $this->addFilters($this->configuration);
    if (count($this->customGroupDataFlowDescriptions) || count($this->additionalDataFlowDescriptions)) {
      $this->dataFlow = new CombinedSqlDataFlow('', $this->primaryDataFlow->getPrimaryTable(), $this->caseDataFlow->getTableAlias());
      $this->dataFlow->addSourceDataFlow(new DataFlowDescription($this->primaryDataFlow));
      foreach ($this->additionalDataFlowDescriptions as $additionalDataFlowDescription) {
        $this->dataFlow->addSourceDataFlow($additionalDataFlowDescription);
      }
      foreach ($this->customGroupDataFlowDescriptions as $customGroupDataFlowDescription) {
        $this->dataFlow->addSourceDataFlow($customGroupDataFlowDescription);
      }
    }
    else {
      $this->dataFlow = $this->primaryDataFlow;
    }
  }

  /**
   * @return \Civi\DataProcessor\DataFlow\SqlDataFlow
   * @throws \Exception
   */
  protected function getEntityDataFlow() {
    $caseDataDescription = new DataFlowDescription($this->caseDataFlow);

    $join = new SimpleJoin($this->caseDataFlow->getTableAlias(), 'id', $this->caseContactDataFlow->getTableAlias(), 'case_id');
    $join->setDataProcessor($this->dataProcessor);
    $caseContactDataDescription = new DataFlowDescription($this->caseContactDataFlow, $join);

    // Create the subquery data flow
    $this->entityDataFlow = new SubqueryDataFlow($this->getSourceName(), $this->caseDataFlow->getTable(), $this->caseDataFlow->getTableAlias());
    $this->entityDataFlow->addSourceDataFlow($caseDataDescription);
    $this->entityDataFlow->addSourceDataFlow($caseContactDataDescription);

    return $this->entityDataFlow;
  }

  /**
   * Ensure that the entity table is added the to the data flow.
   *
   * @return \Civi\DataProcessor\DataFlow\AbstractDataFlow
   * @throws \Exception
   */
  protected function ensureEntity() {
    if ($this->primaryDataFlow && $this->primaryDataFlow instanceof SubqueryDataFlow && $this->primaryDataFlow->getPrimaryTable() === $this->getTable()) {
      return $this->primaryDataFlow;
    } elseif (empty($this->primaryDataFlow)) {
      $this->primaryDataFlow = $this->getEntityDataFlow();
      return $this->primaryDataFlow;
    }
    foreach($this->additionalDataFlowDescriptions as $additionalDataFlowDescription) {
      if ($additionalDataFlowDescription->getDataFlow() instanceof SqlTableDataFlow && $additionalDataFlowDescription->getDataFlow()->getTable() == $this->getTable()) {
        return $additionalDataFlowDescription->getDataFlow();
      }
    }
    $entityDataFlow = $this->getEntityDataFlow();
    $join = new SimpleJoin($this->getSourceName(), 'id', $this->getSourceName(), 'entity_id', 'LEFT');
    $join->setDataProcessor($this->dataProcessor);
    $additionalDataFlowDescription = new DataFlowDescription($entityDataFlow,$join);
    $this->additionalDataFlowDescriptions[] = $additionalDataFlowDescription;
    return $additionalDataFlowDescription->getDataFlow();
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
        if (stripos($spec->name, 'case_contact_') === 0) {
          $name = str_replace('case_contact_', '', $spec->name);
          $this->caseContactDataFlow->addWhereClause(new SimpleWhereClause($this->caseContactDataFlow->getTableAlias(), $name, $op, $values, $spec->type, FALSE));
        } else {
          $this->caseDataFlow->addWhereClause(new SimpleWhereClause($this->caseDataFlow->getTableAlias(), $spec->name, $op, $values, $spec->type, TRUE));
        }
        $this->addFilterToAggregationDataFlow($spec, $op, $values);
      }
    }
  }

  public function ensureField(FieldSpecification $field) {
    if (stripos($field->name, 'case_contact_') === 0) {
      $this->ensureEntity();
      $field->name = str_replace('case_contact_', '', $field->name);
      return $this->caseContactDataFlow;
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
    if (stripos($field->name, 'case_contact_') === 0) {
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
    DataSpecificationUtils::addDAOFieldsToDataSpecification('CRM_Case_DAO_CaseContact', $dataSpecification, array('id', 'case_id'), 'case_contact_', $aliasPrefix, E::ts('Client :: '));
  }
}
