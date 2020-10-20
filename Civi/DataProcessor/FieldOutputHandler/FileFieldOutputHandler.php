<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

namespace Civi\DataProcessor\FieldOutputHandler;

use CRM_Dataprocessor_ExtensionUtil as E;
use Civi\DataProcessor\Source\SourceInterface;
use Civi\DataProcessor\DataSpecification\FieldSpecification;
use Civi\DataProcessor\FieldOutputHandler\FieldOutput;
use Civi\DataProcessor\Exception\DataSourceNotFoundException;
use Civi\DataProcessor\Exception\FieldNotFoundException;

class FileFieldOutputHandler extends AbstractFieldOutputHandler {

  /**
   * Returns the data type of this field
   *
   * @return String
   */
  protected function getType() {
    return 'String';
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
    if ($this->returnUrl) {
      $output = new FieldOutput($rawValue);
    } else {
      $output = new HTMLFieldOutput($rawValue);
    }
    if ($rawValue) {
      $attachment = civicrm_api3('Attachment', 'getsingle', array('id' => $rawValue));
      if (!isset($attachment['is_error']) || $attachment['is_error'] == '0') {
        $output->formattedValue = $attachment['url'];
        if (!$this->returnUrl) {
          $output->setHtmlOutput('<a href="' . $attachment['url'] . '">' . $attachment['name'] . '</a>');
        }
      }
    }
    return $output;
  }

  /**
   * Callback function for determining whether this field could be handled by this output handler.
   *
   * @param \Civi\DataProcessor\DataSpecification\FieldSpecification $field
   * @return bool
   */
  public function isFieldValid(FieldSpecification $field) {
    if ($field->type == 'File') {
      return true;
    }
    return false;
  }

  /**
   * @var \Civi\DataProcessor\DataSpecification\FieldSpecification
   */
  protected $inputFieldSpec;

  /**
   * @var \Civi\DataProcessor\DataSpecification\FieldSpecification
   */
  protected $outputFieldSpec;

  /**
   * @var SourceInterface
   */
  protected $dataSource;

  /**
   * @var bool
   */
  protected $returnUrl = false;

  /**
   * @return \Civi\DataProcessor\DataSpecification\FieldSpecification
   */
  public function getOutputFieldSpecification() {
    return $this->outputFieldSpec;
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
    list($this->dataSource, $this->inputFieldSpec) = $this->initializeField($configuration['field'], $configuration['datasource'], $alias);

    $this->outputFieldSpec = clone $this->inputFieldSpec;
    $this->outputFieldSpec->alias = $alias;
    $this->outputFieldSpec->title = $title;

    $this->returnUrl = isset($configuration['return_url']) ? $configuration['return_url'] : false;
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

    $form->add('select', 'field', E::ts('Field'), $fieldSelect, true, array(
      'style' => 'min-width:250px',
      'class' => 'crm-select2 huge data-processor-field-for-name',
      'placeholder' => E::ts('- select -'),
    ));
    $form->add('checkbox', 'return_url', E::ts('Only return URL'));
    if (isset($field['configuration'])) {
      $configuration = $field['configuration'];
      $defaults = array();
      if (isset($configuration['return_url'])) {
        $defaults['return_url'] = $configuration['return_url'];
      }
      if (isset($configuration['field']) && isset($configuration['datasource'])) {
        $defaults['field'] = $configuration['datasource'] . '::' . $configuration['field'];
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
    return "CRM/Dataprocessor/Form/Field/Configuration/FileDownloadLinkOutputHandler.tpl";
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
    $configuration['return_url'] = isset($submittedValues['return_url']) ? $submittedValues['return_url'] : false;
    $configuration['datasource'] = $datasource;
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


}
