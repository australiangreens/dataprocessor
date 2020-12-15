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

class GroupsOfContactFieldOutputHandler extends AbstractFieldOutputHandler {

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
   * @var FieldSpecification
   */
  protected $outputFieldSpecification;

  /**
   * @var int|false
   */
  protected $parent_group_id;

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
    list($this->contactIdSource, $this->contactIdField) = $this->initializeField($configuration['field'], $configuration['datasource'], $alias);
    $this->outputFieldSpecification = new FieldSpecification($this->contactIdField->name, 'String', $title, null, $alias);
    if (isset($configuration['parent_group']) && $configuration['parent_group']) {
      $this->parent_group_id = civicrm_api3('Group', 'getvalue', array('return' => 'id', 'name' => $configuration['parent_group']));
    }
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
    $sql = "SELECT g.title, g.id
            FROM civicrm_group g
            INNER JOIN civicrm_group_contact gc ON gc.group_id = g.id
            WHERE gc.status = 'Added' AND gc.contact_id = %1";
    if ($this->parent_group_id) {
      $childGroupIds = \CRM_Contact_BAO_GroupNesting::getDescendentGroupIds([$this->parent_group_id], FALSE);
      $sql .= " AND gc.group_id IN (".implode(", ", $childGroupIds).")";
    }
    $sql .= " ORDER BY g.title";
    $sqlParams[1] = array($contactId, 'Integer');
    $dao = \CRM_Core_DAO::executeQuery($sql, $sqlParams);
    $rawValues = array();
    $formattedValues = array();
    while($dao->fetch()) {
      $rawValues[] = array(
        'group_id' => $dao->id,
        'group' => $dao->title,
      );
      $formattedValues[] = $dao->title;
    }
    $output = new FieldOutput($rawValues);
    $output->formattedValue = implode(", ", $formattedValues);
    return $output;
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
    $groupsApi = civicrm_api3('Group', 'get', array('is_active' => 1, 'options' => array('limit' => 0)));
    $groups = array();
    foreach($groupsApi['values'] as $group) {
      $groups[$group['name']] = $group['title'];
    }

    $form->add('select', 'contact_id_field', E::ts('Contact ID Field'), $fieldSelect, true, array(
      'style' => 'min-width:250px',
      'class' => 'crm-select2 huge data-processor-field-for-name',
      'placeholder' => E::ts('- select -'),
    ));
    $form->add('select', 'parent_group', E::ts('Show only subgroup(s) of'), $groups, false, array(
      'style' => 'min-width:250px',
      'class' => 'crm-select2 huge',
      'placeholder' => E::ts('- Show all groups -'),
      'multiple' => false,
    ));

    if (isset($field['configuration'])) {
      $configuration = $field['configuration'];
      $defaults = array();
      if (isset($configuration['field']) && isset($configuration['datasource'])) {
        $defaults['contact_id_field'] = \CRM_Dataprocessor_Utils_DataSourceFields::getSelectedFieldValue($field['data_processor_id'], $configuration['datasource'], $configuration['field']);
      }
      if (isset($configuration['parent_group'])) {
        $defaults['parent_group'] = $configuration['parent_group'];
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
    return "CRM/Dataprocessor/Form/Field/Configuration/GroupsOfContactFieldOutputHandler.tpl";
  }


  /**
   * Process the submitted values and create a configuration array
   *
   * @param $submittedValues
   * @return array
   */
  public function processConfiguration($submittedValues) {
    list($datasource, $field) = explode('::', $submittedValues['contact_id_field'], 2);
    $configuration['field'] = $field;
    $configuration['datasource'] = $datasource;
    $configuration['parent_group'] = isset($submittedValues['parent_group']) ? $submittedValues['parent_group'] : false;
    return $configuration;
  }




}
