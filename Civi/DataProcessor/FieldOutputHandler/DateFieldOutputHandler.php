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

class DateFieldOutputHandler extends AbstractSimpleSortableFieldOutputHandler implements OutputHandlerAggregate {

  /**
   * @var bool
   */
  protected $isAggregateField = false;

  protected $function = null;

  protected $format = null;

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
    $this->format = isset($configuration['format']) ? $configuration['format'] : false;
    $function = isset($configuration['function']) ? $configuration['function'] : false;
    $availableFunctions = $this->getFunctions();
    if (isset($availableFunctions[$function])) {
      $this->function = $function;
      $this->inputFieldSpec->setMySqlFunction($availableFunctions[$function]['sql']);
    }

    if ($this->isAggregateField) {
      $dataFlow = $this->dataSource->ensureField($this->getAggregateFieldSpec());
      if ($dataFlow) {
        $dataFlow->addAggregateOutputHandler($this);
      }
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

    $dateFunctions = array();
    foreach($this->getFunctions() as $key => $function) {
      $dateFunctions[$key] = $function['title'];
    }
    $form->add('text', 'format', E::ts('Format'), array(
      'style' => 'min-width:250px',
      'class' => 'huge',
    ));
    $form->add('select', 'function', E::ts('Date function'), $dateFunctions, false, array(
      'style' => 'min-width:250px',
      'class' => 'crm-select2 huge',
      'placeholder' => E::ts('- select -'),
    ));
    $form->add('checkbox', 'is_aggregate', E::ts('Aggregate on this field'));
    if (isset($field['configuration'])) {
      $configuration = $field['configuration'];
      $defaults = array();
      if (isset($configuration['function'])) {
        $defaults['function'] = $configuration['function'];
      }
      if (isset($configuration['format'])) {
        $defaults['format'] = $configuration['format'];
      }
      if (isset($configuration['is_aggregate'])) {
        $defaults['is_aggregate'] = $configuration['is_aggregate'];
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
    return "CRM/Dataprocessor/Form/Field/Configuration/DateFieldOutputHandler.tpl";
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
    $configuration['function'] = isset($submittedValues['function']) ? $submittedValues['function'] : false;
    $configuration['format'] = isset($submittedValues['format']) ? $submittedValues['format'] : false;
    return $configuration;
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
    $rawValue = $rawRecord[$this->inputFieldSpec->alias];
    $output = new FieldOutput($rawRecord[$this->inputFieldSpec->alias]);
    if ($this->format && $rawValue) {
      $date = new \DateTime($rawValue);
      $output->formattedValue = $date->format($this->format);
    }
    return $output;
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
   * Callback function for determining whether this field could be handled by this output handler.
   *
   * @param \Civi\DataProcessor\DataSpecification\FieldSpecification $field
   * @return bool
   */
  public function isFieldValid(FieldSpecification $field) {
    switch ($field->type) {
      case 'Timestamp':
      case 'Date':
        return TRUE;
        break;
    }
    return FALSE;
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
    $availableFunctions = $this->getFunctions();
    if ($this->function && isset($availableFunctions[$this->function]) && $value) {
      $date = new \DateTime($value);
      $value = $date->format($availableFunctions[$this->function]['php_date_format']);
      if (isset($availableFunctions[$this->function]['php_add'])) {
        $value = $value + $availableFunctions[$this->function]['php_add'];
      }
    }
    return $value;
  }

  /**
   * Enable aggregation for this field.
   *
   * @return void
   */
  public function enableAggregation() {
    try {
      $dataFlow = $this->dataSource->ensureField($this->getAggregateFieldSpec());
      if ($dataFlow) {
        $dataFlow->addAggregateOutputHandler($this);
      }
    } catch (\Exception $e) {
      // Do nothing.
    }
  }

  /**
   * Disable aggregation for this field.
   *
   * @return void
   */
  public function disableAggregation() {
    try {
      $dataFlow = $this->dataSource->ensureField($this->getAggregateFieldSpec());
      if ($dataFlow) {
        $dataFlow->removeAggregateOutputHandler($this);
      }
    } catch (\Exception $e) {
      // Do nothing.
    }
  }

  protected function getFunctions() {
    return array(
      'date' => array(
        'sql' => 'DATE',
        'php_date_format' => 'Y-m-d',
        'title' => E::ts('Date'),
      ),
      'dayofmonth' => array(
        'sql' => 'DAYOFMONTH',
        'php_date_format' => 'j',
        'title' => E::ts('Day of month'),
      ),
      'dayofweek' => array(
        'sql' => 'DAYOFWEEK',
        'php_date_format' => 'N',
        'php_add' => +1, // +1 as the php date format return 0 for sunday and 6 for saturday. So add one
        'title' => E::ts('Day of week (1 = Sunday, 7 = Saturday)'),
      ),
      'week' => array(
        'sql' => 'WEEK',
        'php_date_format' => 'W',
        'title' => E::ts('Weeknumber'),
      ),
      'month' => array(
        'sql' => 'MONTH',
        'php_date_format' => 'n',
        'title' => E::ts('Month'),
      ),
      'year' => array(
        'sql' => 'YEAR',
        'php_date_format' => 'Y',
        'title' => E::ts('Year'),
      ),
    );
  }


}
