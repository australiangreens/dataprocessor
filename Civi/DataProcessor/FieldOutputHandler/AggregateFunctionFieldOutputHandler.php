<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

namespace Civi\DataProcessor\FieldOutputHandler;

use Civi\DataProcessor\DataSpecification\AggregateFunctionFieldSpecification;
use Civi\DataProcessor\DataSpecification\FieldSpecification;
use Civi\DataProcessor\Exception\DataSourceNotFoundException;
use Civi\DataProcessor\Exception\FieldNotFoundException;
use Civi\DataProcessor\FieldOutputHandler\FieldOutput;
use Civi\DataProcessor\FieldOutputHandler\RawFieldOutputHandler;
use CRM_Dataprocessor_ExtensionUtil as E;

class AggregateFunctionFieldOutputHandler extends AbstractSimpleFieldOutputHandler {

  /**
   * @var \Civi\DataProcessor\DataSpecification\FieldSpecification
   */
  protected $aggregateField;

  protected $prefix = '';

  protected $suffix = '';

  protected $number_of_decimals = '';

  protected $decimal_sep = '';

  protected $thousand_sep = '';

  /**
   * @return \Civi\DataProcessor\DataSpecification\FieldSpecification
   */
  public function getSortableInputFieldSpec() {
    return $this->aggregateField;
  }

  /**
   * Returns the data type of this field
   *
   * @return String
   */
  protected function getType() {
    return $this->inputFieldSpec->type;
  }

  /**
   * Returns the formatted value
   *
   * @param $rawRecord
   * @param $formattedRecord
   *
   * @return \Civi\DataProcessor\FieldOutputHandler\FieldOutput
   */
  public function formatField($rawRecord, $formattedRecord) {
    $value = $rawRecord[$this->aggregateField->alias];

    $formattedValue = $value;
    if (is_numeric($this->number_of_decimals) && $value != null) {
      $formattedValue = number_format($value, $this->number_of_decimals, $this->decimal_sep, $this->thousand_sep);
    } elseif ($this->inputFieldSpec->type == 'Money') {
      $formattedValue = \CRM_Utils_Money::format($value);
    }
    if ($formattedValue != null) {
      $formattedValue = $this->prefix . $formattedValue . $this->suffix;
    }

    $output = new FieldOutput($rawRecord[$this->aggregateField->alias]);
    $output->formattedValue = $formattedValue;
    return $output;
  }

  /**
   * Initialize the processor
   *
   * @param String $alias
   * @param String $title
   * @param array $configuration
   * @param \Civi\DataProcessor\ProcessorType\AbstractProcessorType $processorType
   */
  public function initialize($alias, $title, $configuration) {
    $this->dataSource = $this->dataProcessor->getDataSourceByName($configuration['datasource']);
    if (!$this->dataSource) {
      throw new DataSourceNotFoundException(E::ts("Field %1 requires data source '%2' which could not be found. Did you rename or deleted the data source?", array(1=>$alias, 2=>$configuration['datasource'])));
    }

    $this->inputFieldSpec = $this->dataSource->getAvailableFields()->getFieldSpecificationByAlias($configuration['field']);
    if (!$this->inputFieldSpec) {
      $this->inputFieldSpec = $this->dataSource->getAvailableFields()->getFieldSpecificationByName($configuration['field']);
    }
    if (!$this->inputFieldSpec) {
      throw new FieldNotFoundException(E::ts("Field %1 requires a field with the name '%2' in the data source '%3'. Did you change the data source type?", array(
        1 => $alias,
        2 => $configuration['field'],
        3 => $configuration['datasource']
      )));
    }
    $this->dataSource->ensureField($this->inputFieldSpec);

    $this->aggregateField = AggregateFunctionFieldSpecification::convertFromFieldSpecification($this->inputFieldSpec, $configuration['function']);
    $this->aggregateField->alias = $alias;
    $this->dataSource->ensureFieldInSource($this->aggregateField);

    $this->outputFieldSpec = clone $this->inputFieldSpec;
    $this->outputFieldSpec->alias = $alias;
    $this->outputFieldSpec->title = $title;
    $this->outputFieldSpec->type = 'String';

    if (isset($configuration['number_of_decimals'])) {
      $this->number_of_decimals = $configuration['number_of_decimals'];
    }
    if (isset($configuration['decimal_separator'])) {
      $this->decimal_sep = $configuration['decimal_separator'];
    }
    if (isset($configuration['thousand_separator'])) {
      $this->thousand_sep = $configuration['thousand_separator'];
    }
    if (isset($configuration['prefix'])) {
      $this->prefix = $configuration['prefix'];
    }
    if (isset($configuration['suffix'])) {
      $this->suffix = $configuration['suffix'];
    }
  }

  /**
   * When this handler has additional configuration you can add
   * the fields on the form with this function.
   *
   * @param \CRM_Core_Form $form
   * @param array $field
   */
  public function buildConfigurationForm(\CRM_Core_Form $form, $field=array()) {
    $fieldSelect = $this->getFieldOptions($field['data_processor_id']);

    $form->add('select', 'field', E::ts('Field'), $fieldSelect, true, array(
      'style' => 'min-width:250px',
      'class' => 'crm-select2 huge data-processor-field-for-name',
      'placeholder' => E::ts('- select -'),
    ));
    $form->add('select', 'function', E::ts('Function'), AggregateFunctionFieldSpecification::functionList(), true, array(
      'style' => 'min-width:250px',
      'class' => 'crm-select2 huge',
      'placeholder' => E::ts('- select -'),
    ));

    $form->add('text', 'number_of_decimals', E::ts('Number of decimals'), false);
    $form->add('text', 'decimal_separator', E::ts('Decimal separator'), false);
    $form->add('text', 'thousand_separator', E::ts('Thousand separator'), false);
    $form->add('text', 'prefix', E::ts('Prefix (e.g. &euro;)'), false);
    $form->add('text', 'suffix', E::ts('Suffix (e.g. $)'), false);

    if (isset($field['configuration'])) {
      $configuration = $field['configuration'];
      $defaults = array();
      if (isset($configuration['field']) && isset($configuration['datasource'])) {
        $defaults['field'] = $configuration['datasource'] . '::' . $configuration['field'];
      }
      if (isset($configuration['function'])) {
        $defaults['function'] = $configuration['function'];
      }
      if (isset($configuration['number_of_decimals'])) {
        $defaults['number_of_decimals'] = $configuration['number_of_decimals'];
      }
      if (isset($configuration['decimal_separator'])) {
        $defaults['decimal_separator'] = $configuration['decimal_separator'];
      }
      if (isset($configuration['thousand_separator'])) {
        $defaults['thousand_separator'] = $configuration['thousand_separator'];
      }
      if (isset($configuration['prefix'])) {
        $defaults['prefix'] = $configuration['prefix'];
      }
      if (isset($configuration['suffix'])) {
        $defaults['suffix'] = $configuration['suffix'];
      }
      $form->setDefaults($defaults);
    }
  }

  /**
   * When this handler has configuration specify the template file name
   * for the configuration form.
   *
   * @return false|string
   */
  public function getConfigurationTemplateFileName() {
    return "CRM/Dataprocessor/Form/Field/Configuration/AggregateFunctionFieldOutputHandler.tpl";
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
    $configuration['function'] = $submittedValues['function'];
    $configuration['number_of_decimals'] = $submittedValues['number_of_decimals'];
    $configuration['decimal_separator'] = $submittedValues['decimal_separator'];
    $configuration['thousand_separator'] = $submittedValues['thousand_separator'];
    $configuration['prefix'] = $submittedValues['prefix'];
    $configuration['suffix'] = $submittedValues['suffix'];
    return $configuration;
  }

  /**
   * Callback function for determining whether this field could be handled by this output handler.
   *
   * @param \Civi\DataProcessor\DataSpecification\FieldSpecification $field
   * @return bool
   */
  public function isFieldValid(FieldSpecification $field) {
    switch ($field->type){
      case 'Int':
      case 'Integer':
      case 'Float':
      case 'Money':
        return true;
        break;
    }
    return false;
  }

}
