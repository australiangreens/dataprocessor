<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

namespace Civi\DataProcessor\FieldOutputHandler;

use Civi\DataProcessor\ProcessorType\AbstractProcessorType;
use CRM_Dataprocessor_ExtensionUtil as E;
use Civi\DataProcessor\Source\SourceInterface;
use Civi\DataProcessor\DataSpecification\FieldSpecification;
use Civi\DataProcessor\FieldOutputHandler\FieldOutput;
use Civi\DataProcessor\Exception\DataSourceNotFoundException;
use Civi\DataProcessor\Exception\FieldNotFoundException;

class ContactLinkFieldOutputHandler extends AbstractFieldOutputHandler implements OutputHandlerSortable {

  /**
   * @var \Civi\DataProcessor\Source\SourceInterface
   */
  protected $dataSource;

  /**
   * @var SourceInterface
   */
  protected $contactIdSource;

  /**
   * @var FieldSpecification
   */
  protected $contactIdField;

  /**
   * @var SourceInterface
   */
  protected $contactNameSource;

  /**
   * @var FieldSpecification
   */
  protected $contactNameField;

  /**
   * @var FieldSpecification
   */
  protected $outputFieldSpecification;

  /**
   * @return \Civi\DataProcessor\DataSpecification\FieldSpecification
   */
  public function getOutputFieldSpecification() {
    return $this->outputFieldSpecification;
  }

  /**
   * @return \Civi\DataProcessor\DataSpecification\FieldSpecification
   */
  public function getSortableInputFieldSpec() {
    return $this->contactNameField;
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
    list($this->contactIdSource, $this->contactIdField) = $this->initializeField($configuration['contact_id_field'], $configuration['contact_id_datasource'], $alias.'_contact_id');
    list($this->contactNameSource, $this->contactNameSource) = $this->initializeField($configuration['contact_name_field'], $configuration['contact_name_datasource'], $alias.'_contact_name');
    $this->outputFieldSpecification = new FieldSpecification($this->contactIdField->name, 'String', $title, null, $alias);
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
    $contactId = $rawRecord[$this->contactIdField->alias];
    $contactname = $rawRecord[$this->contactNameField->alias];
    $url = \CRM_Utils_System::url('civicrm/contact/view', array(
      'reset' => 1,
      'cid' => $contactId,
    ));
    $link = '<a href="'.$url.'">'.$contactname.'</a>';
    $formattedValue = new HTMLFieldOutput($contactId);
    $formattedValue->formattedValue = $contactname;
    $formattedValue->setHtmlOutput($link);
    return $formattedValue;
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
    $fieldSelect = \CRM_Dataprocessor_Utils_DataSourceFields::getAvailableFieldsInDataSources($field['data_processor_id']);

    $form->add('select', 'contact_id_field', E::ts('Contact ID Field'), $fieldSelect, true, array(
      'style' => 'min-width:250px',
      'class' => 'crm-select2 huge data-processor-field-for-name',
      'placeholder' => E::ts('- select -'),
    ));
    $form->add('select', 'contact_name_field', E::ts('Contact Name Field'), $fieldSelect, true, array(
      'style' => 'min-width:250px',
      'class' => 'crm-select2 huge',
      'placeholder' => E::ts('- select -'),
    ));
    if (isset($field['configuration'])) {
      $configuration = $field['configuration'];
      $defaults = array();
      if (isset($configuration['contact_id_field']) && isset($configuration['contact_id_datasource'])) {
        $defaults['contact_id_field'] = $configuration['contact_id_datasource'] . '::' . $configuration['contact_id_field'];
      }
      if (isset($configuration['contact_name_field']) && isset($configuration['contact_name_datasource'])) {
        $defaults['contact_name_field'] = $configuration['contact_name_datasource'] . '::' . $configuration['contact_name_field'];
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
    return "CRM/Dataprocessor/Form/Field/Configuration/ContactLinkFieldOutputHandler.tpl";
  }


  /**
   * Process the submitted values and create a configuration array
   *
   * @param $submittedValues
   * @return array
   */
  public function processConfiguration($submittedValues) {
    list($contact_id_datasource, $contact_id_field) = explode('::', $submittedValues['contact_id_field'], 2);
    $configuration['contact_id_field'] = $contact_id_field;
    $configuration['contact_id_datasource'] = $contact_id_datasource;
    list($contact_name_datasource, $contact_name_field) = explode('::', $submittedValues['contact_name_field'], 2);
    $configuration['contact_name_field'] = $contact_name_field;
    $configuration['contact_name_datasource'] = $contact_name_datasource;
    return $configuration;
  }




}
