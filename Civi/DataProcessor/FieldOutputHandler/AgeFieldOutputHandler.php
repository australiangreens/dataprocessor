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

class AgeFieldOutputHandler extends AbstractSimpleSortableFieldOutputHandler implements OutputHandlerAggregate {

  /**
   * @var bool
   */
  protected $isAggregateField = false;

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
    $this->inputFieldSpec->setMySqlFunction('CASE WHEN DATE(CONCAT(YEAR(CURRENT_DATE()), \'-\', MONTH(`%1`.`%2`), \'-\', DAYOFMONTH(`%1`.`%2`))) BETWEEN DATE_FORMAT(CURRENT_DATE(), \'%Y-01-01\') AND CURRENT_DATE() THEN YEAR(CURRENT_DATE()) - YEAR(`%1`.`%2`) ELSE YEAR(CURRENT_DATE()) - YEAR(`%1`.`%2`) -1 END');
    $this->isAggregateField = isset($configuration['is_aggregate']) ? $configuration['is_aggregate'] : false;
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
    $form->add('checkbox', 'is_aggregate', E::ts('Aggregate on this field'));
    if (isset($field['configuration'])) {
      $configuration = $field['configuration'];
      $defaults = array();
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
    return "CRM/Dataprocessor/Form/Field/Configuration/AgeFieldOutputHandler.tpl";
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
    $value = $rawRecord[$this->inputFieldSpec->alias];
    $output = new FieldOutput($value);
    // Check whether the value is a number.
    // When it is a number the age has been calculated in the database.
    // If not we have to calculate the age.
    if ($value !== null && is_numeric($value) && strlen($value) != 10) {
      // We check for strlen as civicrm format is yyyymmdd which is numeric but actually
      // a format.
      $output->formattedValue = $value;
    } elseif ($value !== null) {
      $today = new \DateTime();
      $date = new \DateTime($value);
      if (strlen($value) == 10 && is_numeric($value)) {
        $date = \DateTime::createFromFormat('Ymd', $value);
      }
      $age = $today->diff($date);
      $output->rawValue = $age->format('%y');
      $output->formattedValue = $output->rawValue;
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
    // Check whether the value is a number.
    // When it is a number the age has been calculated in the database.
    // If not we have to calculate the age.
    if (is_numeric($value) && strlen($value) != 10) {
      // We check for strlen as civicrm format is yyyymmdd which is numeric but actually
      // a format.
      return $value;
    } else {
      $today = new \DateTime();
      $date = new \DateTime($value);
      if (strlen($value) == 10 && is_numeric($value)) {
        $date = \DateTime::createFromFormat('Ymd', $value);
      }
      $age = $today->diff($date);
      return $age->format('%y');
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


}
