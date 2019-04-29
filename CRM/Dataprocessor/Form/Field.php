<?php

use CRM_Dataprocessor_ExtensionUtil as E;

/**
 * Form controller class
 *
 * @see https://wiki.civicrm.org/confluence/display/CRMDOC/QuickForm+Reference
 */
class CRM_Dataprocessor_Form_Field extends CRM_Core_Form {

  private $dataProcessorId;

  private $id;

  private $field;

  /**
   * Function to perform processing before displaying form (overrides parent function)
   *
   * @access public
   */
  function preProcess() {
    $session = CRM_Core_Session::singleton();
    $this->dataProcessorId = CRM_Utils_Request::retrieve('data_processor_id', 'Integer');
    $this->assign('data_processor_id', $this->dataProcessorId);

    $this->id = CRM_Utils_Request::retrieve('id', 'Integer');
    $this->assign('id', $this->id);

    if ($this->id) {
      $this->field = civicrm_api3('DataProcessorField', 'getsingle', array('id' => $this->id));
      $this->assign('field', $this->field);
    }

    $title = E::ts('Data Processor Field');
    CRM_Utils_System::setTitle($title);

    $url = CRM_Utils_System::url('civicrm/dataprocessor/form/edit', array('id' => $this->dataProcessorId, 'action' => 'update', 'reset' => 1));
    $session->pushUserContext($url);
  }

  public function buildQuickForm() {
    $this->add('hidden', 'data_processor_id');
    $this->add('hidden', 'id');
    if ($this->_action != CRM_Core_Action::DELETE) {
      $this->add('text', 'name', E::ts('Name'), array('size' => CRM_Utils_Type::HUGE), FALSE);
      $this->add('text', 'title', E::ts('Title'), array('size' => CRM_Utils_Type::HUGE), TRUE);

      $outputHandlers = CRM_Dataprocessor_BAO_DataProcessor::getAvailableOutputHandlers($this->dataProcessorId);
      foreach($outputHandlers as $outputHandler) {
        $outputHandlersSelect[$outputHandler->getName()] = $outputHandler->getTitle();
      }

      $this->add('select', 'type', E::ts('Select Field'), $outputHandlersSelect, true, array(
        'style' => 'min-width:250px',
        'class' => 'crm-select2 huge',
        'placeholder' => E::ts('- select -'),
      ));
    }
    if ($this->_action == CRM_Core_Action::ADD) {
      $this->addButtons(array(
        array('type' => 'next', 'name' => E::ts('Next'), 'isDefault' => TRUE,),
        array('type' => 'cancel', 'name' => E::ts('Cancel'))));
    } elseif ($this->_action == CRM_Core_Action::DELETE) {
      $this->addButtons(array(
        array('type' => 'next', 'name' => E::ts('Delete'), 'isDefault' => TRUE,),
        array('type' => 'cancel', 'name' => E::ts('Cancel'))));
    } else {
      $this->addButtons(array(
        array('type' => 'next', 'name' => E::ts('Save'), 'isDefault' => TRUE,),
        array('type' => 'cancel', 'name' => E::ts('Cancel'))));
    }
    parent::buildQuickForm();
  }

  function setDefaultValues() {
    $defaults = [];
    $defaults['data_processor_id'] = $this->dataProcessorId;
    $defaults['id'] = $this->id;

    if ($this->field) {
      if (isset($this->field['type'])) {
        $defaults['type'] = $this->field['type'];
      }
      if (isset($this->field['title'])) {
        $defaults['title'] = $this->field['title'];
      }
      if (isset($this->field['name'])) {
        $defaults['name'] = $this->field['name'];
      }
    }
    return $defaults;
  }

  public function postProcess() {
    $session = CRM_Core_Session::singleton();
    $redirectUrl = $session->readUserContext();
    if ($this->_action == CRM_Core_Action::DELETE) {
      civicrm_api3('DataProcessorField', 'delete', array('id' => $this->id));
      $session->setStatus(E::ts('Field removed'), E::ts('Removed'), 'success');
      CRM_Utils_System::redirect($redirectUrl);
    }

    $values = $this->exportValues();
    if (!empty($values['name'])) {
      $params['name'] = $values['name'];
    }
    $params['title'] = $values['title'];
    $params['type'] = $values['type'];
    $params['data_processor_id'] = $this->dataProcessorId;
    if ($this->id) {
      $params['id'] = $this->id;
    }

    civicrm_api3('DataProcessorField', 'create', $params);

    CRM_Utils_System::redirect($redirectUrl);
    parent::postProcess();
  }

}