<?php

use CRM_Dataprocessor_ExtensionUtil as E;

/**
 * Form controller class
 *
 * @see https://wiki.civicrm.org/confluence/display/CRMDOC/QuickForm+Reference
 */
class CRM_Dataprocessor_Form_Source extends CRM_Core_Form {

  private $dataProcessorId;

  private $dataProcessor;

  /**
   * @var Civi\DataProcessor\ProcessorType\AbstractProcessorType
   */
  private $dataProcessorClass;

  private $id;

  private $isFirstDataSource = true;

  private $source;

  /**
   * @var Civi\DataProcessor\Source\SourceInterface
   */
  private $sourceClass;

  /**
   * @var Civi\DataProcessor\DataFlow\MultipleDataFlows\JoinInterface
   */
  private $joinClass;

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
      $block = CRM_Utils_Request::retrieveValue('block', 'String', 'configuration');
      $this->assign('block', $block);
    }

    $factory = dataprocessor_get_factory();

    $this->dataProcessorId = CRM_Utils_Request::retrieve('data_processor_id', 'Integer');
    $this->assign('data_processor_id', $this->dataProcessorId);
    if ($this->dataProcessorId) {
      $this->dataProcessor = civicrm_api3('DataProcessor', 'getsingle', array('id' => $this->dataProcessorId));
      $this->dataProcessorClass = CRM_Dataprocessor_BAO_DataProcessor::dataProcessorToClass($this->dataProcessor, true);
    }

    $this->id = CRM_Utils_Request::retrieve('id', 'Integer');
    $this->assign('id', $this->id);

    $this->assign('has_configuration', false);

    $sources = civicrm_api3('DataProcessorSource', 'get', array('data_processor_id' => $this->dataProcessorId, 'options' => array('limit' => 0)));
    if ($this->id) {
      $this->source = civicrm_api3('DataProcessorSource', 'getsingle', array('id' => $this->id));
      $this->assign('source', $this->source);
      $this->sourceClass = CRM_Dataprocessor_BAO_DataProcessorSource::sourceToSourceClass($this->source);
      $this->assign('has_configuration', $this->sourceClass->hasConfiguration());

      $i = 0;
      foreach($sources['values'] as $s) {
        if ($s['id'] == $this->id) {
          $this->isFirstDataSource = $i > 0 ? false : true;
          break;
        }
        $i++;
      }
    } else {
      $this->isFirstDataSource = count($sources['values']) > 0 ? false : true;
      $this->source['data_processor_id'] = $this->dataProcessorId;
    }
    $this->assign('is_first_data_source', $this->isFirstDataSource);

    $type = CRM_Utils_Request::retrieve('type', 'String');
    if ($type) {
      $this->source['type'] = $type;
      $this->sourceClass = CRM_Dataprocessor_BAO_DataProcessorSource::sourceToSourceClass($this->source);
      $this->assign('has_configuration', $this->sourceClass->hasConfiguration());
      if ($this->sourceClass && !$this->id) {
        $this->source['configuration'] = $this->sourceClass->getDefaultConfiguration();
      }
    }

    $join_type = CRM_Utils_Request::retrieve('join_type', 'String');
    if ($join_type) {
      $this->source['join_type'] = $join_type;
    }

    $this->assign('has_join_configuration', false);
    if (!$this->isFirstDataSource && isset($this->source['join_type']) && $this->source['join_type']) {
      $this->joinClass = $factory->getJoinByName($this->source['join_type']);
      $this->assign('has_join_configuration', $this->joinClass->hasConfiguration());
    }

    if (!isset($this->source['join_configuration']) || !is_array($this->source['join_configuration'])) {
      $this->source['join_configuration'] = array();
    }

    $title = E::ts('Data Processor Source');
    CRM_Utils_System::setTitle($title);
  }

  public function buildQuickForm() {
    $this->add('hidden', 'data_processor_id');
    $this->add('hidden', 'id');
    if ($this->_action == CRM_Core_Action::DELETE) {
      $this->addButtons(array(
        array('type' => 'next', 'name' => E::ts('Delete'), 'isDefault' => TRUE,),
        array('type' => 'cancel', 'name' => E::ts('Cancel'))));
    } else {
      $this->add('text', 'name', E::ts('Name'), array('size' => CRM_Utils_Type::HUGE), FALSE);
      $this->add('text', 'title', E::ts('Title'), array('size' => CRM_Utils_Type::HUGE), TRUE);

      $factory = dataprocessor_get_factory();
      $types = array(' - select - ')  + $factory->getDataSources();
      $this->add('select', 'type', ts('Select source'), $types, true, array(
        'style' => 'min-width:250px',
        'class' => 'crm-select2 huge',
        'placeholder' => E::ts('- select -'),
      ));

      if (!$this->isFirstDataSource) {
        $joins = [' - select - '] + $factory->getJoins();
        $this->add('select', 'join_type', ts('Select Join Type'), $joins, TRUE, array(
          'style' => 'min-width:250px',
          'class' => 'crm-select2 huge',
          'placeholder' => E::ts('- select -'),
        ));
        if ($this->joinClass && $this->joinClass->hasConfiguration()) {
          $joinableToSources = array();
          foreach($this->dataProcessorClass->getDataSources() as $source) {
            if ($this->sourceClass && $this->sourceClass->getSourceName() == $source->getSourceName()) {
              break;
            }
            $joinableToSources[] = $source;
          }
          $this->joinClass->buildConfigurationForm($this, $this->sourceClass, $joinableToSources, $this->source['join_configuration']);
          $this->assign('join_configuration_template', $this->joinClass->getConfigurationTemplateFileName());
        }
      }

      if ($this->sourceClass && $this->sourceClass->hasConfiguration()) {
        $this->sourceClass->buildConfigurationForm($this, $this->source);
        $this->assign('configuration_template', $this->sourceClass->getConfigurationTemplateFileName());
      }

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

    if (isset($this->source['type'])) {
      $defaults['type'] = $this->source['type'];
    }
    if (isset($this->source['title'])) {
      $defaults['title'] = $this->source['title'];
    }
    if (isset($this->source['name'])) {
      $defaults['name'] = $this->source['name'];
    }
    if (isset($this->source['join_type'])) {
      $defaults['join_type'] = $this->source['join_type'];
    }
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
    $session = CRM_Core_Session::singleton();
    $redirectUrl = CRM_Utils_System::url('civicrm/dataprocessor/form/edit', array('reset' => 1, 'action' => 'update', 'id' => $this->dataProcessorId));
    if ($this->_action == CRM_Core_Action::DELETE) {
      civicrm_api3('DataProcessorSource', 'delete', array('id' => $this->id));
      $session->setStatus(E::ts('Data Processor Source removed'), E::ts('Removed'), 'success');
      CRM_Utils_System::redirect($redirectUrl);
    }

    $values = $this->exportValues();

    $factory = dataprocessor_get_factory();

    if (!empty($values['name'])) {
      $params['name'] = $values['name'];
    }
    $params['title'] = $values['title'];
    $params['type'] = $values['type'];
    if ($this->dataProcessorId) {
      $params['data_processor_id'] = $this->dataProcessorId;
    }
    if ($this->id) {
      $params['id'] = $this->id;
    }
    $this->sourceClass = CRM_Dataprocessor_BAO_DataProcessorSource::sourceToSourceClass($params);

    if ($this->sourceClass && $this->sourceClass->hasConfiguration()) {
      $params['configuration'] = $this->sourceClass->processConfiguration($values);
    }

    if (!$this->isFirstDataSource) {
      $params['join_type'] = $values['join_type'];
      if ($this->joinClass && $this->joinClass->hasConfiguration()) {
        $params['join_configuration'] = $this->joinClass->processConfiguration($values, $this->sourceClass);
      }
    } else {
      $params['join_type'] = '';
    }
    $result = civicrm_api3('DataProcessorSource', 'create', $params);

    CRM_Utils_System::redirect($redirectUrl);
    parent::postProcess();
  }

  /**
   * Function to add validation rules (overrides parent function)
   *
   * @access public
   */
  function addRules() {
    if ($this->_action != CRM_Core_Action::DELETE) {
      $this->addFormRule(array(
        'CRM_Dataprocessor_Form_Source',
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
    if (!empty($fields['id'])) {
      $id = $fields['id'];
    }
    if (empty($fields['name'])) {
      $fields['name'] = CRM_Dataprocessor_BAO_DataProcessorSource::checkName($fields['title'], $fields['data_processor_id'], $id);
    }
    if (!CRM_Dataprocessor_BAO_DataProcessorSource::isNameValid($fields['name'], $fields['data_processor_id'], $id)) {
      $errors['name'] = E::ts('There is already a data source with this name');
      return $errors;
    }
    return TRUE;
  }


}
