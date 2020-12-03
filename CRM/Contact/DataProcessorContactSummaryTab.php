<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

use CRM_Dataprocessor_ExtensionUtil as E;
use Civi\DataProcessor\Output\UIFormOutputInterface;
use Civi\DataProcessor\ProcessorType\AbstractProcessorType;
use Civi\DataProcessor\DataFlow\InMemoryDataFlow\SimpleFilter;
use Civi\DataProcessor\DataFlow\InMemoryDataFlow;
use Civi\DataProcessor\DataFlow\SqlDataFlow;
use Civi\DataProcessor\DataFlow\SqlDataFlow\SimpleWhereClause;

class CRM_Contact_DataProcessorContactSummaryTab implements UIFormOutputInterface {

  /**
   * Implements hook_civicrm_tabset().
   *
   * Adds the data processor out to the contact summary tabs.
   *
   * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_tabset/
   *
   * @param $tabsetName
   * @param $tabs
   * @param $context
   */
  public static function hookTabset($tabsetName, &$tabs, $context) {
    if ($tabsetName != 'civicrm/contact/view') {
      return;
    }
    $contactId = $context['contact_id'];

    $factory = dataprocessor_get_factory();
    // Check whether the factory exists. Usually just after
    // installation the factory does not exists but then no
    // outputs exists either. So we can safely return this function.
    if (!$factory) {
      return;
    }

    $maxWeight = 0;
    foreach($tabs as $tab) {
      if (isset($tab['weight']) && $tab['weight'] > $maxWeight) {
        $maxWeight = $tab['weight'];
      }
    }
    $maxWeight++;

    $sql = "SELECT o.*, d.name as data_processor_name
            FROM civicrm_data_processor d
            INNER JOIN civicrm_data_processor_output o ON d.id = o.data_processor_id
            WHERE d.is_active = 1 AND o.type = 'contact_summary_tab'";
    $dao = CRM_Core_DAO::executeQuery($sql);
    while($dao->fetch()) {
      $outputClass = $factory->getOutputByName($dao->type);
      if (!$outputClass instanceof CRM_Contact_DataProcessorContactSummaryTab) {
        continue;
      }
      $output = civicrm_api3('DataProcessorOutput', 'getsingle', ['id' => $dao->id]);
      $dataprocessor = civicrm_api3('DataProcessor', 'getsingle', ['id' => $dao->data_processor_id]);
      if (!$outputClass->checkUIPermission($output, $dataprocessor)) {
        continue;
      }

      $tab = [
        'id' => 'dataprocessor_' . $dataprocessor['name'],
        'title' => $outputClass->getTitleForUiLink($output, $dataprocessor),
        'icon' => $outputClass->getIconForUiLink($output, $dataprocessor),
        'count' => $outputClass->getCount($contactId, $output, $dataprocessor),
        'url' => CRM_Utils_System::url('civicrm/dataprocessor/page/contactsummary', array('contact_id' => $context['contact_id'], 'data_processor' => $dataprocessor['name'], 'reset' => 1, 'force' => 1)),
        'class' => '',
      ];
      if (isset($output['configuration']['weight']) && strlen($output['configuration']['weight']) && is_numeric($output['configuration']['weight'])) {
        $tab['weight'] = $output['configuration']['weight'];
        if ($tab['weight'] > $maxWeight) {
          $maxWeight = $tab['weight'];
        }
      }
      else {
        $tab['weight'] = $maxWeight;
        $maxWeight++;
      }
      $tabs[$tab['id']] = $tab;
    }
  }

  /**
   * Returns true when this output has additional configuration
   *
   * @return bool
   */
  public function hasConfiguration() {
    return true;
  }

  /**
   * When this output type has additional configuration you can add
   * the fields on the form with this function.
   *
   * @param \CRM_Core_Form $form
   * @param array $output
   */
  public function buildConfigurationForm(\CRM_Core_Form $form, $output = []) {
    $fieldSelect = \CRM_Dataprocessor_Utils_DataSourceFields::getAvailableFilterFieldsInDataSources($output['data_processor_id']);
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

    $form->add('select', 'contact_id_field', E::ts('Contact ID field'), $fieldSelect, true, array(
      'style' => 'min-width:250px',
      'class' => 'crm-select2 huge',
      'placeholder' => E::ts('- select -'),
    ));

    $form->add('select', 'hidden_fields', E::ts('Hidden fields'), $fields, false, array(
      'style' => 'min-width:250px',
      'class' => 'crm-select2 huge',
      'multiple' => true,
      'placeholder' => E::ts('- select -'),
    ));

    $form->add('wysiwyg', 'help_text', E::ts('Help text for this tab'), array('rows' => 6, 'cols' => 80));
    $form->add('wysiwyg', 'no_result_text', E::ts('No Result Text'), array('rows' => 2, 'class' => 'huge'), false);
    $form->add('text', 'weight', E::ts('Weight'));
    $form->add('text', 'default_limit', E::ts('Default Limit'));


    $defaults = array();
    if ($output) {
      if (isset($output['permission'])) {
        $defaults['permission'] = $output['permission'];
      }
      if (isset($output['configuration']) && is_array($output['configuration'])) {
        if (isset($output['configuration']['help_text'])) {
          $defaults['help_text'] = $output['configuration']['help_text'];
        }
        if (isset($output['configuration']['no_result_text'])) {
          $defaults['no_result_text'] = $output['configuration']['no_result_text'];
        }
        if (isset($output['configuration']['contact_id_field'])) {
          $defaults['contact_id_field'] = $output['configuration']['contact_id_field'];
        }
        if (isset($output['configuration']['hidden_fields'])) {
          $defaults['hidden_fields'] = $output['configuration']['hidden_fields'];
        }
        if (isset($output['configuration']['default_limit'])) {
          $defaults['default_limit'] = $output['configuration']['default_limit'];
        }
        if (isset($output['configuration']['weight'])) {
          $defaults['weight'] = $output['configuration']['weight'];
        }
      }
    }
    if (!isset($defaults['permission'])) {
      $defaults['permission'] = 'access CiviCRM';
    }
    if (!isset($defaults['no_result_text'])) {
      $defaults['no_result_text'] = E::ts('No records');
    }
    if (!isset($defaults['default_limit'])) {
      $defaults['default_limit'] = 25;
    }
    $form->setDefaults($defaults);
  }

  /**
   * When this output type has configuration specify the template file name
   * for the configuration form.
   *
   * @return false|string
   */
  public function getConfigurationTemplateFileName() {
    return "CRM/Contact/Form/OutputConfiguration/DataProcessorContactSummaryTab.tpl";
  }

  /**
   * Process the submitted values and create a configuration array
   *
   * @param $submittedValues
   * @param array $output
   *
   * @return array $output
   * @throws \Exception
   */
  public function processConfiguration($submittedValues, &$output) {
    $output['permission'] = $submittedValues['permission'];
    $configuration['contact_id_field'] = $submittedValues['contact_id_field'];
    $configuration['hidden_fields'] = $submittedValues['hidden_fields'];
    $configuration['help_text'] = $submittedValues['help_text'];
    $configuration['no_result_text'] = $submittedValues['no_result_text'];
    $configuration['weight'] = $submittedValues['weight'];
    $configuration['default_limit'] = $submittedValues['default_limit'];
    return $configuration;
  }

  /**
   * This function is called prior to removing an output
   *
   * @param array $output
   * @return void
   * @throws \Exception
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
    return "civicrm/dataprocessor_contact_summary/{$dataProcessor['name']}";
  }

  /**
   * Returns the data processor name from a url.
   *
   * @param $url
   *
   * @return string
   */
  public function getDataProcessorNameFromUrl($url) {
    return str_replace('civicrm/dataprocessor_contact_summary/', '', $url);
  }

  /**
   * Returns the name of the contact id field.
   *
   * @param $config
   *
   * @return string|false
   */
  public function getContactIdFieldNameFromConfig($config) {
    return false;
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
    return 'CRM_Contact_Form_DataProcessorContactSummaryTab';
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

  public function getCount($contact_id, $output, $dataProcessor) {
    $dataProcessorClass = $this->loadDataProcessor($contact_id, $output, $dataProcessor);
    return $dataProcessorClass->getDataFlow()->recordCount();
  }

  /**
   * @param $contact_id
   * @param $dataProcessor
   *
   * @return \Civi\DataProcessor\ProcessorType\AbstractProcessorType
   * @throws \Exception
   */
  protected function loadDataProcessor($contact_id, $output, $dataProcessor) {
    $dataProcessorClass = \CRM_Dataprocessor_BAO_DataProcessor::dataProcessorToClass($dataProcessor);
    return self::alterDataProcessor($contact_id, $output, $dataProcessorClass);
  }

  /**
   * @param $contact_id
   * @param $output
   * @param AbstractProcessorType $dataProcessorClass
   *
   * @return AbstractProcessorType
   * @throws \Civi\DataProcessor\Exception\DataSourceNotFoundException
   * @throws \Civi\DataProcessor\Exception\FieldNotFoundException
   */
  public static function alterDataProcessor($contact_id, $output, AbstractProcessorType $dataProcessorClass) {
    list($datasource_name, $field_name) = explode('::', $output['configuration']['contact_id_field'], 2);
    $dataSource = $dataProcessorClass->getDataSourceByName($datasource_name);
    if (!$dataSource) {
      throw new \Civi\DataProcessor\Exception\DataSourceNotFoundException(E::ts("Requires data source '%1' which could not be found. Did you rename or deleted the data source?", array(1=>$datasource_name)));
    }
    $fieldSpecification  =  $dataSource->getAvailableFilterFields()->getFieldSpecificationByAlias($field_name);
    if (!$fieldSpecification) {
      $fieldSpecification  =  $dataSource->getAvailableFilterFields()->getFieldSpecificationByName($field_name);
    }
    if (!$fieldSpecification) {
      throw new \Civi\DataProcessor\Exception\FieldNotFoundException(E::ts("Requires a field with the name '%1' in the data source '%2'. Did you change the data source type?", array(
        1 => $field_name,
        2 => $datasource_name
      )));
    }

    $fieldSpecification = clone $fieldSpecification;
    $fieldSpecification->alias = 'contact_summary_tab_contact_id';
    $dataFlow = $dataSource->ensureField($fieldSpecification);
    if ($dataFlow && $dataFlow instanceof SqlDataFlow) {
      $whereClause = new SimpleWhereClause($dataFlow->getName(), $fieldSpecification->name, '=', $contact_id, $fieldSpecification->type);
      $dataFlow->addWhereClause($whereClause);
    } elseif ($dataFlow && $dataFlow instanceof InMemoryDataFlow) {
      $filterClass = new SimpleFilter($fieldSpecification->name, '=', $contact_id);
      $dataFlow->addFilter($filterClass);
    }
    return $dataProcessorClass;
  }

}
