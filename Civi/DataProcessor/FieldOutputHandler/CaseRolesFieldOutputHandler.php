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
use Civi\DataProcessor\Exception\DataSourceNotFoundException;
use Civi\DataProcessor\Exception\FieldNotFoundException;
use Civi\DataProcessor\FieldOutputHandler\FieldOutput;

class CaseRolesFieldOutputHandler extends AbstractFieldOutputHandler {

  /**
   * @var \Civi\DataProcessor\Source\SourceInterface
   */
  protected $dataSource;

  /**
   * @var SourceInterface
   */
  protected $caseIdSource;

  /**
   * @var FieldSpecification
   */
  protected $caseIdField;

  /**
   * @var FieldSpecification
   */
  protected $outputFieldSpecification;

  /**
   * @var array
   */
  protected $relationship_type_ids = array();

  protected $show_label = true;

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
    list($this->caseIdSource, $this->caseIdField) = $this->initializeField($configuration['field'], $configuration['datasource'], $alias);
    $this->outputFieldSpecification = new FieldSpecification($this->caseIdField->name, 'String', $title, null, $alias);

    if (isset($configuration['relationship_types']) && is_array($configuration['relationship_types'])) {
      $this->relationship_type_ids = array();
      foreach($configuration['relationship_types'] as $rel_type) {
        $this->relationship_type_ids[] = civicrm_api3('RelationshipType', 'getvalue', array('return' => 'id', 'name_a_b' => $rel_type));
      };
    }
    if (isset($configuration['show_label'])) {
      $this->show_label = $configuration['show_label'];
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
    $caseId = $rawRecord[$this->caseIdField->alias];
    $sql = "SELECT c.id, c.display_name, t.label_a_b, r.relationship_type_id
            FROM civicrm_contact c
            INNER JOIN civicrm_relationship r ON r.contact_id_b = c.id
            INNER JOIN civicrm_relationship_type t on r.relationship_type_id = t.id
            WHERE c.is_deleted = 0 AND r.is_active = 1 AND r.case_id = %1";
    if (count($this->relationship_type_ids)) {
      $sql .= " AND t.id IN (".implode(", ", $this->relationship_type_ids).") ";
    }
    $sql .= " ORDER BY t.label_a_b, c.display_name";
    $sqlParams[1] = array($caseId, 'Integer');
    $dao = \CRM_Core_DAO::executeQuery($sql, $sqlParams);
    $rawValues = array();
    $formattedValues = array();
    while($dao->fetch()) {
      $rawValues[] = array(
        'contact_id' => $dao->id,
        'relationship_type_id' => $dao->relationship_type_id,
        'relationship_type' => $dao->label_a_b,
        'display_name' => $dao->display_name
      );
      $url = \CRM_Utils_System::url('civicrm/contact/view', array(
        'reset' => 1,
        'cid' => $dao->id,
      ));
      $link = '<a href="'.$url.'">'.$dao->display_name.'</a>';
      if ($this->show_label) {
        $formattedValues[] = $dao->label_a_b . ':&nbsp;' . $link;
      } else {
        $formattedValues[] = $link;
      }
    }
    $output = new HTMLFieldOutput($rawValues);
    $output->formattedValue = implode("<br>", $formattedValues);
    $output->setHtmlOutput($output->formattedValue);
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
    $relationshipTypeApi = civicrm_api3('RelationshipType', 'get', array('is_active' => 1, 'options' => array('limit' => 0)));
    $relationshipTypes = array();
    foreach($relationshipTypeApi['values'] as $relationship_type) {
      $relationshipTypes[$relationship_type['name_a_b']] = $relationship_type['label_a_b'];
    }

    $form->add('select', 'case_id_field', E::ts('Case ID Field'), $fieldSelect, true, array(
      'style' => 'min-width:250px',
      'class' => 'crm-select2 huge data-processor-field-for-name',
      'placeholder' => E::ts('- select -'),
    ));
    $form->add('select', 'relationship_types', E::ts('Restrict to roles'), $relationshipTypes, false, array(
      'style' => 'min-width:250px',
      'class' => 'crm-select2 huge',
      'placeholder' => E::ts('- Show all roles -'),
      'multiple' => true,
    ));
    $form->add('checkbox', 'show_label', E::ts('Show role title'), false, false);
    if (isset($field['configuration'])) {
      $configuration = $field['configuration'];
      $defaults = array();
      if (isset($configuration['field']) && isset($configuration['datasource'])) {
        $defaults['case_id_field'] = $configuration['datasource'] . '::' . $configuration['field'];
      }
      if (isset($configuration['relationship_types'])) {
        $defaults['relationship_types'] = $configuration['relationship_types'];
      }
      if (isset($configuration['show_label'])) {
        $defaults['show_label'] = $configuration['show_label'];
      } elseif (!isset($configuration['show_label'])) {
        $defaults['show_label'] = 1;
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
    return "CRM/Dataprocessor/Form/Field/Configuration/CaseRolesFieldOutputHandler.tpl";
  }


  /**
   * Process the submitted values and create a configuration array
   *
   * @param $submittedValues
   * @return array
   */
  public function processConfiguration($submittedValues) {
    list($datasource, $field) = explode('::', $submittedValues['case_id_field'], 2);
    $configuration['field'] = $field;
    $configuration['datasource'] = $datasource;
    $configuration['relationship_types'] = isset($submittedValues['relationship_types']) ? $submittedValues['relationship_types'] : array();
    $configuration['show_label'] = isset($submittedValues['show_label']) ? $submittedValues['show_label'] : 0;
    return $configuration;
  }




}
