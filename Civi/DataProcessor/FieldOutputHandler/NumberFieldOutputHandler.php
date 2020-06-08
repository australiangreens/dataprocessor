<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

namespace Civi\DataProcessor\FieldOutputHandler;

use Civi\DataProcessor\Exception\DataSourceNotFoundException;
use Civi\DataProcessor\Exception\FieldNotFoundException;
use CRM_Dataprocessor_ExtensionUtil as E;
use Civi\DataProcessor\Source\SourceInterface;
use Civi\DataProcessor\DataSpecification\FieldSpecification;

class NumberFieldOutputHandler extends AbstractSimpleFieldOutputHandler implements OutputHandlerAggregate {

  /**
   * @var bool
   */
  protected $isAggregateField = false;

  /**
   * Returns the formatted value
   *
   * @param $rawRecord
   * @param $formattedRecord
   *
   * @return \Civi\DataProcessor\FieldOutputHandler\FieldOutput
   */
  public function formatField($rawRecord, $formattedRecord) {
    $value = $rawRecord[$this->inputFieldSpec->alias];

    $formattedValue = $value;
    if (is_numeric($this->number_of_decimals) && $value != null) {
      $formattedValue = number_format($value, $this->number_of_decimals, $this->decimal_sep, $this->thousand_sep);
    } elseif ($this->inputFieldSpec->type == 'Money') {
      $formattedValue = \CRM_Utils_Money::format($value);
    }
    if ($formattedValue != null) {
      $formattedValue = $this->prefix . $formattedValue . $this->suffix;
    }

    $output = new FieldOutput($rawRecord[$this->inputFieldSpec->alias]);
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
    parent::initialize($alias, $title, $configuration);
    $this->isAggregateField = isset($configuration['is_aggregate']) ? $configuration['is_aggregate'] : false;

    if ($this->isAggregateField) {
      $dataFlow = $this->dataSource->ensureField($this->getAggregateFieldSpec());
      if ($dataFlow) {
        $dataFlow->addAggregateOutputHandler($this);
      }
    }

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
   * Enable aggregation for this field.
   *
   * @return void
   */
  public function enableAggregation() {
    $dataFlow = $this->dataSource->ensureField($this->getAggregateFieldSpec());
    if ($dataFlow) {
      $dataFlow->addAggregateOutputHandler($this);
    }
  }

  /**
   * Disable aggregation for this field.
   *
   * @return void
   */
  public function disableAggregation() {
    $dataFlow = $this->dataSource->ensureField($this->getAggregateFieldSpec());
    if ($dataFlow) {
      $dataFlow->removeAggregateOutputHandler($this);
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
    parent::buildConfigurationForm($form, $field);
    $form->add('checkbox', 'is_aggregate', E::ts('Aggregate on this field'));

    $form->add('text', 'number_of_decimals', E::ts('Number of decimals'), false);
    $form->add('text', 'decimal_separator', E::ts('Decimal separator'), false);
    $form->add('text', 'thousand_separator', E::ts('Thousand separator'), false);
    $form->add('text', 'prefix', E::ts('Prefix (e.g. &euro;)'), false);
    $form->add('text', 'suffix', E::ts('Suffix (e.g. $)'), false);

    if (isset($field['configuration'])) {
      $configuration = $field['configuration'];
      $defaults = array();
      if (isset($configuration['is_aggregate'])) {
        $defaults['is_aggregate'] = $configuration['is_aggregate'];
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
    return "CRM/Dataprocessor/Form/Field/Configuration/NumberFieldOutputHandler.tpl";
  }


  /**
   * Process the submitted values and create a configuration array
   *
   * @param $submittedValues
   * @return array
   */
  public function processConfiguration($submittedValues) {
    $configuration = parent::processConfiguration($submittedValues);
    $configuration['is_aggregate'] = isset($submittedValues['is_aggregate']) ? $submittedValues['is_aggregate'] : false;
    $configuration['number_of_decimals'] = $submittedValues['number_of_decimals'];
    $configuration['decimal_separator'] = $submittedValues['decimal_separator'];
    $configuration['thousand_separator'] = $submittedValues['thousand_separator'];
    $configuration['prefix'] = $submittedValues['prefix'];
    $configuration['suffix'] = $submittedValues['suffix'];
    return $configuration;
  }

  /**
   * @return \Civi\DataProcessor\DataSpecification\FieldSpecification
   */
  public function getAggregateFieldSpec() {
    return $this->inputFieldSpec;
  }

  /**
   * @return bool
   */
  public function isAggregateField() {
    return $this->isAggregateField;
  }

  /**
   * Returns the value. And if needed a formatting could be applied.
   * E.g. when the value is a date field and you want to aggregate on the month
   * you can then return the month here.
   *
   * @param $value
   * @return mixed
   */
  public function formatAggregationValue($value) {
    return $value;
  }

  /**
   * Callback function for determining whether this field could be handled by this output handler.
   *
   * @param \Civi\DataProcessor\DataSpecification\FieldSpecification $field
   * @return bool
   */
  public function isFieldValid(FieldSpecification $field) {
    switch ($field->type) {
      case 'Int':
      case 'Float':
      case 'Money':
        return true;
        break;
    }
    return false;
  }


}
