<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

namespace Civi\DataProcessor\FieldOutputHandler\Calculations;

use Civi\DataProcessor\DataSpecification\FieldSpecification;
use Civi\DataProcessor\Exception\DataSourceNotFoundException;
use Civi\DataProcessor\Exception\FieldNotFoundException;
use Civi\DataProcessor\FieldOutputHandler\AbstractFieldOutputHandler;
use Civi\DataProcessor\FieldOutputHandler\FieldOutput;

use CRM_Dataprocessor_ExtensionUtil as E;

class SubtractFieldOutputHandler extends AbstractFieldOutputHandler {

  /**
   * @var \Civi\DataProcessor\DataSpecification\FieldSpecification
   */
  protected $outputFieldSpec;

  /**
   * @var \Civi\DataProcessor\DataSpecification\FieldSpecification[]
   */
  protected $inputFieldSpec1 = array();

  /**
   * @var \Civi\DataProcessor\DataSpecification\FieldSpecification[]
   */
  protected $inputFieldSpec2 = array();

  protected $prefix = '';

  protected $suffix = '';

  protected $number_of_decimals = '';

  protected $decimal_sep = '';

  protected $thousand_sep = '';

  /**
   * @return \Civi\DataProcessor\DataSpecification\FieldSpecification
   */
  public function getOutputFieldSpecification() {
    return $this->outputFieldSpec;
  }

  /**
   * Returns the data type of this field
   *
   * @return String
   */
  protected function getType() {
    return 'String';
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
    list($datasourceName1, $field1) = explode('::', $configuration['field1'], 2);
    list($dataSource1, $this->inputFieldSpec1) = $this->initializeField($field1, $datasourceName1, $alias.'_1');
    list($datasourceName2, $field2) = explode('::', $configuration['field2'], 2);
    list($dataSource2, $this->inputFieldSpec2) = $this->initializeField($field2, $datasourceName2, $alias.'_2');

    $this->outputFieldSpec = new FieldSpecification($alias, 'Float', $title, null, $alias);

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
   * Returns true when this handler has additional configuration.
   *
   * @return bool
   */
  public function hasConfiguration() {
    return true;
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

    $form->add('select', 'field1', E::ts('Field 1'), $fieldSelect, true, array(
      'style' => 'min-width:250px',
      'class' => 'crm-select2 huge data-processor-field-for-name',
      'placeholder' => E::ts('- select -'),
      'multiple' => false,
    ));
    $form->add('select', 'field2', E::ts('Field 2'), $fieldSelect, true, array(
      'style' => 'min-width:250px',
      'class' => 'crm-select2 huge data-processor-field-for-name',
      'placeholder' => E::ts('- select -'),
      'multiple' => false,
    ));
    $form->add('text', 'number_of_decimals', E::ts('Number of decimals'), false);
    $form->add('text', 'decimal_separator', E::ts('Decimal separator'), false);
    $form->add('text', 'thousand_separator', E::ts('Thousand separator'), false);
    $form->add('text', 'prefix', E::ts('Prefix (e.g. &euro;)'), false);
    $form->add('text', 'suffix', E::ts('Suffix (e.g. $)'), false);
    if (isset($field['configuration'])) {
      $configuration = $field['configuration'];
      $defaults = array();
      if (isset($configuration['field1'])) {
        $defaults['field1'] = $configuration['field1'];
      }
      if (isset($configuration['field2'])) {
        $defaults['field2'] = $configuration['field2'];
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
    return "CRM/Dataprocessor/Form/Field/Configuration/CalculationSubtractFieldOutputHandler.tpl";
  }


  /**
   * Process the submitted values and create a configuration array
   *
   * @param $submittedValues
   * @return array
   */
  public function processConfiguration($submittedValues) {
    $configuration['field1'] = $submittedValues['field1'];
    $configuration['field2'] = $submittedValues['field2'];
    $configuration['number_of_decimals'] = $submittedValues['number_of_decimals'];
    $configuration['decimal_separator'] = $submittedValues['decimal_separator'];
    $configuration['thousand_separator'] = $submittedValues['thousand_separator'];
    $configuration['prefix'] = $submittedValues['prefix'];
    $configuration['suffix'] = $submittedValues['suffix'];
    return $configuration;
  }

  /**
   * Returns all possible fields
   *
   * @param $data_processor_id
   *
   * @return array
   * @throws \Exception
   */
  protected function getFieldOptions($data_processor_id) {
    $fieldSelect = \CRM_Dataprocessor_Utils_DataSourceFields::getAvailableFieldsInDataSources($data_processor_id, array($this, 'isFieldValid'));
    return $fieldSelect;
  }

  /**
   * Callback function for determining whether this field could be handled by this output handler.
   *
   * @param \Civi\DataProcessor\DataSpecification\FieldSpecification $field
   * @return bool
   */
  public function isFieldValid(FieldSpecification $field) {
    return true;
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
    $values = array();
    $values[] = $rawRecord[$this->inputFieldSpec1->alias];
    $values[] = $rawRecord[$this->inputFieldSpec2->alias];
    $value = $this->doCalculation($values);
    $formattedValue = $value;
    if (is_numeric($this->number_of_decimals) && $value != null) {
      $formattedValue = number_format($value, $this->number_of_decimals, $this->decimal_sep, $this->thousand_sep);
    }
    if ($formattedValue != null) {
      $formattedValue = $this->prefix . $formattedValue . $this->suffix;
    }
    $output = new FieldOutput($value);
    $output->formattedValue = $formattedValue;
    return $output;
  }


  /**
   * @param array $values
   * @return int|float
   */
  protected function doCalculation($values) {
    $value = 0;
    $i =0;
    foreach($values as $v) {
      if ($v === null) {
        return null;
      }
      if ($i == 0) {
        $value = $v;
      } else {
        $value = $value - $v;
      }
      $i++;
    }
    return $value;
  }

}
