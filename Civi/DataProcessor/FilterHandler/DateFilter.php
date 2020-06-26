<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

namespace Civi\DataProcessor\FilterHandler;

use Civi\DataProcessor\DataFlow\SqlDataFlow;
use Civi\DataProcessor\DataSpecification\CustomFieldSpecification;
use Civi\DataProcessor\DataSpecification\FieldSpecification;
use Civi\DataProcessor\Exception\InvalidConfigurationException;
use CRM_Dataprocessor_ExtensionUtil as E;

class DateFilter extends AbstractFieldFilterHandler {

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
    $this->fieldSpecification->type = 'Int';
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
      $value = null;
      if (isset($filter['value']) && is_array($filter['value'])) {
        $value = array();
        foreach($filter['value'] as $v) {
          $dateTime = new \DateTime($v);
          $value[] = $dateTime->format('Y-m-d');
        }
      } elseif (isset($filter['value'])) {
        $dateTime = new \DateTime($filter['value']);
        $value = $dateTime->format('Y-m-d');
      }

      $this->whereClause = new SqlDataFlow\SimpleWhereClause($dataFlow->getName(), $this->inputFieldSpecification->name, $filter['op'], $value, 'String');
      $dataFlow->addWhereClause($this->whereClause);
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
    $fieldSelect = \CRM_Dataprocessor_Utils_DataSourceFields::getAvailableFilterFieldsInDataSources($filter['data_processor_id'], [self::class, 'filterDateFields']);

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
    return "CRM/Dataprocessor/Form/Filter/Configuration/DateFilter.tpl";
  }

  /**
   * File name of the template to add this filter to the criteria form.
   *
   * @return string
   */
  public function getTemplateFileName() {
    return "CRM/Dataprocessor/Form/Filter/DateFilter.tpl";
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

  protected function getOperatorOptions(FieldSpecification $fieldSpec) {
    return array(
      '=' => E::ts('Is equal to'),
      '<=' => E::ts('Is less than or equal to'),
      '>=' => E::ts('Is greater than or equal to'),
      '<' => E::ts('Is less than'),
      '>' => E::ts('Is greater than'),
      '!=' => E::ts('Is not equal to'),
      'null' => E::ts('Is empty'),
      'not null' => E::ts('Is not empty'),
      'bw' => E::ts('Is between'),
      'nbw' => E::ts('Is not between'),
    );
  }

  /**
   * Filters the date fields
   *
   * @param \Civi\DataProcessor\DataSpecification\FieldSpecification $field
   * @return bool
   */
  public static function filterDateFields(FieldSpecification $field) {
    if ($field->type == 'Date' || $field->type == 'Timestamp') {
      return true;
    }
    return false;
  }

}
