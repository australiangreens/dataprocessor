<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */


use Civi\DataProcessor\Output\UIFormOutputInterface;

use CRM_Dataprocessor_ExtensionUtil as E;

class CRM_DataprocessorSearch_Search implements UIFormOutputInterface {

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
    $form->add('select', 'id_field', E::ts('ID field'), $fields, true, array(
      'style' => 'min-width:250px',
      'class' => 'crm-select2 huge',
      'placeholder' => E::ts('- select -'),
    ));
    $form->add('select', 'hide_id_field', E::ts('Show ID field'), array(0=>'ID is Visible', 1=> 'ID field is hidden'));

    $form->add('select', 'hidden_fields', E::ts('Hidden fields'), $fields, false, array(
      'style' => 'min-width:250px',
      'class' => 'crm-select2 huge',
      'multiple' => true,
      'placeholder' => E::ts('- select -'),
    ));

    $form->add('wysiwyg', 'help_text', E::ts('Help text for this search'), array('rows' => 6, 'cols' => 80));
    $form->add('text', 'no_result_text', E::ts('No result text'), array('class' => 'huge'), false);
    $form->add('checkbox', 'expanded_search', E::ts('Expand criteria form initially'));
    $form->add('checkbox', 'expose_aggregate', E::ts('Expose aggregate options'));
    $form->add('checkbox', 'expose_hidden_fields', E::ts('Expose hidden field options'));

    // navigation field
    $navigationOptions = $navigation->getNavigationOptions();
    if (isset($output['configuration']['navigation_id'])) {
      $navigationPath = $navigation->getNavigationPathById($output['configuration']['navigation_id']);
      unset($navigationOptions[$navigationPath]);
    }
    $form->add('select', 'navigation_parent_path', ts('Parent Menu'), array('' => ts('- select -')) + $navigationOptions, true);

    $defaults = array();
    if ($output) {
      if (isset($output['permission'])) {
        $defaults['permission'] = $output['permission'];
      }
      if (isset($output['configuration']) && is_array($output['configuration'])) {
        if (isset($output['configuration']['id_field'])) {
          $defaults['id_field'] = $output['configuration']['id_field'];
        }
        if (isset($output['configuration']['no_result_text'])) {
          $defaults['no_result_text'] = $output['configuration']['no_result_text'];
        } else {
          $defaults['no_result_text'] = E::ts('No results');
        }
        if (isset($output['configuration']['navigation_id'])) {
          $defaults['navigation_parent_path'] = $navigation->getNavigationParentPathById($output['configuration']['navigation_id']);
        }
        if (isset($output['configuration']['hide_id_field'])) {
          $defaults['hide_id_field'] = $output['configuration']['hide_id_field'];
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
        if (isset($output['configuration']['expose_aggregate'])) {
          $defaults['expose_aggregate'] = $output['configuration']['expose_aggregate'];
        }
        if (isset($output['configuration']['expose_hidden_fields'])) {
          $defaults['expose_hidden_fields'] = $output['configuration']['expose_hidden_fields'];
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
    return "CRM/DataprocessorSearch/Form/OutputConfiguration/Search.tpl";
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
    $configuration['id_field'] = $submittedValues['id_field'];
    $configuration['navigation_parent_path'] = $submittedValues['navigation_parent_path'];
    $configuration['no_result_text'] = $submittedValues['no_result_text'];
    $configuration['hide_id_field'] = $submittedValues['hide_id_field'];
    $configuration['hidden_fields'] = $submittedValues['hidden_fields'];
    $configuration['help_text'] = $submittedValues['help_text'];
    $configuration['expanded_search'] = isset($submittedValues['expanded_search']) ? $submittedValues['expanded_search'] : false;
    $configuration['expose_aggregate'] = isset($submittedValues['expose_aggregate']) ? $submittedValues['expose_aggregate'] : false;
    $configuration['expose_hidden_fields'] = isset($submittedValues['expose_hidden_fields']) ? $submittedValues['expose_hidden_fields'] : false;
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
    return "civicrm/dataprocessor_search/{$dataProcessor['name']}";
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
    return 'CRM_DataprocessorSearch_Controller_Search';
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
    $dataProcessorName = str_replace('civicrm/dataprocessor_search/', '', $url);
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
