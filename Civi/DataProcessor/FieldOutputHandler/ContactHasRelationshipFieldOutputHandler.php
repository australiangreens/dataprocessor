<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */
namespace Civi\DataProcessor\FieldOutputHandler;

use Civi\DataProcessor\DataSpecification\FieldSpecification;
use Civi\DataProcessor\Exception\DataSourceNotFoundException;
use Civi\DataProcessor\Exception\FieldNotFoundException;
use Civi\DataProcessor\Source\SourceInterface;

use CRM_Dataprocessor_ExtensionUtil as E;

class ContactHasRelationshipFieldOutputHandler extends AbstractFieldOutputHandler {

  protected $relationshipTypeIds = [];

  protected $activeText = '';

  protected $inactiveText = '';

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
    $contactId = $rawRecord[$this->inputFieldSpec->alias];
    $count = 0;
    if (count($this->relationshipTypeIds)) {
      $relationship_types = implode(", ", $this->relationshipTypeIds);
      $sql = "
          SELECT COUNT(*)
          FROM civicrm_relationship
          WHERE relationship_type_id IN({$relationship_types})
          AND (contact_id_a = %1 OR contact_id_b = %1)
          AND (start_date IS NULL OR DATE(start_date) <= NOW())
          AND (end_date IS NULL OR DATE(end_date) >= NOW())
          AND is_active = '1'";
      $sqlParams[1] = [$contactId, 'Integer'];
      $count = \CRM_Core_DAO::singleValueQuery($sql, $sqlParams);
    }
    $output = new FieldOutput($count ? true : false);
    if ($count && $this->activeText) {
      $output->formattedValue = $this->activeText;
    } elseif ($this->inactiveText) {
      $output->formattedValue = $this->inactiveText;
    }
    return $output;
  }

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
    $this->outputFieldSpec->type = $this->getType();

    if (isset($configuration['relationship_types']) && is_array($configuration['relationship_types'])) {
      $this->relationshipTypeIds = array();
      foreach($configuration['relationship_types'] as $rel_type) {
        try {
          $this->relationshipTypeIds[] = civicrm_api3('RelationshipType', 'getvalue', [
            'return' => 'id',
            'name_a_b' => $rel_type
          ]);
        } catch (\CiviCRM_API3_Exception $e) {
          // Do nothing
        }
      };
    }
    if (isset($configuration['active_text'])) {
      $this->activeText = $configuration['active_text'];
    }
    if (isset($configuration['inactive_text'])) {
      $this->inactiveText = $configuration['inactive_text'];
    }
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
    $relationshipTypeApi = civicrm_api3('RelationshipType', 'get', array('is_active' => 1, 'options' => array('limit' => 0)));
    $relationshipTypes = array();
    foreach($relationshipTypeApi['values'] as $relationship_type) {
      $relationshipTypes[$relationship_type['name_a_b']] = $relationship_type['label_a_b'];
    }

    $form->add('select', 'field', E::ts('Contact ID Field'), $fieldSelect, true, array(
      'style' => 'min-width:250px',
      'class' => 'crm-select2 huge data-processor-field-for-name',
      'placeholder' => E::ts('- select -'),
    ));

    $form->add('select', 'relationship_types', E::ts('Relationship type'), $relationshipTypes, true, array(
      'style' => 'min-width:250px',
      'class' => 'crm-select2 huge',
      'placeholder' => E::ts('- Select relationship type -'),
      'multiple' => true,
    ));

    $form->add('text', 'active_text', E::ts('Active text'), [
      'style' => 'min-width:250px',
      'class' => 'huge',
    ], true);
    $form->add('text', 'inactive_text', E::ts('Active text'), [
      'style' => 'min-width:250px',
      'class' => 'huge',
    ], false);

    if (isset($field['configuration'])) {
      $configuration = $field['configuration'];
      $defaults = array();
      if (isset($configuration['field']) && isset($configuration['datasource'])) {
        $defaults['field'] = $configuration['datasource'] . '::' . $configuration['field'];
      }
      if (isset($configuration['relationship_types'])) {
        $defaults['relationship_types'] = $configuration['relationship_types'];
      }
      if (isset($configuration['active_text'])) {
        $defaults['active_text'] = $configuration['active_text'];
      }
      if (isset($configuration['inactive_text'])) {
        $defaults['inactive_text'] = $configuration['inactive_text'];
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
    return "CRM/Dataprocessor/Form/Field/Configuration/ContactHasRelationshipFieldOutputHandler.tpl";
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
    $configuration['datasource'] = $datasource;
    $configuration['relationship_types'] = isset($submittedValues['relationship_types']) ? $submittedValues['relationship_types'] : array();
    $configuration['active_text'] = isset($submittedValues['active_text']) ? $submittedValues['active_text'] : '';
    $configuration['inactive_text'] = isset($submittedValues['inactive_text']) ? $submittedValues['inactive_text'] : '';
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

  /**
   * Callback function for determining whether this field could be handled by this output handler.
   *
   * @param \Civi\DataProcessor\DataSpecification\FieldSpecification $field
   * @return bool
   */
  public function isFieldValid(FieldSpecification $field) {
    return true;
  }


}
