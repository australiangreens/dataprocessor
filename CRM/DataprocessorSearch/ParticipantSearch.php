<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

use Civi\DataProcessor\Output\UIFormOutputInterface;

use CRM_Dataprocessor_ExtensionUtil as E;

class CRM_DataprocessorSearch_ParticipantSearch implements UIFormOutputInterface {

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
  public function buildConfigurationForm(\CRM_Core_Form $form, $output=array()) {
    \Civi\DataProcessor\Output\UIOutputHelper::fixBackwardsCompatibility($output);
    $navigation = CRM_Dataprocessor_Utils_Navigation::singleton();
    $dataProcessor = civicrm_api3('DataProcessor', 'getsingle', array('id' => $output['data_processor_id']));
    $dataProcessorClass = \CRM_Dataprocessor_BAO_DataProcessor::dataProcessorToClass($dataProcessor);
    $fields = array();
    foreach($dataProcessorClass->getDataFlow()->getOutputFieldHandlers() as $outputFieldHandler) {
      $field = $outputFieldHandler->getOutputFieldSpecification();
      $fields[$field->alias] = $field->title;
    }

    $form->add('select','permission', E::ts('Permission'), \CRM_Core_Permission::basicPermissions(), true, array(
      'style' => 'min-width:250px',
      'class' => 'crm-select2 huge',
      'placeholder' => E::ts('- select -'),
    ));
    $form->add('select', 'participant_id_field', E::ts('Participant ID field'), $fields, true, array(
      'style' => 'min-width:250px',
      'class' => 'crm-select2 huge',
      'placeholder' => E::ts('- select -'),
    ));
    $form->add('select', 'hide_id_field', E::ts('Show participant ID field'), array(0=>'Participant ID is Visible', 1=> 'Participant ID is hidden'));

    $form->add('select', 'hidden_fields', E::ts('Hidden fields'), $fields, false, array(
      'style' => 'min-width:250px',
      'class' => 'crm-select2 huge',
      'multiple' => true,
      'placeholder' => E::ts('- select -'),
    ));

    $form->add('wysiwyg', 'help_text', E::ts('Help text for this search'), array('rows' => 6, 'cols' => 80));
    $form->add('text', 'no_result_text', E::ts('No result text'), array('class' => 'huge'), false);
    $form->add('checkbox', 'expanded_search', E::ts('Expand criteria form initially'));

    // navigation field
    $navigationOptions = $navigation->getNavigationOptions();
    $form->add('select', 'navigation_parent_path', ts('Parent Menu'), array('' => ts('- select -')) + $navigationOptions, true);

    $defaults = array();
    if ($output) {
      if (isset($output['permission'])) {
        $defaults['permission'] = $output['permission'];
      }
      if (isset($output['configuration']) && is_array($output['configuration'])) {
        if (isset($output['configuration']['participant_id_field'])) {
          $defaults['participant_id_field'] = $output['configuration']['participant_id_field'];
        }
        if (isset($output['configuration']['navigation_parent_path'])) {
          $defaults['navigation_parent_path'] = $output['configuration']['navigation_parent_path'];
        }
        if (isset($output['configuration']['hide_id_field'])) {
          $defaults['hide_id_field'] = $output['configuration']['hide_id_field'];
        }
        if (isset($output['configuration']['no_result_text'])) {
          $defaults['no_result_text'] = $output['configuration']['no_result_text'];
        } else {
          $defaults['no_result_text'] = E::ts('No results');
        }
        if (isset($output['configuration']['hidden_fields'])) {
          $defaults['hidden_fields'] = $output['configuration']['hidden_fields'];
        }
        if (isset($output['configuration']['help_text'])) {
          $defaults['help_text'] = $output['configuration']['help_text'];
        }
        if (isset($output['configuration']['expanded_search'])) {
          $defaults['expanded_search'] = $output['configuration']['expanded_search'];
        }
      }
    }
    if (!isset($defaults['permission'])) {
      $defaults['permission'] = 'access CiviCRM';
    }
    $form->setDefaults($defaults);
  }

  /**
   * When this filter type has configuration specify the template file name
   * for the configuration form.
   *
   * @return false|string
   */
  public function getConfigurationTemplateFileName() {
    return "CRM/DataprocessorSearch/Form/OutputConfiguration/ParticipantSearch.tpl";
  }


  /**
   * Process the submitted values and create a configuration array
   *
   * @param $submittedValues
   * @param array $output
   * @return array
   */
  public function processConfiguration($submittedValues, &$output) {
    $output['permission'] = $submittedValues['permission'];
    $configuration['participant_id_field'] = $submittedValues['participant_id_field'];
    $configuration['no_result_text'] = $submittedValues['no_result_text'];
    $configuration['navigation_parent_path'] = $submittedValues['navigation_parent_path'];
    $configuration['hide_id_field'] = $submittedValues['hide_id_field'];
    $configuration['hidden_fields'] = $submittedValues['hidden_fields'];
    $configuration['help_text'] = $submittedValues['help_text'];
    $configuration['expanded_search'] = isset($submittedValues['expanded_search']) ? $submittedValues['expanded_search'] : false;
    return $configuration;
  }

  /**
   * This function is called prior to removing an output
   *
   * @param array $output
   * @return void
   */
  public function deleteOutput($output) {
    // Do nothing
  }

  /**
   * Returns the url for the page/form this output will show to the user
   *
   * @param array $output
   * @param array $dataProcessor
   * @return string
   */
  public function getUrlToUi($output, $dataProcessor) {
    return "civicrm/dataprocessor_participant_search/{$dataProcessor['name']}";
  }

  /**
   * Returns the url for the page/form this output will show to the user
   *
   * @param array $output
   * @param array $dataProcessor
   * @return string
   */
  public function getTitleForUiLink($output, $dataProcessor) {
    return $dataProcessor['title'];
  }

  /**
   * Returns the url for the page/form this output will show to the user
   *
   * @param array $output
   * @param array $dataProcessor
   * @return string|false
   */
  public function getIconForUiLink($output, $dataProcessor) {
    return false;
  }

  /**
   * Returns the callback for the UI.
   *
   * @return string
   */
  public function getCallbackForUi() {
    return 'CRM_DataprocessorSearch_Controller_ParticipantSearch';
  }

  /**
   * Checks whether the current user has access to this output
   *
   * @param array $output
   * @param array $dataProcessor
   * @return bool
   */
  public function checkUIPermission($output, $dataProcessor) {
    return CRM_Core_Permission::check(array(
      $output['permission']
    ));
  }

  /**
   * Returns the data processor name from a url.
   *
   * @param $url
   *
   * @return string
   */
  public function getDataProcessorNameFromUrl($url) {
    $dataProcessorName = str_replace('civicrm/dataprocessor_participant_search/', '', $url);
    return $dataProcessorName;
  }

  /**
   * Returns the name of the contact id field.
   *
   * @param $config
   * @return string
   */
  public function getContactIdFieldNameFromConfig($config) {
    return false;
  }

}
