<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

namespace Civi\DataProcessor\FieldOutputHandler;

use Civi\DataProcessor\DataSpecification\FieldSpecification;
use Civi\DataProcessor\Exception\DataSourceNotFoundException;
use Civi\DataProcessor\Exception\FieldNotFoundException;
use Civi\DataProcessor\ProcessorType\AbstractProcessorType;

use CRM_Dataprocessor_ExtensionUtil as E;

abstract class AbstractFieldOutputHandler {

  /**
   * @var AbstractProcessorType
   */
  protected $dataProcessor;

  /**
   * Returns the data type of this field
   *
   * @return String
   */
  abstract protected function getType();

  /**
   * Returns the formatted value
   *
   * @param $rawRecord
   * @param $formattedRecord
   *
   * @return \Civi\DataProcessor\FieldOutputHandler\FieldOutput
   */
  abstract public function formatField($rawRecord, $formattedRecord);

  /**
   * @return \Civi\DataProcessor\DataSpecification\FieldSpecification
   */
  abstract public function getOutputFieldSpecification();

  /**
   * AbstractFieldOutputHandler constructor.
   */
  public function __construct() {
  }

  /**
   * @param \Civi\DataProcessor\ProcessorType\AbstractProcessorType $dataProcessor
   */
  public function setDataProcessor(AbstractProcessorType $dataProcessor) {
    $this->dataProcessor = $dataProcessor;
  }

  /**
   * @return \Civi\DataProcessor\ProcessorType\AbstractProcessorType
   */
  public function getDataProcessor() {
    return $this->dataProcessor;
  }

  /**
   * Initialize the processor
   *
   * @param String $alias
   * @param String $title
   * @param array $configuration
   */
  public function initialize($alias, $title, $configuration) {
    // Override this in child classes.
    //$this->outputFieldSpecification->title = $title;
    //$this->outputFieldSpecification->alias = $alias;
  }

  /**
   * Initialize a field.
   * Returns the datasource and the field specification
   * The new alias is set as alias in the field.
   *
   * @param $fieldAlias
   * @param $datasourceName
   * @param $newAlias
   *
   * @return array
   * @throws \Civi\DataProcessor\Exception\DataSourceNotFoundException
   * @throws \Civi\DataProcessor\Exception\FieldNotFoundException
   */
  protected function initializeField($fieldAlias, $datasourceName, $newAlias) {
    $dataSource = $this->dataProcessor->getDataSourceByName($datasourceName);
    if (!$dataSource) {
      throw new DataSourceNotFoundException(E::ts("Field %1 requires data source '%2' which could not be found. Did you rename or deleted the data source?", array(1=>$newAlias, 2=>$datasourceName)));
    }
    $inputFieldSpec = $dataSource->getAvailableFields()->getFieldSpecificationByAlias($fieldAlias);
    if (!$inputFieldSpec) {
      $inputFieldSpec = $dataSource->getAvailableFields()->getFieldSpecificationByName($fieldAlias);
    }
    if (!$inputFieldSpec) {
      throw new FieldNotFoundException(E::ts("Field %1 requires a field with the name '%2' in the data source '%3'. Did you change the data source type?", array(
        1 => $newAlias,
        2 => $fieldAlias,
        3 => $datasourceName
      )));
    }

    $inputFieldSpec = clone $inputFieldSpec;
    $inputFieldSpec->alias = $newAlias;
    $dataSource->ensureFieldInSource($inputFieldSpec);
    return [$dataSource, $inputFieldSpec];
  }

  /**
   * Returns true when this handler has additional configuration.
   *
   * @return bool
   */
  public function hasConfiguration() {
    return false;
  }

  /**
   * When this handler has additional configuration you can add
   * the fields on the form with this function.
   *
   * @param \CRM_Core_Form $form
   * @param array $field
   */
  public function buildConfigurationForm(\CRM_Core_Form $form, $field=array()) {
    // Example add a checkbox to the form.
    // $form->add('checkbox', 'show_label', E::ts('Show label'));
  }

  /**
   * When this handler has configuration specify the template file name
   * for the configuration form.
   *
   * @return false|string
   */
  public function getConfigurationTemplateFileName() {
    // Example return "CRM/FormFieldLibrary/Form/FieldConfiguration/TextField.tpl";
    return false;
  }


  /**
   * Process the submitted values and create a configuration array
   *
   * @param $submittedValues
   * @return array
   */
  public function processConfiguration($submittedValues) {
    // Add the show_label to the configuration array.
    // $configuration['show_label'] = $submittedValues['show_label'];
    // return $configuration;
    return array();
  }


}
