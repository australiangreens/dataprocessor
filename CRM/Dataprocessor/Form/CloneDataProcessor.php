<?php

use CRM_Dataprocessor_ExtensionUtil as E;

/**
 * Form controller class
 *
 * @see https://wiki.civicrm.org/confluence/display/CRMDOC/QuickForm+Reference
 */
class CRM_Dataprocessor_Form_CloneDataProcessor extends CRM_Core_Form {

  private $dataProcessorId;

  private $dataProcessor;

  /**
   * @var \Civi\DataProcessor\ProcessorType\AbstractProcessorType
   */
  private $dataProcessorClass;

  private $currentUrl;

  /**
   * Function to perform processing before displaying form (overrides parent function)
   *
   * @access public
   */
  function preProcess() {
    $this->dataProcessorId = CRM_Utils_Request::retrieveValue('id', 'Integer', NULL, TRUE);
    $this->dataProcessor = civicrm_api3('DataProcessor', 'getsingle', ['id' => $this->dataProcessorId]);
    $this->dataProcessorClass = CRM_Dataprocessor_BAO_DataProcessor::dataProcessorToClass($this->dataProcessor, true);
    $this->currentUrl = CRM_Utils_System::url('civicrm/dataprocessor/form/edit', array('reset' => 1, 'action' => 'update', 'id' => $this->dataProcessorId));
    $this->assign('data_processor_id', $this->dataProcessorId);
    $this->assign('dataProcessor', $this->dataProcessor);
  }

  public function buildQuickForm() {
    CRM_Utils_System::setTitle(E::ts('Clone data processor: %1', [1=>$this->dataProcessor['title']]));
    $this->add('hidden', 'id');

    $this->add('text', 'name', E::ts('Name'), array('size' => CRM_Utils_Type::HUGE), FALSE);
    $this->add('text', 'title', E::ts('Title'), array('size' => CRM_Utils_Type::HUGE), TRUE);
    $this->add('text', 'description', E::ts('Description'), array('size' => 100, 'maxlength' => 256));
    $this->add('checkbox', 'is_active', E::ts('Enabled'));

    $this->addButtons(array(
      array('type' => 'next', 'name' => E::ts('Save'), 'isDefault' => TRUE,),
      array('type' => 'cancel', 'name' => E::ts('Cancel'))
    ));

    parent::buildQuickForm();
  }

  /**
   * Function to set default values (overrides parent function)
   *
   * @return array $defaults
   * @access public
   */
  function setDefaultValues() {
    $defaults = array();
    $defaults['id'] = $this->dataProcessorId;
    if (!empty($this->dataProcessor) && !empty($this->dataProcessorId)) {
      $defaults['title'] = E::ts('Clone of %1', [1=>$this->dataProcessor['title']]);
      if (isset($this->dataProcessor['description'])) {
        $defaults['description'] = $this->dataProcessor['description'];
      } else {
        $defaults['description'] = '';
      }
      $defaults['is_active'] = $this->dataProcessor['is_active'];
    }
    return $defaults;
  }

  public function postProcess() {
    $session = CRM_Core_Session::singleton();
    $values = $this->exportValues();
    $dataProcessor = CRM_Dataprocessor_Utils_Importer::export($this->dataProcessorId);
    $dataProcessor['name'] = $values['name'];
    $dataProcessor['title'] = $values['title'];
    $dataProcessor['description'] = $values['description'];
    $dataProcessor['is_active'] = !empty($values['is_active']) ? 1 : 0;
    $newId = CRM_Dataprocessor_Utils_Importer::importDataProcessor($dataProcessor, null, null, CRM_Dataprocessor_Status::STATUS_IN_DATABASE);
    $redirectUrl = CRM_Utils_System::url('civicrm/dataprocessor/form/edit', array('reset' => 1, 'action' => 'update', 'id' => $newId));
    CRM_Utils_System::redirect($redirectUrl);
  }

  /**
   * Function to add validation rules (overrides parent function)
   *
   * @access public
   */
  function addRules() {
    if ($this->_action != CRM_Core_Action::DELETE) {
      $this->addFormRule(array(
        'CRM_Dataprocessor_Form_DataProcessor',
        'validateName'
      ));
    }
  }

  /**
   * Function to validate if rule label already exists
   *
   * @param array $fields
   * @return array|bool
   * @access static
   */
  static function validateName($fields) {
    /*
     * if id not empty, edit mode. Check if changed before check if exists
     */
    $id = false;
    if (empty($fields['name'])) {
      $fields['name'] = CRM_Dataprocessor_BAO_DataProcessor::checkName($fields['title'], $id);
    }
    if (!CRM_Dataprocessor_BAO_DataProcessor::isNameValid($fields['name'], $id)) {
      $errors['name'] = E::ts('There is already a data processor with this name');
      return $errors;
    }
    return TRUE;
  }

}
