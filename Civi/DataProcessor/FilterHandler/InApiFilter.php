<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

namespace Civi\DataProcessor\FilterHandler;

use Civi\DataProcessor\Exception\InvalidConfigurationException;
use CRM_Dataprocessor_ExtensionUtil as E;

class InApiFilter extends AbstractFieldFilterHandler {

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

    $apiEntity = $this->configuration['api_entity'];
    $apiAction = $this->configuration['api_action'];
    $apiParams = isset($this->configuration['api_params']) && is_array($this->configuration['api_params'])? $this->configuration['api_params'] : array();
    $id_field = $this->configuration['value_field'];
    $label_field = $this->configuration['label_field'];
    $apiParams['return'] = array($id_field, $label_field);
    $apiParams['options']['limit'] = 0;
    unset($apiParams['options']['offset']);
    $this->fieldSpecification->options = array();
    try {
      $apiResult = civicrm_api3($apiEntity, $apiAction, $apiParams);
      foreach ($apiResult['values'] as $option) {
        if (isset($option[$id_field]) && isset($option[$label_field])) {
          $this->fieldSpecification->options[$option[$id_field]] = $option[$label_field];
        }
      }
    } catch (\CiviCRM_API3_Exception $e) {
      // Do nothing
    }
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

    $form->add('text', 'api_entity', E::ts('API Entity'), array('style' => 'min-width:250px', 'class' => 'huge'), true);

    $form->add('text', 'api_action', E::ts('API Action'), array('style' => 'min-width:250px', 'class' => 'huge'), true);

    $form->add('text', 'value_field', E::ts('Value Field'), array('style' => 'min-width:250px', 'class' => 'huge'), true);

    $form->add('text', 'label_field', E::ts('Label Field'), array('style' => 'min-width:250px', 'class' => 'huge'), true);

    $form->add('textarea', 'api_params', E::ts('API Params'), array('style' => 'min-width:250px', 'class' => 'huge'), false);


    if (isset($filter['configuration'])) {
      $configuration = $filter['configuration'];
      $defaults = array();
      if (isset($configuration['field']) && isset($configuration['datasource'])) {
        $defaults['field'] = \CRM_Dataprocessor_Utils_DataSourceFields::getSelectedFieldValue($filter['data_processor_id'], $configuration['datasource'], $configuration['field']);
      }
      $defaults['api_entity'] = $configuration['api_entity'];
      $defaults['api_action'] = $configuration['api_action'];
      $defaults['value_field'] = $configuration['value_field'];
      $defaults['label_field'] = $configuration['label_field'];
      $defaults['api_params'] = is_array($configuration['api_params']) ? json_encode($configuration['api_params'], JSON_PRETTY_PRINT) : '';
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
    return "CRM/Dataprocessor/Form/Filter/Configuration/InApiFilter.tpl";
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
    $configuration['api_entity'] = $submittedValues['api_entity'];
    $configuration['api_action'] = $submittedValues['api_action'];
    $configuration['value_field'] = $submittedValues['value_field'];
    $configuration['label_field'] = $submittedValues['label_field'];
    $configuration['api_params'] = !empty($submittedValues['api_params']) ? json_decode($submittedValues['api_params'], true) : null;
    return $configuration;
  }


}
