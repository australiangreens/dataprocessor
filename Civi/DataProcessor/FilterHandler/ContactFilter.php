<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

namespace Civi\DataProcessor\FilterHandler;

use Civi\DataProcessor\Exception\InvalidConfigurationException;
use CRM_Dataprocessor_ExtensionUtil as E;

class ContactFilter extends AbstractFieldFilterHandler {

  /**
   * @var array
   *   Filter configuration
   */
  protected $configuration;

  public function __construct() {
    parent::__construct();
  }

  /**
   * Initialize the filter
   *
   * @throws \Civi\DataProcessor\Exception\DataSourceNotFoundException
   * @throws \Civi\DataProcessor\Exception\InvalidConfigurationException
   * @throws \Civi\DataProcessor\Exception\FieldNotFoundException
   */
  protected function doInitialization() {
    if (!isset($this->configuration['datasource']) || !isset($this->configuration['field'])) {
      throw new InvalidConfigurationException(E::ts("Filter %1 requires a field to filter on. None given.", array(1=>$this->title)));
    }
    $this->initializeField($this->configuration['datasource'], $this->configuration['field']);
  }

  /**
   * Returns true when this filter has additional configuration
   *
   * @return bool
   */
  public function hasConfiguration() {
    return true;
  }

  /**
   * When this filter type has additional configuration you can add
   * the fields on the form with this function.
   *
   * @param \CRM_Core_Form $form
   * @param array $filter
   */
  public function buildConfigurationForm(\CRM_Core_Form $form, $filter=array()) {
    $fieldSelect = \CRM_Dataprocessor_Utils_DataSourceFields::getAvailableFilterFieldsInDataSources($filter['data_processor_id']);

    $form->add('select', 'field', E::ts('Contact ID Field'), $fieldSelect, true, array(
      'style' => 'min-width:250px',
      'class' => 'crm-select2 huge data-processor-field-for-name',
      'placeholder' => E::ts('- select -'),
    ));

    $groupsApi = civicrm_api3('Group', 'get', array('is_active' => 1, 'options' => array('limit' => 0)));
    $groups = array();
    foreach($groupsApi['values'] as $group) {
      $groups[$group['id']] = $group['title'];
    }
    $form->add('select', 'limit_groups', E::ts('Limit to Contacts in group(s)'), $groups, false, array(
      'style' => 'min-width:250px',
      'class' => 'crm-select2 huge',
      'placeholder' => E::ts('- Show all groups -'),
      'multiple' => true,
    ));

    $contactTypesApi = civicrm_api3('ContactType', 'get',array('is_active' => 1, 'options' => array('limit' => 0)));
    $contactTypes = array();
    foreach($contactTypesApi['values'] as $contactType) {
      $contactTypeName = $contactType['name'];
      if (isset($contactType['parent_id']) && $contactType['parent_id']) {
        $contactTypeName = 'sub_'. $contactTypeName;
      }
      $contactTypes[$contactTypeName] = $contactType['label'];
    }
    $form->add('select', 'limit_contact_types', E::ts('Limit to Contact Type(s)'), $contactTypes, false, array(
      'style' => 'min-width:250px',
      'class' => 'crm-select2 huge',
      'placeholder' => E::ts('- All contact types -'),
      'multiple' => true,
    ));

    if (isset($filter['configuration'])) {
      $configuration = $filter['configuration'];
      $defaults = array();
      if (isset($configuration['field']) && isset($configuration['datasource'])) {
        $defaults['field'] = \CRM_Dataprocessor_Utils_DataSourceFields::getSelectedFieldValue($filter['data_processor_id'], $configuration['datasource'], $configuration['field']);
      }
      if (isset($configuration['limit_groups'])) {
        $defaults['limit_groups'] = $configuration['limit_groups'];
      }
      if (isset($configuration['limit_contact_types'])) {
        $defaults['limit_contact_types'] = $configuration['limit_contact_types'];
      }
      $form->setDefaults($defaults);
    }
  }

  /**
   * When this filter type has configuration specify the template file name
   * for the configuration form.
   *
   * @return false|string
   */
  public function getConfigurationTemplateFileName() {
    return "CRM/Dataprocessor/Form/Filter/Configuration/ContactFilter.tpl";
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
    $configuration['limit_groups'] = isset($submittedValues['limit_groups']) ? $submittedValues['limit_groups'] : false;
    $configuration['limit_contact_types'] = isset($submittedValues['limit_contact_types']) ? $submittedValues['limit_contact_types'] : false;
    return $configuration;
  }

  /**
   * File name of the template to add this filter to the criteria form.
   *
   * @return string
   */
  public function getTemplateFileName() {
    return "CRM/Dataprocessor/Form/Filter/ContactFilter.tpl";
  }

  /**
   * Validate the submitted filter parameters.
   *
   * @param $submittedValues
   * @return array
   */
  public function validateSubmittedFilterParams($submittedValues) {
    $filterSpec = $this->getFieldSpecification();
    $filterName = $filterSpec->alias;
    if (isset($submittedValues[$filterName.'_op']) && $submittedValues[$filterName.'_op'] == 'current_user') {
      $submittedValues[$filterName.'_op'] = 'IN';
      $submittedValues[$filterName.'_value'] = [\CRM_Core_Session::getLoggedInContactID()];
    }
    return parent::validateSubmittedFilterParams($submittedValues);
  }

  /**
   * Apply the submitted filter
   *
   * @param $submittedValues
   * @throws \Exception
   */
  public function applyFilterFromSubmittedFilterParams($submittedValues) {
    if (isset($submittedValues['op']) && $submittedValues['op'] == 'current_user') {
      $submittedValues['op'] = 'IN';
      $submittedValues['value'] = [\CRM_Core_Session::getLoggedInContactID()];
    }
    parent::applyFilterFromSubmittedFilterParams($submittedValues);
  }

  /**
   * Add the elements to the filter form.
   *
   * @param \CRM_Core_Form $form
   * @param array $defaultFilterValue
   * @param string $size
   *   Possible values: full or compact
   * @return array
   *   Return variables belonging to this filter.
   */
  public function addToFilterForm(\CRM_Core_Form $form, $defaultFilterValue, $size='full') {
    $fieldSpec = $this->getFieldSpecification();
    $operations = $this->getOperatorOptions($fieldSpec);
    $defaults = array();

    $title = $fieldSpec->title;
    $alias = $fieldSpec->alias;
    if ($this->isRequired()) {
      $title .= ' <span class="crm-marker">*</span>';
    }

    $sizeClass = 'huge';
    $minWidth = 'min-width: 250px;';
    if ($size =='compact') {
      $sizeClass = 'medium';
      $minWidth = '';
    }

    $form->add('select', "{$alias}_op", E::ts('Operator:'), $operations, true, [
      'style' => $minWidth,
      'class' => 'crm-select2 '.$sizeClass,
      'multiple' => FALSE,
      'placeholder' => E::ts('- select -'),
    ]);

    $api_params = array();
    if (!empty($this->configuration['limit_groups'])) {
      $api_params['group'] = ['IN' => $this->configuration['limit_groups']];
    }
    $limitContactTypes = array();
    $limitContactSubTypes = array();
    if (isset($this->configuration['limit_contact_types']) && is_array($this->configuration['limit_contact_types'])) {
      foreach($this->configuration['limit_contact_types'] as $limit_contact_type) {
        if (stripos($limit_contact_type, 'sub_') === 0) {
          // This is a subtype
          $limitContactSubTypes[] = substr($limit_contact_type, 4);
        } else {
          $limitContactTypes[] = $limit_contact_type;
        }
      }
    }
    if (count($limitContactTypes)) {
      $api_params['contact_type'] = ['IN' => $limitContactTypes];
    }
    if (count($limitContactSubTypes)) {
      $api_params['contact_sub_type'] = ['IN' => $limitContactSubTypes];
    }
    $props = array(
      'placeholder' => E::ts('Select a contact'),
      'entity' => 'Contact',
      'api' => array('params' => $api_params),
      'create' => false,
      'multiple' => true,
      'style' => $minWidth,
      'class' => $sizeClass,
    );
    $form->addEntityRef( "{$alias}_value", '', $props);

    if (isset($defaultFilterValue['op'])) {
      $defaults[$alias . '_op'] = $defaultFilterValue['op'];
    } else {
      $defaults[$alias . '_op'] = key($operations);
    }
    if (isset($defaultFilterValue['value'])) {
      $defaults[$alias.'_value'] = $defaultFilterValue['value'];
    }
    if (count($defaults)) {
      $form->setDefaults($defaults);
    }

    $filter['type'] = $fieldSpec->type;
    $filter['title'] = $title;
    $filter['alias'] = $fieldSpec->alias;
    $filter['size'] = $size;

    return $filter;
  }

  protected function getOperatorOptions(\Civi\DataProcessor\DataSpecification\FieldSpecification $fieldSpec) {
    return array(
      'IN' => E::ts('Is one of'),
      'NOT IN' => E::ts('Is not one of'),
      'null' => E::ts('Is empty'),
      'not null' => E::ts('Is not empty'),
      'current_user' => E::ts('Is current user'),
    );
  }


}
