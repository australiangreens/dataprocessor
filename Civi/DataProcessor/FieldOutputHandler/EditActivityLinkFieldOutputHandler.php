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

class EditActivityLinkFieldOutputHandler extends AbstractFieldOutputHandler {

  /**
   * @var SourceInterface
   */
  protected $activityIdSource;

  /**
   * @var FieldSpecification
   */
  protected $activityIdField;

  /**
   * @var SourceInterface
   */
  protected $contactIdSource;

  /**
   * @var FieldSpecification
   */
  protected $contactIdField;

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
    list($this->activityIdSource, $this->activityIdField) = $this->initializeField($configuration['activity_id_field'], $configuration['activity_id_datasource'], $alias.'_activity_id');
    list($this->contactIdSource, $this->contactIdField) = $this->initializeField($configuration['contact_id_field'], $configuration['contact_id_datasource'], $alias.'_contact_id');
    $this->outputFieldSpecification = new FieldSpecification($alias, 'String', $title, null, $alias);
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
    $activityId = $rawRecord[$this->activityIdField->alias];
    $contactId = $rawRecord[$this->contactIdField->alias];
    $url = \CRM_Utils_System::url('civicrm/activity/add', array(
      'reset' => 1,
      'action' => 'update',
      'id' => $activityId,
      'cid' => $contactId,
    ));
    $link = '<a href="'.$url.'">'.E::ts('Edit activity').'</a>';
    $formattedValue = new HTMLFieldOutput($activityId);
    $formattedValue->formattedValue = $url;
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

    $form->add('select', 'activity_id_field', E::ts('Activity ID Field'), $fieldSelect, true, array(
      'style' => 'min-width:250px',
      'class' => 'crm-select2 huge',
      'placeholder' => E::ts('- select -'),
    ));
    $form->add('select', 'contact_id_field', E::ts('Contact ID Field'), $fieldSelect, true, array(
      'style' => 'min-width:250px',
      'class' => 'crm-select2 huge',
      'placeholder' => E::ts('- select -'),
    ));
    if (isset($field['configuration'])) {
      $configuration = $field['configuration'];
      $defaults = array();
      if (isset($configuration['activity_id_field']) && isset($configuration['activity_id_datasource'])) {
        $defaults['activity_id_field'] = \CRM_Dataprocessor_Utils_DataSourceFields::getSelectedFieldValue($field['data_processor_id'], $configuration['activity_id_datasource'], $configuration['activity_id_field']);
      }
      if (isset($configuration['contact_id_field']) && isset($configuration['contact_id_datasource'])) {
        $defaults['contact_id_field'] = \CRM_Dataprocessor_Utils_DataSourceFields::getSelectedFieldValue($field['data_processor_id'], $configuration['contact_id_datasource'], $configuration['contact_id_field']);
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
    return "CRM/Dataprocessor/Form/Field/Configuration/EditActivityLinkFieldOutputHandler.tpl";
  }


  /**
   * Process the submitted values and create a configuration array
   *
   * @param $submittedValues
   * @return array
   */
  public function processConfiguration($submittedValues) {
    list($activity_id_datasource, $activity_id_field) = explode('::', $submittedValues['activity_id_field'], 2);
    $configuration['activity_id_field'] = $activity_id_field;
    $configuration['activity_id_datasource'] = $activity_id_datasource;

    list($contact_id_datasource, $contact_id_field) = explode('::', $submittedValues['contact_id_field'], 2);
    $configuration['contact_id_field'] = $contact_id_field;
    $configuration['contact_id_datasource'] = $contact_id_datasource;

    return $configuration;
  }




}
