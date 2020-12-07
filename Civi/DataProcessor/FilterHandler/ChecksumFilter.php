<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

namespace Civi\DataProcessor\FilterHandler;

use Civi\DataProcessor\DataFlow\InMemoryDataFlow;
use Civi\DataProcessor\DataFlow\SqlDataFlow;
use Civi\DataProcessor\Exception\DataSourceNotFoundException;
use Civi\DataProcessor\Exception\FieldNotFoundException;
use Civi\DataProcessor\Exception\InvalidConfigurationException;
use CRM_Dataprocessor_ExtensionUtil as E;

class ChecksumFilter extends AbstractFieldFilterHandler {

  /**
   * @var array
   *   Filter configuration
   */
  protected $configuration;

  /**
   * @var \Civi\DataProcessor\DataSpecification\FieldSpecification
   */
  protected $hashInputFieldSpecification;

  /**
   * @var \Civi\DataProcessor\Source\SourceInterface
   */
  protected $hashDataSource;

  public function __construct() {
    parent::__construct();
  }

  /**
   * Initialize the filter
   *
   * @throws \Civi\DataProcessor\Exception\DataSourceNotFoundException
   * @throws \Civi\DataProcessor\Exception\InvalidConfigurationException
   * @throws \Civi\DataProcessor\Exception\FieldNotFoundException
   */
  protected function doInitialization() {
    if (!isset($this->configuration['datasource']) || !isset($this->configuration['field'])) {
      throw new InvalidConfigurationException(E::ts("Filter %1 requires a field to filter on. None given.", array(1=>$this->title)));
    }
    $this->initializeField($this->configuration['datasource'], $this->configuration['field']);
    $this->initializeHashField($this->configuration['hash_datasource'], $this->configuration['hash_field']);
    $this->fieldSpecification->type = 'String';
  }

  /**
   * @param $datasource_name
   * @param $field_name
   *
   * @throws \Civi\DataProcessor\Exception\DataSourceNotFoundException
   * @throws \Civi\DataProcessor\Exception\FieldNotFoundException
   */
  protected function initializeHashField($datasource_name, $field_name) {
    $this->hashDataSource = $this->data_processor->getDataSourceByName($datasource_name);
    if (!$this->hashDataSource) {
      throw new DataSourceNotFoundException(E::ts("Filter %1 requires data source '%2' which could not be found. Did you rename or deleted the data source?", array(1=>$this->title, 2=>$datasource_name)));
    }
    $this->hashInputFieldSpecification = $this->hashDataSource->getAvailableFilterFields()->getFieldSpecificationByAlias($field_name);
    if (!$this->hashInputFieldSpecification) {
      $this->hashInputFieldSpecification = $this->hashDataSource->getAvailableFilterFields()->getFieldSpecificationByName($field_name);
    }
    if (!$this->hashInputFieldSpecification) {
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
    $fieldSelect = \CRM_Dataprocessor_Utils_DataSourceFields::getAvailableFilterFieldsInDataSources($filter['data_processor_id']);

    $form->add('select', 'field', E::ts('Contact ID Field'), $fieldSelect, true, array(
      'style' => 'min-width:250px',
      'class' => 'crm-select2 huge',
      'placeholder' => E::ts('- select -'),
    ));

    $form->add('select', 'hash_field', E::ts('Hash Field'), $fieldSelect, true, array(
      'style' => 'min-width:250px',
      'class' => 'crm-select2 huge data-processor-field-for-name',
      'placeholder' => E::ts('- select -'),
    ));

    if (isset($filter['configuration'])) {
      $configuration = $filter['configuration'];
      $defaults = array();
      if (isset($configuration['field']) && isset($configuration['datasource'])) {
        $defaults['field'] = $configuration['datasource'] . '::' . $configuration['field'];
      }
      if (isset($configuration['hash_field']) && isset($configuration['hash_datasource'])) {
        $defaults['hash_field'] = $configuration['hash_datasource'] . '::' . $configuration['hash_field'];
      }
      $form->setDefaults($defaults);
    }
  }

  /**
   * When this filter type has configuration specify the template file name
   * for the configuration form.
   *
   * @return false|string
   */
  public function getConfigurationTemplateFileName() {
    return "CRM/Dataprocessor/Form/Filter/Configuration/ChecksumFilter.tpl";
  }


  /**
   * Process the submitted values and create a configuration array
   *
   * @param $submittedValues
   * @return array
   */
  public function processConfiguration($submittedValues) {
    list($datasource, $field) = explode('::', $submittedValues['field'], 2);
    $configuration['field'] = $field;
    $configuration['datasource'] = $datasource;
    list($hash_datasource, $hash_field) = explode('::', $submittedValues['hash_field'], 2);
    $configuration['hash_field'] = $hash_field;
    $configuration['hash_datasource'] = $hash_datasource;
    return $configuration;
  }

  /**
   * @param array $filter
   *   The filter settings
   * @return mixed
   * @throws \Exception
   */
  public function setFilter($filter) {
    $this->resetFilter();
    $dataFlow  = $this->dataSource->ensureField($this->inputFieldSpecification);
    $hashDataFlow  = $this->dataSource->ensureField($this->hashInputFieldSpecification);
    if ($dataFlow && $hashDataFlow && $dataFlow instanceof SqlDataFlow && $hashDataFlow instanceof SqlDataFlow) {
      list($cs, $ts, $lf) = explode('_', $filter['value'], 3);
      $now = time();
      $tableAlias = $this->getTableAlias($dataFlow);
      $hashTableAlias = $this->getTableAlias($hashTableAlias);
      $this->whereClause = new SqlDataFlow\PureSqlStatementClause(
        "MD5(CONCAT(
          `{$hashTableAlias}`.`{$this->hashInputFieldSpecification->getName()}`,
          '_',
          `{$tableAlias}`.`{$this->inputFieldSpecification->getName()}`,
          '_{$ts}_{$lf}'
          )) = '{$cs}' AND ({$ts} + ({$lf} * 60 * 60)) >= {$now}
          "
      );
      $dataFlow->addWhereClause($this->whereClause);
    } elseif ($dataFlow && $hashDataFlow && $dataFlow instanceof InMemoryDataFlow && $hashDataFlow instanceof InMemoryDataFlow) {
      $this->filterClass = new InMemoryDataFlow\CheksumFilter($this->inputFieldSpecification->alias, $this->hashInputFieldSpecification->alias, $filter['value']);
      $this->data_processor->getDataFlow()->addFilter($this->filterClass);
    }
  }

  protected function getOperatorOptions(\Civi\DataProcessor\DataSpecification\FieldSpecification $fieldSpec) {
    return array(
      '=' => E::ts('Is equal to'),
    );
  }


}
