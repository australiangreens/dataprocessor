<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

namespace Civi\DataProcessor\FilterHandler;

use Civi\DataProcessor\DataFlow\CombinedDataFlow\CombinedSqlDataFlow;
use Civi\DataProcessor\DataFlow\InMemoryDataFlow;
use Civi\DataProcessor\DataFlow\SqlDataFlow;
use Civi\DataProcessor\DataFlow\SqlTableDataFlow;
use Civi\DataProcessor\DataSpecification\CustomFieldSpecification;
use Civi\DataProcessor\Exception\InvalidConfigurationException;
use Civi\DataProcessor\Source\AbstractCivicrmEntitySource;
use CRM_Dataprocessor_ExtensionUtil as E;

class SimpleSqlFilter extends AbstractFieldFilterHandler {

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
  }

  /**
   * @param array $filter
   *   The filter settings
   * @return mixed
   */
  public function setFilter($filter) {
    $this->resetFilter();
    $dataFlow  = $this->dataSource->ensureField($this->inputFieldSpecification);
    if ($dataFlow && $dataFlow instanceof SqlDataFlow) {
      $tableAlias = $this->getTableAlias($dataFlow);
      $fieldName = $this->inputFieldSpecification->getName();
      // special handling for activity contact fields added to filter
      if (stripos($fieldName, 'activity_contact_') === 0) {
        $tableAlias = '_activity_contact';
        $fieldName = substr($fieldName, 17);
      }
      if ($this->isMultiValueField()) {
        $this->whereClause = new SqlDataFlow\MultiValueFieldWhereClause($tableAlias, $fieldName, $filter['op'], $filter['value'], $this->inputFieldSpecification->type);
      } else {
        $this->whereClause = new SqlDataFlow\SimpleWhereClause($tableAlias, $fieldName, $filter['op'], $filter['value'], $this->inputFieldSpecification->type);
      }
      $dataFlow->addWhereClause($this->whereClause);
    } elseif ($dataFlow && $dataFlow instanceof InMemoryDataFlow && !$this->isMultiValueField()) {
      $this->filterClass = new InMemoryDataFlow\SimpleFilter($this->inputFieldSpecification->getName(), $filter['op'], $filter['value']);
      $dataFlow->addFilter($this->filterClass);
    }
  }

  /**
   * @return bool
   */
  protected function isMultiValueField() {
    if ($this->fieldSpecification instanceof CustomFieldSpecification) {
      if ($this->fieldSpecification->isMultiple()) {
        return TRUE;
      }
    }
    return false;
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

    $form->add('select', 'field', E::ts('Field'), $fieldSelect, true, array(
      'style' => 'min-width:250px',
      'class' => 'crm-select2 huge data-processor-field-for-name',
      'placeholder' => E::ts('- select -'),
    ));
    if (isset($filter['configuration'])) {
      $configuration = $filter['configuration'];
      if (isset($configuration['field']) && isset($configuration['datasource'])) {
        $defaults['field'] = $configuration['datasource'] . '::' . $configuration['field'];
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
    return "CRM/Dataprocessor/Form/Filter/Configuration/SimpleSqlFilter.tpl";
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
    return $configuration;
  }


}
