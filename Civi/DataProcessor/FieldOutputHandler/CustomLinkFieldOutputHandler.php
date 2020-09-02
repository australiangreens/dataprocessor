<?php
/**
 * @author Klaas Eikelboom <klaas.eikelboom@civicoop.org>
 * @license AGPL-3.0
 */

namespace Civi\DataProcessor\FieldOutputHandler;

use CRM_Dataprocessor_ExtensionUtil as E;
use Civi\DataProcessor\Source\SourceInterface;
use Civi\DataProcessor\DataSpecification\FieldSpecification;

class CustomLinkFieldOutputHandler extends AbstractFieldOutputHandler {

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
  protected $linkField;

  /**
   * @var SourceInterface
   */
  protected $linkFieldSource;

  protected $linkTemplate;

  protected $linkText;

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
    list($this->linkFieldSource, $this->linkField) = $this->initializeField($configuration['link_field'], $configuration['link_field_datasource'], $alias.'_link_field');
    if (isset($configuration['link_template'])) {
      $this->linkTemplate = $configuration['link_template'];
    }
    if (isset($configuration['link_text'])) {
      $this->linkText = $configuration['link_text'];
    }
    $this->outputFieldSpecification = new FieldSpecification($this->linkField->name, 'String', $title, null, $alias);
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

    $linkField = $rawRecord[$this->linkField->alias];
    $url = str_replace('%1',$linkField,$this->linkTemplate);
    $link = '<a href="'.$url.'">'.$this->linkText.'</a>';
    $formattedValue = new HTMLFieldOutput($linkField);
    $formattedValue->formattedValue = $this->linkText;
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

    $form->add('select', 'link_field', E::ts('Field to link to'), $fieldSelect, true, array(
      'style' => 'min-width:250px',
      'class' => 'crm-select2 huge data-processor-field-for-name',
      'placeholder' => E::ts('- select -'),
    ));
    $form->add('text', 'link_template', E::ts('Link Template'), array(
      'style' => 'min-width:250px',
      'class' => 'crm-select2 huge',
    ), true);
    $form->add('text', 'link_text', E::ts('Link Text'), array(
      'style' => 'min-width:250px',
      'class' => 'crm-select2 huge',
    ), true);
    if (isset($field['configuration'])) {
      $configuration = $field['configuration'];
      $defaults = array();
      if (isset($configuration['link_field']) && isset($configuration['link_field_datasource'])) {
        $defaults['link_field'] = $configuration['link_field_datasource'] . '::' . $configuration['link_field'];
      }
      if (isset($configuration['link_template'])) {
        $defaults['link_template'] = $configuration['link_template'] ;
      }
      if (isset($configuration['link_text'])) {
        $defaults['link_text'] = $configuration['link_text'] ;
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
    return "CRM/Dataprocessor/Form/Field/Configuration/CustomLinkFieldOutputHandler.tpl";
  }


  /**
   * Process the submitted values and create a configuration array
   *
   * @param $submittedValues
   * @return array
   */
  public function processConfiguration($submittedValues) {
    list($contact_id_datasource, $contact_id_field) = explode('::', $submittedValues['link_field'], 2);
    $configuration['link_field'] = $contact_id_field;
    $configuration['link_field_datasource'] = $contact_id_datasource;
    $configuration['link_template'] =$submittedValues['link_template'];
    $configuration['link_text'] =$submittedValues['link_text'];
    return $configuration;
  }

}
