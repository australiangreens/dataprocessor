<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

namespace Civi\DataProcessor\FieldOutputHandler\Calculations;

use Civi\DataProcessor\DataSpecification\FieldSpecification;
use Civi\DataProcessor\FieldOutputHandler\AbstractFieldOutputHandler;
use Civi\DataProcessor\FieldOutputHandler\FieldOutput;
use Civi\DataProcessor\Exception\DataSourceNotFoundException;
use Civi\DataProcessor\Exception\FieldNotFoundException;
use CRM_Dataprocessor_ExtensionUtil as E;

abstract class CalculationFieldOutputHandler extends AbstractFieldOutputHandler {

  /**
   * @var \Civi\DataProcessor\DataSpecification\FieldSpecification
   */
  protected $outputFieldSpec;

  /**
   * @var \Civi\DataProcessor\DataSpecification\FieldSpecification[]
   */
  protected $inputFieldSpecs = array();

  protected $prefix = '';

  protected $suffix = '';

  protected $number_of_decimals = '';

  protected $decimal_sep = '';

  protected $thousand_sep = '';

  /**
   * @param array $rawRecord,
   * @param array $formattedRecord
   * @return int|float
   */
  abstract protected function doCalculation($rawRecord, $formattedRecord);

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
    if (isset($configuration['fields']) && !isset($configuration['fields_0'])) {
      $configuration['fields_0'] = $configuration['fields'];
    }
    $fieldSelectConfigurations = $this->getFieldSelectConfigurations();
    for($i=0; $i<count($fieldSelectConfigurations); $i++) {
      if (is_array($configuration['fields_'.$i])) {
        $j = 0;
        foreach($configuration['fields_'.$i] as $fieldAndDataSource) {
          list($datasourceName, $field) = explode('::', $fieldAndDataSource, 2);
          list($dataSource, $inputFieldSpec) = $this->initializeField($field, $datasourceName, $alias.'_'.$i.'_'.$j);
          $this->inputFieldSpecs[$i][] = $inputFieldSpec;
          $j++;
        }
      } else {
        list($datasourceName, $field) = explode('::', $configuration['fields_' . $i], 2);
        list($dataSource, $inputFieldSpec) = $this->initializeField($field, $datasourceName, $alias.'_'.$i);
        $this->inputFieldSpecs[$i] = $inputFieldSpec;
      }
    }

    $this->outputFieldSpec = new FieldSpecification($alias, 'String', $title, null, $alias);

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

    $fieldSelectConfigurations = $this->getFieldSelectConfigurations();
    $fieldSelects = [];
    for($i=0; $i<count($fieldSelectConfigurations); $i++) {
      $form->add('select', 'fields_'.$i, $fieldSelectConfigurations[$i]['title'], $fieldSelect, true, array(
        'style' => 'min-width:250px',
        'class' => 'crm-select2 huge data-processor-field-for-name',
        'placeholder' => E::ts('- select -'),
        'multiple' => $fieldSelectConfigurations[$i]['multiple'],
      ));
      $fieldSelects[] = 'fields_'.$i;
    }
    $form->assign('fieldSelects', $fieldSelects);

    $form->add('text', 'number_of_decimals', E::ts('Number of decimals'), false);
    $form->add('text', 'decimal_separator', E::ts('Decimal separator'), false);
    $form->add('text', 'thousand_separator', E::ts('Thousand separator'), false);
    $form->add('text', 'prefix', E::ts('Prefix (e.g. &euro;)'), false);
    $form->add('text', 'suffix', E::ts('Suffix (e.g. $)'), false);
    if (isset($field['configuration'])) {
      $configuration = $field['configuration'];
      $defaults = array();

      if (isset($configuration['fields'])) {
        // Backwards compatibility.
        $defaults['fields_0'] = $configuration['fields'];
      }
      for($i=0; $i<count($fieldSelectConfigurations); $i++) {
        if (isset($configuration['fields_'.$i])) {
          $defaults['fields_'.$i] = $configuration['fields_'.$i];
        }
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

  protected function getFieldSelectConfigurations() {
    return array(
      ['title' => E::ts('Fields'), 'multiple' => true],
    );
  }

  /**
   * When this handler has configuration specify the template file name
   * for the configuration form.
   *
   * @return false|string
   */
  public function getConfigurationTemplateFileName() {
    return "CRM/Dataprocessor/Form/Field/Configuration/CalculationFieldOutputHandler.tpl";
  }


  /**
   * Process the submitted values and create a configuration array
   *
   * @param $submittedValues
   * @return array
   */
  public function processConfiguration($submittedValues) {
    $fieldSelectConfigurations = $this->getFieldSelectConfigurations();
    for($i=0; $i<count($fieldSelectConfigurations); $i++) {
      $configuration['fields_'.$i] = $submittedValues['fields_'.$i];
    }
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
    $value = $this->doCalculation($rawRecord, $formattedRecord);
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

}
