<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

namespace Civi\DataProcessor\Source\Contribution;

use Civi\DataProcessor\DataFlow\CombinedDataFlow\CombinedSqlDataFlow;
use Civi\DataProcessor\DataFlow\MultipleDataFlows\DataFlowDescription;
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

class ContributionSource extends AbstractCivicrmEntitySource {

  /**
   * @var SqlTableDataFlow
   */
  protected $contributionDataFlow;

  /**
   * @var SqlTableDataFlow
   */
  protected $contributionSoftDataFlow;

  public function __construct() {
    parent::__construct();

    // Create the contribution data flow and data flow description
    $this->contributionDataFlow = new SqlTableDataFlow($this->getTable(), $this->getSourceName().'_contribution', $this->getSourceTitle());
    DataSpecificationUtils::addDAOFieldsToDataSpecification('CRM_Contribute_DAO_Contribution', $this->contributionDataFlow->getDataSpecification());

    // Create the contribution soft data flow and data flow description
    $this->contributionSoftDataFlow = new SqlTableDataFlow('civicrm_contribution_soft', $this->getSourceName().'_contribution_soft');
    DataSpecificationUtils::addDAOFieldsToDataSpecification('CRM_Contribute_DAO_ContributionSoft', $this->contributionSoftDataFlow->getDataSpecification(), array('id'), '', 'contribution_soft_', E::ts('Soft :: '));
  }



  /**
   * Returns the entity name
   *
   * @return String
   */
  protected function getEntity() {
    return 'Contribution';
  }

  /**
   * Returns the table name of this entity
   *
   * @return String
   */
  protected function getTable() {
    return 'civicrm_contribution';
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
      $this->dataFlow = new CombinedSqlDataFlow('', $this->primaryDataFlow->getPrimaryTable(), $this->contributionDataFlow->getTableAlias());
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
    $contributionDataDescription = new DataFlowDescription($this->contributionDataFlow);

    $join = new SimpleJoin($this->contributionDataFlow->getTableAlias(), 'id', $this->contributionSoftDataFlow->getTableAlias(), 'contribution_id', 'LEFT');
    $join->setDataProcessor($this->dataProcessor);
    $contributionSoftDataDescription = new DataFlowDescription($this->contributionSoftDataFlow, $join);

    // Create the subquery data flow
    $this->entityDataFlow = new SubqueryDataFlow($this->getSourceName(), $this->contributionDataFlow->getTable(), $this->contributionDataFlow->getTableAlias());
    $this->entityDataFlow->addSourceDataFlow($contributionDataDescription);
    $this->entityDataFlow->addSourceDataFlow($contributionSoftDataDescription);

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
      if ($additionalDataFlowDescription->getDataFlow()->getTable() == $this->getTable()) {
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
        if (stripos($spec->name, 'contribution_soft_') === 0) {
          $name = str_replace('contribution_soft_', '', $spec->name);
          $this->contributionSoftDataFlow->addWhereClause(new SimpleWhereClause($this->contributionSoftDataFlow->getTableAlias(), $name, $op, $values, $spec->type, FALSE));
        } else {
          $this->contributionDataFlow->addWhereClause(new SimpleWhereClause($this->contributionDataFlow->getTableAlias(), $spec->name, $op, $values, $spec->type, TRUE));
        }
        $this->addFilterToAggregationDataFlow($spec, $op, $values);
      }
    }
  }

  public function ensureField(FieldSpecification $field) {
    if (stripos($field->name, 'contribution_soft_') === 0) {
      $this->ensureEntity();
      $field->name = str_replace('contribution_soft_', '', $field->name);
      return $this->contributionSoftDataFlow;
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
    if (stripos($field->name, 'contribution_soft_') === 0) {
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
    DataSpecificationUtils::addDAOFieldsToDataSpecification('CRM_Contribute_DAO_ContributionSoft', $dataSpecification, array('id', 'contribution_id'), 'contribution_soft_', $aliasPrefix.'_contribution_soft_', E::ts('Soft :: '));
  }

  /**
   * Returns the default configuration for this data source
   *
   * @return array
   */
  public function getDefaultConfiguration() {
    return array(
      'filter' => array(
        'is_test' => array (
          'op' => '=',
          'value' => '0',
        )
      )
    );
  }
}
