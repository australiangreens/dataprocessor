<?php
/**
 * @author Klaas Eikelboom <klaas.eikelboom@civicoop.org>
 * @license AGPL-3.0
 */

namespace Civi\DataProcessor\FieldOutputHandler;

use CRM_Dataprocessor_ExtensionUtil as E;
use Civi\DataProcessor\Source\SourceInterface;
use Civi\DataProcessor\DataSpecification\FieldSpecification;

class CustomLinkTwoFieldOutputHandler extends AbstractFieldOutputHandler {

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
  protected $linkFieldOne;
  protected $linkFieldTwo;

  /**
   * @var SourceInterface
   */
  protected $linkFieldOneSource;
  protected $linkFieldTwoSource;

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
    list($this->linkFieldOneSource, $this->linkFieldOne) = $this->initializeField($configuration['link_field_1'], $configuration['link_field_datasource_1'], $alias.'_link_field_1');
    list($this->linkFieldTwoSource, $this->linkFieldTwo) = $this->initializeField($configuration['link_field_2'], $configuration['link_field_datasource_2'], $alias.'_link_field_2');
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
    $linkFieldOne = $rawRecord[$this->linkFieldOne->alias];
    $linkFieldTwo = $rawRecord[$this->linkFieldTwo->alias];

    $url = $this->linkTemplate;
    $url = str_replace('%1',$linkFieldOne,$url);
    $url = str_replace('%2',$linkFieldTwo,$url);

    $label = $this->linkText;
    $label = str_replace('%1',$linkFieldOne,$label);
    $label = str_replace('%2',$linkFieldTwo,$label);
    $link = '<a href="'.$url.'">'.$label.'</a>';

    $formattedValue = new HTMLFieldOutput($link);
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

    $form->add('select', 'link_field_1', E::ts('Field 1 to link to'), $fieldSelect, true, array(
      'style' => 'min-width:250px',
      'class' => 'crm-select2 huge data-processor-field-for-name',
      'placeholder' => E::ts('- select -'),
    ));
    $form->add('select', 'link_field_2', E::ts('Field 2 to link to'), $fieldSelect, true, array(
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
      if (isset($configuration['link_field_1']) && isset($configuration['link_field_datasource_1'])) {
        $defaults['link_field_1'] = \CRM_Dataprocessor_Utils_DataSourceFields::getSelectedFieldValue($field['data_processor_id'], $configuration['link_field_datasource_1'], $configuration['link_field_1']);
      }
      if (isset($configuration['link_field_2']) && isset($configuration['link_field_datasource_2'])) {
        $defaults['link_field_2'] = \CRM_Dataprocessor_Utils_DataSourceFields::getSelectedFieldValue($field['data_processor_id'], $configuration['link_field_datasource_2'], $configuration['link_field_2']);
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
    return "CRM/Dataprocessor/Form/Field/Configuration/CustomLinkTwoFieldOutputHandler.tpl";
  }


  /**
   * Process the submitted values and create a configuration array
   *
   * @param $submittedValues
   * @return array
   */
  public function processConfiguration($submittedValues) {
    list($ds1, $lf1) = explode('::', $submittedValues['link_field_1'], 2);
    list($ds2, $lf2) = explode('::', $submittedValues['link_field_2'], 2);
    $configuration['link_field_1'] = $lf1;
    $configuration['link_field_2'] = $lf2;
    $configuration['link_field_datasource_1'] = $ds1;
    $configuration['link_field_datasource_2'] = $ds2;
    $configuration['link_template'] =$submittedValues['link_template'];
    $configuration['link_text'] =$submittedValues['link_text'];
    return $configuration;
  }
}
