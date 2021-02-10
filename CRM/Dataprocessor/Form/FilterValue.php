<?php

use CRM_Dataprocessor_ExtensionUtil as E;

/**
 * Form controller class
 *
 * @see https://wiki.civicrm.org/confluence/display/CRMDOC/QuickForm+Reference
 */
class CRM_Dataprocessor_Form_FilterValue extends CRM_Core_Form {

  private $dataProcessorId;

  /**
   * @var \Civi\DataProcessor\ProcessorType\AbstractProcessorType
   */
  private $dataProcessorClass;

  /**
   * @var array
   */
  private $dataProcessor;

  private $id;

  private $filter;

  /**
   * @var Civi\DataProcessor\FilterHandler\AbstractFilterHandler
   */
  private $filterTypeClass;

  private $snippet;

  /**
   * Function to perform processing before displaying form (overrides parent function)
   *
   * @access public
   */
  function preProcess() {
    $this->snippet = CRM_Utils_Request::retrieve('snippet', 'String');
    if ($this->snippet) {
      $this->assign('suppressForm', TRUE);
      $this->controller->_generateQFKey = FALSE;
    }

    $factory = dataprocessor_get_factory();
    $this->dataProcessorId = CRM_Utils_Request::retrieve('data_processor_id', 'Integer');
    $this->dataProcessor = civicrm_api3('DataProcessor', 'getsingle', array('id' => $this->dataProcessorId));
    $this->dataProcessorClass = CRM_Dataprocessor_BAO_DataProcessor::dataProcessorToClass($this->dataProcessor, true);
    $this->assign('data_processor_id', $this->dataProcessorId);

    $this->id = CRM_Utils_Request::retrieve('id', 'Integer');
    $this->assign('id', $this->id);


    $this->filter = civicrm_api3('DataProcessorFilter', 'getsingle', array('id' => $this->id));
    $this->filterTypeClass = $factory->getFilterByName($this->filter['type']);
    $this->filterTypeClass->setDataProcessor($this->dataProcessorClass);
    try {
      $this->filterTypeClass->initialize($this->filter);
    } catch (\Civi\DataProcessor\Exception\FilterRequiredException $ex) {
      // Do nothing.
    }

    $title = E::ts('Data Processor Default Filter Value');
    CRM_Utils_System::setTitle($title);
  }

  public function buildQuickForm() {
    $this->add('hidden', 'data_processor_id');
    $this->add('hidden', 'id');

    $filter = $this->filterTypeClass->addToFilterForm($this, $this->filter['filter_value']);
    $this->assign('filter', $filter);
    $this->assign('filter_template', $this->filterTypeClass->getTemplateFileName());

    $this->addButtons(array(
      array('type' => 'next', 'name' => E::ts('Save'), 'isDefault' => TRUE,),
      array('type' => 'cancel', 'name' => E::ts('Cancel'))));
    parent::buildQuickForm();
  }

  function setDefaultValues() {
    $defaults = array();
    $defaults['data_processor_id'] = $this->dataProcessorId;
    $defaults['id'] = $this->id;
    return $defaults;
  }

  /**
   * Function that can be defined in Form to override or.
   * perform specific action on cancel action
   */
  public function cancelAction() {
    $this->dataProcessorId = CRM_Utils_Request::retrieve('data_processor_id', 'Integer');
    $redirectUrl = CRM_Utils_System::url('civicrm/dataprocessor/form/edit', array('reset' => 1, 'action' => 'update', 'id' => $this->dataProcessorId));
    CRM_Utils_System::redirect($redirectUrl);
  }

  public function postProcess() {
    $redirectUrl = CRM_Utils_System::url('civicrm/dataprocessor/form/edit', array('reset' => 1, 'action' => 'update', 'id' => $this->dataProcessorId));
    $values = $this->exportValues();
    $default_filter_value = $this->filterTypeClass->processSubmittedValues($values);
    $this->filter['filter_value'] = $default_filter_value;

    civicrm_api3('DataProcessorFilter', 'create', $this->filter);

    CRM_Utils_System::redirect($redirectUrl);
    parent::postProcess();
  }

}
