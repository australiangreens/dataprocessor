<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

namespace Civi\DataProcessor\Source;

use Civi\DataProcessor\DataFlow\CombinedDataFlow\SubqueryDataFlow;
use Civi\DataProcessor\DataFlow\MultipleDataFlows\JoinInterface;
use Civi\DataProcessor\DataFlow\MultipleDataFlows\SimpleNonRequiredJoin;
use Civi\DataProcessor\DataFlow\MultipleDataFlows\SqlJoinInterface;
use Civi\DataProcessor\DataFlow\SqlDataFlow\PureSqlStatementClause;
use Civi\DataProcessor\DataFlow\SqlDataFlow\SimpleWhereClause;
use Civi\DataProcessor\DataFlow\SqlTableDataFlow;
use Civi\DataProcessor\DataFlow\CombinedDataFlow\CombinedSqlDataFlow;
use Civi\DataProcessor\DataFlow\MultipleDataFlows\DataFlowDescription;
use Civi\DataProcessor\DataFlow\MultipleDataFlows\SimpleJoin;
use Civi\DataProcessor\DataSpecification\CustomFieldSpecification;
use Civi\DataProcessor\DataSpecification\DataSpecification;
use Civi\DataProcessor\DataSpecification\FieldExistsException;
use Civi\DataProcessor\DataSpecification\FieldSpecification;
use Civi\DataProcessor\DataSpecification\Utils as DataSpecificationUtils;
use Civi\DataProcessor\ProcessorType\AbstractProcessorType;

use CRM_Dataprocessor_ExtensionUtil as E;

abstract class AbstractCivicrmEntitySource extends AbstractSource {

  /**
   * @var \Civi\DataProcessor\DataFlow\SqlDataFlow
   */
  protected $dataFlow;

  /**
   * @var \Civi\DataProcessor\DataFlow\SqlDataFlow
   */
  protected $primaryDataFlow;

  /**
   * @var SqlTableDataFlow
   */
  protected $entityDataFlow;

  /**
   * @var SqlJoinInterface
   */
  protected $entityJoin;

  /**
   * @var SubqueryDataFlow
   */
  protected $aggregationDateFlow;

  /**
   * @var \Civi\DataProcessor\DataSpecification\DataSpecification
   */
  protected $availableFields;

  /**
   * @var \Civi\DataProcessor\DataSpecification\DataSpecification
   */
  protected $availableFilterFields;

  /**
   * @var array
   */
  protected $whereClauses = array();


  /**
   * @var array<\Civi\DataProcessor\DataFlow\MultipleDataFlows\DataFlowDescription>
   */
  protected $customGroupDataFlowDescriptions = array();

  /**
   * @var array<\Civi\DataProcessor\DataFlow\MultipleDataFlows\DataFlowDescription>
   */
  protected $additionalDataFlowDescriptions = array();

  /**
   * @var AbstractProcessorType
   */
  protected $dataProcessor;

  /**
   * @var array
   */
  protected $configuration;

  /**
   * Returns the entity name
   *
   * @return String
   */
  abstract protected function getEntity();

  /**
   * Returns the table name of this entity
   *
   * @return String
   */
  abstract protected function getTable();


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
      if ($this->primaryDataFlow instanceof CombinedSqlDataFlow) {
        $this->dataFlow = new CombinedSqlDataFlow('', $this->primaryDataFlow->getPrimaryTable(), $this->primaryDataFlow->getPrimaryTableAlias());
      } elseif ($this->primaryDataFlow instanceof SqlTableDataFlow) {
        $this->dataFlow = new CombinedSqlDataFlow('', $this->primaryDataFlow->getTable(), $this->primaryDataFlow->getTableAlias());
      } else {
        throw new \Exception("Invalid primary data source in data source ".$this->getSourceName());
      }
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

  protected function reset() {
    $this->primaryDataFlow = $this->getEntityDataFlow();
    $this->dataFlow = null;
    $this->additionalDataFlowDescriptions = array();
    $this->availableFields = null;
    $this->availableFilterFields = null;
  }

  /**
   * @param String $name
   * @return \Civi\DataProcessor\Source\SourceInterface
   */
  public function setSourceName($name) {
    $this->availableFields = null;
    $this->availableFilterFields = null;
    return parent::setSourceName($name);
  }

  protected function getEntityTableAlias() {
    if ($this->entityDataFlow instanceof SqlTableDataFlow) {
      $entityTableAlias = $this->entityDataFlow->getTableAlias();
    } elseif ($this->entityDataFlow instanceof CombinedSqlDataFlow) {
      $entityTableAlias = $this->entityDataFlow->getPrimaryTableAlias();
    } else {
      throw new \Exception('No entity data flow set.');
    }
    return $entityTableAlias;
  }

  /**
   * @return \Civi\DataProcessor\DataFlow\SqlDataFlow
   */
  protected function getEntityDataFlow() {
    if (!$this->entityDataFlow) {
      $this->entityDataFlow = new SqlTableDataFlow($this->getTable(), $this->getSourceName());
    }
    if ($this->isAggregationEnabled()) {
      $this->getAggregationDataFlow();

      $dataFlow = new CombinedSqlDataFlow('', $this->aggregationDateFlow->getPrimaryTable(), $this->aggregationDateFlow->getPrimaryTableAlias());
      $dataFlow->addSourceDataFlow(new DataFlowDescription($this->aggregationDateFlow));
      $dataFlowDescription = new DataFlowDescription($this->entityDataFlow, $this->getAggregationJoin($this->getEntityTableAlias()));
      $dataFlow->addSourceDataFlow($dataFlowDescription);
      return $dataFlow;
    } else {
      return $this->entityDataFlow;
    }
  }

  protected function getAggregationDataFlow() {
    $groupByFields = array();
    foreach($this->configuration['aggregate_by'] as $aggregate_by) {
      $field = clone $this->getAvailableFields()->getFieldSpecificationByName($aggregate_by);
      $field->alias = $aggregate_by;
      $groupByFields[] = $field;
    }

    if (!$this->aggregationDateFlow) {
      $aggrgeate_field_spec = clone $this->getAvailableFields()->getFieldSpecificationByName($this->getAggregateField());
      $aggrgeate_field_spec->setMySqlFunction($this->getAggregateFunction());
      $aggrgeate_field_spec->alias = $this->getAggregateField();

      $aggretated_table_dataflow = new SqlTableDataFlow($this->getTable(), '_aggregated_'.$this->getSourceName());
      $aggretated_table_dataflow->getDataSpecification()->addFieldSpecification($aggrgeate_field_spec->name, $aggrgeate_field_spec);
      foreach ($groupByFields as $groupByField) {
        $aggretated_table_dataflow->getDataSpecification()
          ->addFieldSpecification($groupByField->name, $groupByField);
        $aggretated_table_dataflow->getGroupByDataSpecification()
          ->addFieldSpecification($groupByField->name, $groupByField);
      }

      $this->aggregationDateFlow = new SubqueryDataFlow('', $this->getTable(), '_aggregated_' . $this->getSourceName());
      $this->aggregationDateFlow->addSourceDataFlow(new DataFlowDescription($aggretated_table_dataflow));
    }

    return $this->aggregationDateFlow;
  }

  /**
   * Returns JOIN specification for joining on the aggregation source.
   *
   * @param $entityTableAlias
   *
   * @return \Civi\DataProcessor\DataFlow\MultipleDataFlows\SimpleNonRequiredJoin
   * @throws \Exception
   */
  protected function getAggregationJoin($entityTableAlias) {
    $groupByFields = array();
    foreach($this->configuration['aggregate_by'] as $aggregate_by) {
      $field = clone $this->getAvailableFields()->getFieldSpecificationByName($aggregate_by);
      $field->alias = $aggregate_by;
      $groupByFields[] = $field;
    }

    $join = new SimpleNonRequiredJoin($entityTableAlias, $this->getAggregateField(), $this->aggregationDateFlow->getPrimaryTableAlias(), $this->getAggregateField(), 'LEFT');
    foreach($groupByFields as $groupByField) {
      $join->addFilterClause(new PureSqlStatementClause("`{$entityTableAlias}`.`{$groupByField->alias}` = `{$this->aggregationDateFlow->getPrimaryTableAlias()}`.`{$groupByField->alias}`", TRUE));
    }
    $join->setDataProcessor($this->dataProcessor);
    return $join;
  }

  /**
   * Load the fields from this entity.
   *
   * @param DataSpecification $dataSpecification
   * @throws \Civi\DataProcessor\DataSpecification\FieldExistsException
   */
  protected function loadFields(DataSpecification $dataSpecification, $fieldsToSkip=array()) {
    $daoClass = \CRM_Core_DAO_AllCoreTables::getFullName($this->getEntity());
    $aliasPrefix = '';
    if ($this->getSourceName()) {
      $aliasPrefix = $this->getSourceName() . '_';
    }

    DataSpecificationUtils::addDAOFieldsToDataSpecification($daoClass, $dataSpecification, $fieldsToSkip, '', $aliasPrefix);
  }

  /**
   * Add custom fields to the available fields section
   *
   * @param DataSpecification $dataSpecification
   * @param bool $onlySearchAbleFields
   * @param $entity
   * @throws \Civi\DataProcessor\DataSpecification\FieldExistsException
   * @throws \Exception
   */
  protected function loadCustomGroupsAndFields(DataSpecification $dataSpecification, $onlySearchAbleFields, $entity=null) {
    if (!$entity) {
      $entity = $this->getEntity();
    }
    $aliasPrefix = '';
    if ($this->getSourceName()) {
      $aliasPrefix = $this->getSourceName() . '_';
    }
    DataSpecificationUtils::addCustomFieldsToDataSpecification($entity, $dataSpecification, $onlySearchAbleFields, $aliasPrefix);
  }

  /**
   * Add the filters to the where clause of the data flow
   *
   * @param $configuration
   * @throws \Exception
   */
  protected function addFilters($configuration) {
    if (isset($configuration['filter']) && is_array($configuration['filter'])) {
      foreach($configuration['filter'] as $filter_alias => $filter_field) {
        $this->addFilter($filter_alias, $filter_field['op'], $filter_field['value']);
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
        $tableAlias = $this->getEntityTableAlias();
        $this->entityDataFlow->addWhereClause(new SimpleWhereClause($tableAlias, $spec->name,$op, $values, $spec->type, TRUE));
        $this->addFilterToAggregationDataFlow($spec, $op, $values);
      }
    }
  }

  /**
   * Add a filter to the aggregation data flow.
   *
   * @param \Civi\DataProcessor\DataSpecification\FieldSpecification $filter
   * @param $op
   * @param $values
   */
  protected function addFilterToAggregationDataFlow(FieldSpecification $filter, $op, $values) {
    if ($this->isAggregationEnabled()){
      $aggregationFlow = $this->getAggregationDataFlowForField($filter);
      $tableAlias = $aggregationFlow->getName();
      if ($aggregationFlow instanceof SqlTableDataFlow) {
        $tableAlias = $aggregationFlow->getTableAlias();
      } elseif ($aggregationFlow instanceof CombinedSqlDataFlow) {
        $tableAlias = $aggregationFlow->getPrimaryTableAlias();
      }
      $aggregationFlow ->addWhereClause(new SimpleWhereClause($tableAlias, $filter->name,$op, $values, $filter->type, FALSE));
    }
  }

  /**
   * Ensure that filter or aggregate field is accesible in the query
   *
   * @param FieldSpecification $field
   * @return \Civi\DataProcessor\DataFlow\AbstractDataFlow|null
   * @throws \Exception
   */
  public function ensureField(FieldSpecification $field) {
    if ($this->getAvailableFilterFields()->doesAliasExists($field->alias)) {
      $spec = $this->getAvailableFilterFields()->getFieldSpecificationByAlias($field->alias);
      if ($spec instanceof CustomFieldSpecification) {
        return $this->ensureCustomGroup($spec->customGroupTableName, $spec->customGroupName);
      }
      return $this->ensureEntity();
    } elseif ($this->getAvailableFilterFields()->doesFieldExist($field->name)) {
      $spec = $this->getAvailableFilterFields()->getFieldSpecificationByName($field->name);
      if ($spec instanceof CustomFieldSpecification) {
        return $this->ensureCustomGroup($spec->customGroupTableName, $spec->customGroupName);
      }
      return $this->ensureEntity();
    }
  }

  /**
   * Ensure a custom group is added the to the data flow.
   *
   * @param $customGroupTableName
   * @param $customGroupName
   * @return \Civi\DataProcessor\DataFlow\AbstractDataFlow
   * @throws \Exception
   */
  protected function ensureCustomGroup($customGroupTableName, $customGroupName) {
    if (isset($this->customGroupDataFlowDescriptions[$customGroupName])) {
      return $this->customGroupDataFlowDescriptions[$customGroupName]->getDataFlow();
    } elseif ($this->primaryDataFlow && $this->primaryDataFlow instanceof SqlTableDataFlow && $this->primaryDataFlow->getTable() == $customGroupTableName) {
      return $this->primaryDataFlow;
    }
    $customGroupTableAlias = $this->getSourceName().'_'.$customGroupName;
    $this->ensureEntity(); // Ensure the entity as we need it before joining.
    $join = new SimpleJoin($this->getSourceName(), 'id', $customGroupTableAlias, 'entity_id', 'LEFT');
    $join->setDataProcessor($this->dataProcessor);
    if (!$this->entityJoin) {
      $this->entityJoin = $join;
    }
    $this->customGroupDataFlowDescriptions[$customGroupName] = new DataFlowDescription(
      new SqlTableDataFlow($customGroupTableName, $customGroupTableAlias, new DataSpecification()),
      $join
    );
    $this->dataProcessor->resetDataFlow();
    return $this->customGroupDataFlowDescriptions[$customGroupName]->getDataFlow();
  }

  /**
   * Ensure that the entity table is added the to the data flow.
   *
   * @return \Civi\DataProcessor\DataFlow\AbstractDataFlow
   * @throws \Exception
   */
  protected function ensureEntity() {
    if ($this->primaryDataFlow && $this->primaryDataFlow instanceof SqlTableDataFlow && $this->primaryDataFlow->getTable() === $this->getTable()) {
      return $this->entityDataFlow;
    } elseif ($this->primaryDataFlow && $this->primaryDataFlow instanceof CombinedSqlDataFlow && $this->primaryDataFlow->getPrimaryTable() === $this->getTable()) {
      return $this->entityDataFlow;
    } elseif (empty($this->primaryDataFlow)) {
      $this->getEntityDataFlow();
      return $this->entityDataFlow;
    }
    foreach($this->additionalDataFlowDescriptions as $additionalDataFlowDescription) {
      if ($additionalDataFlowDescription->getDataFlow()->getTable() == $this->getTable()) {
        return $additionalDataFlowDescription->getDataFlow();
      }
    }
    $entityDataFlow = $this->getEntityDataFlow();
    if (!$this->entityJoin) {
      $this->entityJoin = new SimpleJoin($this->getSourceName(), 'id', $this->primaryDataFlow->getTableAlias(), 'entity_id', 'LEFT');
      $this->entityJoin->setDataProcessor($this->dataProcessor);
    }
    $additionalDataFlowDescription = new DataFlowDescription($entityDataFlow, $this->entityJoin);
    $this->additionalDataFlowDescriptions[] = $additionalDataFlowDescription;
    return $this->entityDataFlow;
  }

  /**
   * Sets the join specification to connect this source to other data sources.
   *
   * @param \Civi\DataProcessor\DataFlow\MultipleDataFlows\JoinInterface $join
   *
   * @return \Civi\DataProcessor\Source\SourceInterface
   */
  public function setJoin(JoinInterface $join) {
    foreach($this->customGroupDataFlowDescriptions as $idx => $customGroupDataFlowDescription) {
      if ($join->worksWithDataFlow($customGroupDataFlowDescription->getDataFlow())) {
        $this->primaryDataFlow = $customGroupDataFlowDescription->getDataFlow();
        $this->entityJoin = $customGroupDataFlowDescription->getJoinSpecification();
        unset($this->customGroupDataFlowDescriptions[$idx]);
        unset($this->dataFlow);
      }
    }
    foreach($this->additionalDataFlowDescriptions as $idx => $additionalDataFlowDescription) {
      if ($join->worksWithDataFlow($additionalDataFlowDescription->getDataFlow())) {
        $this->primaryDataFlow = $additionalDataFlowDescription->getDataFlow();
        $this->entityJoin = $additionalDataFlowDescription->getJoinSpecification();
        unset($this->additionalDataFlowDescriptions[$idx]);
        unset($this->dataFlow);
      }
    }

    if ($this->isAggregationEnabled()) {
      if ($join instanceof SimpleJoin && $join->getLeftTable() == $this->getSourceName()) {
        $join->setLeftTable($this->aggregationDateFlow->getPrimaryTableAlias());
        $join->setLeftPrefix($this->aggregationDateFlow->getPrimaryTableAlias());
      }
      elseif ($join instanceof SimpleJoin && $join->getRightTable() == $this->getSourceName()) {
        $join->setRightTable($this->aggregationDateFlow->getPrimaryTableAlias());
        $join->setRightPrefix($this->aggregationDateFlow->getPrimaryTableAlias());
      }
    }
    return $this;
  }

  /**
   * @return \Civi\DataProcessor\DataSpecification\DataSpecification
   * @throws \Exception
   */
  public function getAvailableFields() {
    if (!$this->availableFields) {
      $this->availableFields = new DataSpecification();
      $this->loadFields($this->availableFields, array());
      $this->loadCustomGroupsAndFields($this->availableFields, false);
    }
    return $this->availableFields;
  }

  /**
   * @return \Civi\DataProcessor\DataSpecification\DataSpecification
   * @throws \Exception
   */
  public function getAvailableFilterFields() {
    if (!$this->availableFilterFields) {
      $this->availableFilterFields = new DataSpecification();
      $this->loadFields($this->availableFilterFields, array());
      $this->loadCustomGroupsAndFields($this->availableFilterFields, true);
    }
    return $this->availableFilterFields;
  }

  /**
   * Ensures a field is in the data source
   *
   * @param \Civi\DataProcessor\DataSpecification\FieldSpecification $fieldSpecification
   * @throws \Exception
   */
  public function ensureFieldInSource(FieldSpecification $fieldSpecification) {
    try {
      $originalFieldSpecification = null;
      if ($this->getAvailableFields()->doesAliasExists($fieldSpecification->alias)) {
        $originalFieldSpecification = $this->getAvailableFields()->getFieldSpecificationByAlias($fieldSpecification->alias);
      } elseif ($this->getAvailableFields()->doesFieldExist($fieldSpecification->name)) {
        $originalFieldSpecification = $this->getAvailableFields()
          ->getFieldSpecificationByName($fieldSpecification->name);
      }
      if ($originalFieldSpecification && $originalFieldSpecification instanceof CustomFieldSpecification) {
        $dataFlow = $this->ensureCustomGroup($originalFieldSpecification->customGroupTableName, $originalFieldSpecification->customGroupName);
        if (!$dataFlow->getDataSpecification()->doesAliasExists($fieldSpecification->alias)) {
          $dataFlow->getDataSpecification()->addFieldSpecification($fieldSpecification->alias, $fieldSpecification);
        } else {
        }
      } elseif ($originalFieldSpecification) {
        $this->ensureEntity();
        $this->entityDataFlow->getDataSpecification()->addFieldSpecification($fieldSpecification->alias, $fieldSpecification);
      }
    } catch (FieldExistsException $e) {
      // Do nothing.
    }
  }

  /**
   * @return \Civi\DataProcessor\DataFlow\SqlDataFlow
   */
  public function getPrimaryDataFlow() {
    return $this->primaryDataFlow;
  }

  /**
   * Returns true when aggregation is enabled for this data source.
   *
   * @return bool
   */
  public function isAggregationEnabled() {
    if (!empty($this->configuration['aggregate_function'])) {
      return TRUE;
    }
    return false;
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
      return $this->aggregationDateFlow;
    }
    return null;
  }

  /**
   * @return string|false
   */
  protected function getAggregateFunction() {
    if (strpos($this->configuration['aggregate_function'], 'min_') === 0) {
      return 'MIN';
    } elseif (strpos($this->configuration['aggregate_function'], 'max_') === 0) {
      return 'MAX';
    }
    return false;
  }

  /**
   * Returns the name of the aggregated field.
   *
   * @return string|false
   */
  protected function getAggregateField() {
    if (strpos($this->configuration['aggregate_function'], 'min_') === 0) {
      return substr($this->configuration['aggregate_function'], 4);
    } elseif (strpos($this->configuration['aggregate_function'], 'max_') === 0) {
      return substr($this->configuration['aggregate_function'], 4);
    }
    return false;
  }

  /**
   * Returns an array with possible aggregate functions.
   * Return false when aggregation is not possible.
   *
   * This function could be overridden in child classes.
   *
   * @return array|false
   */
  protected function getPossibleAggregateFunctions() {
    return false;
  }

  /**
   * When this source has configuration specify the template file name
   * for the configuration form.
   *
   * @return false|string
   */
  public function getConfigurationTemplateFileName() {
    return "CRM/Dataprocessor/Form/Source/CiviCRMEntitySourceConfiguration.tpl";
  }

  /**
   * When this source has additional configuration you can add
   * the fields on the form with this function.
   *
   * @param \CRM_Core_Form $form
   * @param array $source
   */
  public function buildConfigurationForm(\CRM_Core_Form $form, $source=array()) {
    parent::buildConfigurationForm($form, $source);
    $fields = array();
    foreach($this->getAvailableFields()->getFields() as $field) {
      $fields[$field->getName()] = $field->title;
    }
    $aggregateFunctions = $this->getPossibleAggregateFunctions();
    $form->assign('aggregation_available', false);
    if (is_array($aggregateFunctions)) {
      $form->assign('aggregation_available', true);
      $form->add('select', "aggregate_function", E::ts('Aggregate function'), $aggregateFunctions, FALSE, [
        'style' => 'min-width:250px',
        'class' => 'crm-select2 huge',
        'placeholder' => E::ts('No aggregation'),
      ]);
      $form->add('select', "aggregate_by", E::ts('Aggregate by'), $fields, FALSE, [
        'style' => 'min-width:250px',
        'class' => 'crm-select2 huge',
        'multiple' => 'multiple',
        'placeholder' => E::ts('- select -'),
      ]);
      $form->addFormRule([$this, 'validateConfigurationForm']);
      $defaults = array();
      if (isset($source['configuration']['aggregate_function'])) {
        $defaults['aggregate_function'] = $source['configuration']['aggregate_function'];
      }
      if (isset($source['configuration']['aggregate_by'])) {
        $defaults['aggregate_by'] = $source['configuration']['aggregate_by'];
      }
      $form->setDefaults($defaults);
    }
  }

  public function validateConfigurationForm($fields) {
    $errors = [];
    if (!empty($fields['aggregate_function']) && empty($fields['aggregate_by'])) {
      $errors['aggregate_by'] = E::ts('Select at least one field at aggregate by');
    }
    return count($errors) ? $errors : true;
  }

  /**
   * Process the submitted values and create a configuration array
   *
   * @param $submittedValues
   * @return array
   */
  public function processConfiguration($submittedValues) {
    $configuration = parent::processConfiguration($submittedValues);
    if (isset($submittedValues['aggregate_function'])) {
      $configuration['aggregate_function'] = $submittedValues['aggregate_function'];
      $configuration['aggregate_by'] = $submittedValues['aggregate_by'];
    }
    return $configuration;
  }

}
