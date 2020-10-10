<?php
/**
 * Created by Chamil Wijesooriya.
 * Date: 09/10/2020
 * Time: 13:11
 */

namespace Civi\DataProcessor\FieldOutputHandler;

use CRM_Dataprocessor_ExtensionUtil as E;
use Civi\DataProcessor\Source\SourceInterface;
use Civi\DataProcessor\DataSpecification\FieldSpecification;

class ImageFieldOutputHandler extends AbstractFieldOutputHandler
{

  /**
   * @var FieldSpecification
   */
  protected $imageField;

  /**
   * @var SourceInterface
   */
  protected $dataSource;

  /**
   * Store alt text
   * @var $imageText
   */
  protected $imageText;

  /**
   * @var FieldSpecification
   */
  protected $outputFieldSpecification;

  /**
   * @return \Civi\DataProcessor\DataSpecification\FieldSpecification
   */
  public function getOutputFieldSpecification()
  {
    return $this->outputFieldSpecification;
  }

  /**
   * Returns the data type of this field
   *
   * @return String
   */
  protected function getType()
  {
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
  public function initialize($alias, $title, $configuration)
  {
    list($this->dataSource, $this->imageField) = $this->initializeField($configuration['image_field'], $configuration['datasource'], $alias . '_image_field');
    if (isset($configuration['image_text'])) {
      $this->imageText = $configuration['image_text'];
    }
    $this->outputFieldSpecification = new FieldSpecification($this->imageField->name, 'String', $title, null, $alias);
  }

  /**
   * Returns the formatted value
   *
   * @param $rawRecord
   * @param $formattedRecord
   *
   * @return \Civi\DataProcessor\FieldOutputHandler\FieldOutput
   */
  public function formatField($rawRecord, $formattedRecord)
  {
    $rawValue = $rawRecord[$this->imageField->alias];
    $output = new HTMLFieldOutput($rawValue);
    if ($rawValue) {
      $attachment = civicrm_api3('Attachment', 'getsingle', array('id' => $rawValue));
      if (!isset($attachment['is_error']) || $attachment['is_error'] == '0') {
        $altText = $attachment['name'];
        if ($this->imageText) {
          $altText = $this->imageText;
        }
        $imageHTML = '<img src="' . $attachment['url'] . '" alt="' . $altText . '" />';
        $output->rawValue = $imageHTML;
        $output->formattedValue = $imageHTML;
        $output->setHtmlOutput($imageHTML);
      }
    }
    return $output;
  }

  /**
   * Returns true when this handler has additional configuration.
   *
   * @return bool
   */
  public function hasConfiguration()
  {
    return true;
  }

  /**
   * When this handler has additional configuration you can add
   * the fields on the form with this function.
   *
   * @param \CRM_Core_Form $form
   * @param array $field
   */
  public function buildConfigurationForm(\CRM_Core_Form $form, $field = array())
  {
    $fieldSelect = $this->getFieldOptions($field['data_processor_id']);

    $form->add('select', 'image_field', E::ts('Field to link to'), $fieldSelect, true, array(
      'style' => 'min-width:250px',
      'class' => 'crm-select2 huge data-processor-field-for-name',
      'placeholder' => E::ts('- select -'),
    ));
    $form->add('text', 'image_text', E::ts('Alt Image Text'), array(
      'style' => 'min-width:250px',
      'class' => 'crm-select2 huge',
    ), false);
    if (isset($field['configuration'])) {
      $configuration = $field['configuration'];
      $defaults = array();
      if (isset($configuration['image_field']) && isset($configuration['datasource'])) {
        $defaults['image_field'] = $configuration['datasource'] . '::' . $configuration['image_field'];
      }
      if (isset($configuration['image_text'])) {
        $defaults['image_text'] = $configuration['image_text'];
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
  public function getConfigurationTemplateFileName()
  {
    return "CRM/Dataprocessor/Form/Field/Configuration/ImageFieldOutputHandler.tpl";
  }


  /**
   * Process the submitted values and create a configuration array
   *
   * @param $submittedValues
   * @return array
   */
  public function processConfiguration($submittedValues)
  {
    list($datasource, $image_field) = explode('::', $submittedValues['image_field'], 2);
    $configuration['image_field'] = $image_field;
    $configuration['datasource'] = $datasource;
    $configuration['image_text'] = $submittedValues['image_text'];
    return $configuration;
  }

  /**
   * Callback function for determining whether this field could be handled by this output handler.
   *
   * @param \Civi\DataProcessor\DataSpecification\FieldSpecification $field
   * @return bool
   */
  public function isFieldValid(FieldSpecification $field)
  {
    if ($field->type == 'File') {
      return true;
    }
    return false;
  }

  /**
   * Returns all possible fields
   *
   * @param $data_processor_id
   *
   * @return array
   * @throws \Exception
   */
  protected function getFieldOptions($data_processor_id)
  {
    $fieldSelect = \CRM_Dataprocessor_Utils_DataSourceFields::getAvailableFieldsInDataSources($data_processor_id, array($this, 'isFieldValid'));
    return $fieldSelect;
  }

}
