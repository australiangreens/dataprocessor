<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

namespace Civi\DataProcessor\FilterHandler;

use Civi\DataProcessor\DataFlow\SqlDataFlow;
use Civi\DataProcessor\DataSpecification\CustomFieldSpecification;
use Civi\DataProcessor\DataSpecification\FieldSpecification;
use Civi\DataProcessor\Exception\DataSourceNotFoundException;
use Civi\DataProcessor\Exception\FieldNotFoundException;
use Civi\DataProcessor\Exception\InvalidConfigurationException;
use CRM_Dataprocessor_ExtensionUtil as E;

class MultipleFieldFilter extends AbstractFilterHandler {

  /**
   * @var \Civi\DataProcessor\DataSpecification\FieldSpecification
   */
  protected $fieldSpecification;

  /**
   * @var \Civi\DataProcessor\DataFlow\SqlDataFlow\WhereClauseInterface
   */
  protected $whereClause;

  /**
   * Initialize the filter
   *
   * @throws \Civi\DataProcessor\Exception\DataSourceNotFoundException
   * @throws \Civi\DataProcessor\Exception\InvalidConfigurationException
   * @throws \Civi\DataProcessor\Exception\FieldNotFoundException
   */
  protected function doInitialization() {
    if (!isset($this->configuration['fields']) || !is_array($this->configuration['fields']) || !count($this->configuration['fields'])) {
      throw new InvalidConfigurationException(E::ts("Filter %1 requires at least one field to filter on. None given.", array(1=>$this->title)));
    }
    foreach($this->configuration['fields'] as $fieldAndDataSource) {
      list($dataSource, $field) = explode("::", $fieldAndDataSource);
      $this->initializeField($dataSource, $field);
    }

    $this->fieldSpecification = new FieldSpecification($this->alias, 'String', $this->title, null, $this->alias);
  }

  /**
   * @param $datasource_name
   * @param $field_name
   *
   * @throws \Civi\DataProcessor\Exception\DataSourceNotFoundException
   * @throws \Civi\DataProcessor\Exception\FieldNotFoundException
   */
  protected function initializeField($datasource_name, $field_name) {
    $dataSource = $this->data_processor->getDataSourceByName($datasource_name);
    if (!$dataSource) {
      throw new DataSourceNotFoundException(E::ts("Filter %1 requires data source '%2' which could not be found. Did you rename or deleted the data source?", array(1=>$this->title, 2=>$datasource_name)));
    }
    $fieldSpecification = $dataSource->getAvailableFilterFields()->getFieldSpecificationByAlias($field_name);
    if (!$fieldSpecification) {
      $fieldSpecification = $dataSource->getAvailableFilterFields()
        ->getFieldSpecificationByName($field_name);
    }
    if (!$fieldSpecification) {
      throw new FieldNotFoundException(E::ts("Filter %1 requires a field with the name '%2' in the data source '%3'. Did you change the data source type?", array(
        1 => $this->title,
        2 => $field_name,
        3 => $datasource_name
      )));
    }
  }

  /**
   * Returns true when this filter has additional configuration
   *
   * @return bool
   */
  public function hasConfiguration() {
    return true;
  }

  /**
   * When this filter type has additional configuration you can add
   * the fields on the form with this function.
   *
   * @param \CRM_Core_Form $form
   * @param array $filter
   */
  public function buildConfigurationForm(\CRM_Core_Form $form, $filter=array()) {
    $fieldSelect = \CRM_Dataprocessor_Utils_DataSourceFields::getAvailableFilterFieldsInDataSources($filter['data_processor_id'], [$this, 'filterFieldsForConfiguration']);

    $form->add('select', 'fields', E::ts('Fields'), $fieldSelect, true, array(
      'style' => 'min-width:250px',
      'class' => 'crm-select2 huge data-processor-field-for-name',
      'placeholder' => E::ts('- select -'),
      'multiple' => true,
    ));
    if (isset($filter['configuration'])) {
      $configuration = $filter['configuration'];
      if (isset($configuration['fields'])) {
        $defaults['fields'] = $configuration['fields'];
        $form->setDefaults($defaults);
      }
    }
  }

  /**
   * When this filter type has configuration specify the template file name
   * for the configuration form.
   *
   * @return false|string
   */
  public function getConfigurationTemplateFileName() {
    return "CRM/Dataprocessor/Form/Filter/Configuration/MultipleFieldFilter.tpl";
  }


  /**
   * Process the submitted values and create a configuration array
   *
   * @param $submittedValues
   * @return array
   */
  public function processConfiguration($submittedValues) {
    $configuration['fields'] = $submittedValues['fields'];
    return $configuration;
  }

  /**
   * @param \Civi\DataProcessor\DataSpecification\FieldSpecification $field
   * @return bool
   */
  public function filterFieldsForConfiguration(FieldSpecification $field) {
    switch ($field->type) {
      case 'String':
        return true;
        break;
    }
    return false;
  }

  /**
   * @return \Civi\DataProcessor\DataSpecification\FieldSpecification
   */
  public function getFieldSpecification() {
    return $this->fieldSpecification;
  }

  /**
   * Resets the filter
   *
   * @return void
   * @throws \Exception
   */
  public function resetFilter() {
    if (!$this->isInitialized()) {
      return;
    }
    $dataFlow  = $this->data_processor->getDataFlow();
    if ($dataFlow && $dataFlow instanceof SqlDataFlow && $this->whereClause) {
      $dataFlow->removeWhereClause($this->whereClause);
      unset($this->whereClause);
    }
  }

  /**
   * @param array $filterParams
   *   The filter settings
   *
   * @return mixed
   */
  public function setFilter($filterParams) {
    $this->resetFilter();
    $clauses = array();
    foreach($this->configuration['fields'] as $fieldAndDataSource) {
      list($datasource_name, $field_name) = explode("::", $fieldAndDataSource);
      $dataSource = $this->data_processor->getDataSourceByName($datasource_name);
      $fieldSpec = $dataSource->getAvailableFields()->getFieldSpecificationByAlias($field_name);
      if (!$fieldSpec) {
        $fieldSpec = $dataSource->getAvailableFields()->getFieldSpecificationByName($field_name);
      }
      $dataFlow = $dataSource->ensureField($fieldSpec);
      if ($dataFlow && $dataFlow instanceof SqlDataFlow) {
        $clauses[] = new SqlDataFlow\SimpleWhereClause($dataFlow->getName(), $fieldSpec->getName(), $filterParams['op'], $filterParams['value'], $this->fieldSpecification->type);
      }
    }
    if (count($clauses)) {
      switch ($filterParams['op']) {
        case 'IS NULL':
        case 'NOT LIKE':
        case '!=':
        $this->whereClause = new SqlDataFlow\AndClause($clauses);
        default:
          $this->whereClause = new SqlDataFlow\OrClause($clauses);
          break;
      }
      $dataFlow  = $this->data_processor->getDataFlow();
      if ($dataFlow && $dataFlow instanceof SqlDataFlow) {
        $dataFlow->addWhereClause($this->whereClause);
      }
    }
  }


}
