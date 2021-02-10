<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

namespace Civi\DataProcessor\FilterHandler;

use Civi\DataProcessor\Exception\InvalidConfigurationException;
use CRM_Dataprocessor_ExtensionUtil as E;

class ActivityFilter extends AbstractFieldFilterHandler {

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

    $optionValueApi = civicrm_api3('OptionValue', 'get', array('option_group_id' => "activity_type", 'options' => array('limit' => 0)));

    $activityType = array();
    foreach($optionValueApi['values'] as $option) {
      $activityType[$option['id']] = $option['label'];
    }

    $form->add('select', 'limit_activity_types', E::ts('Limit to Contacts of activity types'), $activityType, false, array(
      'style' => 'min-width:250px',
      'class' => 'crm-select2 huge',
      'placeholder' => E::ts('- Show all activity types -'),
      'multiple' => true,
    ));

    if (isset($filter['configuration'])) {
      $configuration = $filter['configuration'];
      $defaults = array();
      if (isset($configuration['field']) && isset($configuration['datasource'])) {
        $defaults['field'] = \CRM_Dataprocessor_Utils_DataSourceFields::getSelectedFieldValue($filter['data_processor_id'], $configuration['datasource'], $configuration['field']);
      }
      if (isset($configuration['limit_activity_types'])) {
        $defaults['limit_activity_types'] = $configuration['limit_activity_types'];
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
    return "CRM/Dataprocessor/Form/Filter/Configuration/ActivityFilter.tpl";
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
    $configuration['limit_activity_types'] = isset($submittedValues['limit_activity_types']) ? $submittedValues['limit_activity_types'] : false;
    return $configuration;
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

    $props = array(
      'placeholder' => E::ts('Select a Contact'),
      'entity' => 'Contact',
      'create' => false,
      'multiple' => true,
      'style' => $minWidth,
      'class' => $sizeClass,
    );
    if (!empty($this->configuration['limit_activity_types'])) {
      $optionValueApi = civicrm_api3('OptionValue', 'get', array('option_group_id' => "activity_type", 'options' => array('limit' => 0)));

      $activityType = array();
      foreach($optionValueApi['values'] as $option) {
        $activityType[$option['id']] = $option['label'];
      }
      $param_activity_type = array();

      foreach($this->configuration['limit_activity_types'] as $type_id){
        array_push($param_activity_type, $activityType[$type_id]);
      }

      $result = civicrm_api3('ActivityContact', 'get', [
        'activity_id.activity_type_id'  => ['IN' => $param_activity_type],
        'options' => ['limit' => 0],
      ]);

      $contact_id_list = array();
      foreach ($result['values'] as $contact) {
        array_push($contact_id_list, $contact['contact_id']);
      }
      $contact_id_list = array_values(array_unique($contact_id_list));
      $props['api'] = ['params' => ['contact_id' => ['IN' => $contact_id_list]]];
    }
    $props['select'] = ['minimumInputLength' => 0];
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
    $filter['size'] = $size;
    $filter['alias'] = $fieldSpec->alias;

    return $filter;
  }

  protected function getOperatorOptions(\Civi\DataProcessor\DataSpecification\FieldSpecification $fieldSpec) {
    return array(
      'IN' => E::ts('Is one of'),
      'NOT IN' => E::ts('Is not one of'),
      'null' => E::ts('Is empty'),
    );
  }


}
