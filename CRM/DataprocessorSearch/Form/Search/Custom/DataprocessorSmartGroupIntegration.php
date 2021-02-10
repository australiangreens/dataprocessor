<?php

use Civi\DataProcessor\Output\UIFormOutputInterface;

/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

/**
 * Class to hack around saved search (smart groups).
 * What we do is the following:
 *  1. When running a data processor search the method 'setDataProcessorAndFormValues'
 *     should be called so that we know we are running in data processor search
 *  2. The event listener 'alterSavedSearch' is called when the user press an action which creates
 *     a smart group (such as create smart group or send bulk mail). We then check whether we are
 *     running a data processor search and if we do so we store the form values and the ID of this
 *     custom search class.
 *  3. When the smart group is populated this custom search class is used and the function
 *     'contactIDs' is called. That function returns a query with all contact ids.
 *     In this function we can do run the data processor again. And that is why this class is extending the custom search class.
 *
 * Requires to implement the following event listeners in the config hook:
 *
 *   \Civi::dispatcher()->addListener('civi.dao.preUpdate', ['CRM_DataprocessorSearch_Form_Search_Custom_DataprocessorSmartGroupIntegration', 'alterSavedSearch']);
 *   \Civi::dispatcher()->addListener('civi.dao.preInsert', ['CRM_DataprocessorSearch_Form_Search_Custom_DataprocessorSmartGroupIntegration', 'alterSavedSearch']);
 *
 */
class CRM_DataprocessorSearch_Form_Search_Custom_DataprocessorSmartGroupIntegration extends CRM_Contact_Form_Search_Custom_Base {

  /**
   * @var array containing the form values to be saved when saving a saved search.
   */
  private static $formValues;

  /**
   * @var string the name of the data processor. This needs to be set when running
   * a data processor search.
   */
  private static $dataProcessorName;

  /**
   * This is an symfony event for preUpdate which is run when just before saved search
   * is saved.
   * @param \Civi\Core\DAO\Event\PostUpdate $event
   */
  public static function alterSavedSearch(\Civi\Core\DAO\Event\PostUpdate $event) {
    // Check whether we are saving saved search.
    // And check whether a data processor search has been run.
    if ($event->object instanceof \CRM_Contact_DAO_SavedSearch && self::$dataProcessorName) {
      $custom_search_id = civicrm_api3('OptionValue', 'getvalue', [
        'return' => 'value',
        'option_group_id' => "custom_search",
        'name' => "CRM_DataprocessorSearch_Form_Search_Custom_DataprocessorSmartGroupIntegration",
      ]);
      $event->object->search_custom_id = $custom_search_id;
      self::$formValues['customSearchID'] = $custom_search_id; // Store also the custom search id in the formValues.
      $event->object->form_values = serialize(self::$formValues);
      \CRM_Core_DAO::executeQuery("UPDATE `{$event->object->tableName()}` SET `form_values` = %1, `search_custom_id` = %2 WHERE `id` = %3", [
        1 => [$event->object->form_values, 'String'],
        2 => [$event->object->search_custom_id, 'Integer'],
        3 => [$event->object->id, 'Integer']
      ]);
    }
  }

  /**
   * Helper function to be run from the data processor search controller to set the meta
   * data needed to save a saved search after a data processor search
   *
   * Example call in CRM_Contact_Controller_DataProcessorContactSearch
   *
   * ...
   * public function run() {
   * ...
   *   if (!$this->_pages[$pageName] instanceof CRM_DataprocessorSearch_Form_ContactSearch) {
   *     CRM_DataprocessorSearch_Form_Search_Custom_DataprocessorSmartGroupIntegration::setDataProcessorAndFormValues('contact_search', $this->get('formValues'), CRM_Utils_System::currentPath());
   *     ...
   *   }
   * ...
   * }
   *
   * @param $outputType
   * @param $formValues
   * @param $currentUrl
   */
  public static function setDataProcessorAndFormValues($outputType, $formValues, $currentUrl) {
    $factory = dataprocessor_get_factory();
    $outputClass = $factory->getOutputByName($outputType);
    if ($outputClass instanceof UIFormOutputInterface) {
      self::$dataProcessorName = $outputClass->getDataProcessorNameFromUrl($currentUrl);
      $formValues['_dataprocessor_name'] = self::$dataProcessorName;
      $formValues['_dataprocessor_output_type'] = $outputType;
      self::$formValues = $formValues;
    }
  }

  /**
   * When civicrm/contact/search/custom?ssID=... is called
   * check whether we are calling saved data processor search and if so
   * redirect it to the right url. As the custom search will fail.
   *
   * @param $currentUrl
   * @throws \CRM_Core_Exception
   */
  public static function redirectCustomSearchToDataProcessorSearch($currentUrl) {
    if (stripos($currentUrl, 'civicrm/contact/search/custom')===false || !CRM_Utils_Request::retrieve('ssID', 'Integer')) {
      return;
    }
    // Load saved search to check whether this is a data processor search.
    $savedSearchDao = new CRM_Contact_DAO_SavedSearch();
    $savedSearchDao->id = CRM_Utils_Request::retrieve('ssID', 'Integer');
    if (!$savedSearchDao->find(TRUE) || empty($savedSearchDao->form_values)) {
      return;
    }
    $formValues = unserialize($savedSearchDao->form_values);
    if (!is_array($formValues) || !isset($formValues['_dataprocessor_name'])) {
      return;
    }
    $dataProcessorName = $formValues['_dataprocessor_name'];
    $dataProcessorOutputType = $formValues['_dataprocessor_output_type'];
    if (!$dataProcessorName || !$dataProcessorOutputType) {
      return;
    }

    list($dataProcessor, $dataProcessorClass, $dataProcessorOutput, $outputClass) = self::loadDataProcessor($dataProcessorName, $dataProcessorOutputType);
    if ($outputClass instanceof UIFormOutputInterface) {
      $url = $outputClass->getUrlToUi($dataProcessorOutput, $dataProcessor);
      $url = CRM_Utils_System::url($url, 'reset=1&ssID='.$savedSearchDao->id);
      CRM_Utils_System::redirect($url);
    }
  }

  /**
   * Returns a query with only the contact_id as the column. Those contact ids will
   * then be inserted into the group_contact_cache at the moment a smart group is rebuild.
   *
   * This is the little hack to update a smart group with data from a data processor.
   *
   * @param int $offset
   * @param int $rowcount
   * @param null $sort
   * @param false $returnSQL
   *
   * @return string
   * @throws \CiviCRM_API3_Exception
   * @throws \Civi\DataProcessor\DataFlow\InvalidFlowException
   */
  public function contactIDs($offset = 0, $rowcount = 0, $sort = NULL, $returnSQL = FALSE) {
    // dgcc = dataprocessor group contact cache
    // the category could not be longer than 12 characters
    $groupContactsTempTable = CRM_Utils_SQL_TempTable::build()->setCategory('dgcc')->setMemory();
    $tempTableName = $groupContactsTempTable->getName();
    $groupContactsTempTable->createWithColumns('id int');

    $this->runDataProcessorAndStoreInTempTable($tempTableName);

    return "SELECT `id` AS `contact_id` FROM `{$tempTableName}` `contact_a`";
  }

  protected function runDataProcessorAndStoreInTempTable($table_name) {
    $max_insert_statements = 100;

    $dataProcessorName = $this->_formValues['_dataprocessor_name'];
    $dataProcessorOutputType = $this->_formValues['_dataprocessor_output_type'];
    if (!$dataProcessorName || !$dataProcessorOutputType) {
      return;
    }

    list($dataProcessor, $dataProcessorClass, $dataProcessorOutput, $outputClass) = self::loadDataProcessor($dataProcessorName, $dataProcessorOutputType);
    if (!$outputClass instanceof UIFormOutputInterface) {
      return;
    }
    $contact_id_field = $outputClass->getContactIdFieldNameFromConfig($dataProcessorOutput['configuration']);
    if (!$contact_id_field) {
      return;
    }

    CRM_Dataprocessor_Form_Output_AbstractUIOutputForm::applyFilters($dataProcessorClass, $this->_formValues);
    $records = $dataProcessorClass->getDataFlow()->allRecords();
    $insertStatements = [];
    foreach($records as $record) {
      $insertStatements[] = '('.$record[$contact_id_field]->rawValue.')';
      if (count($insertStatements) > $max_insert_statements) {
        CRM_Core_DAO::executeQuery("INSERT INTO `{$table_name}` (id) VALUES ".implode(", ", $insertStatements));
        $insertStatements = [];
      }
    }
    if (count($insertStatements)) {
      CRM_Core_DAO::executeQuery("INSERT INTO `{$table_name}` (id) VALUES ".implode(", ", $insertStatements));
    }
  }

  protected static function loadDataProcessor($dataProcessorName, $dataProcessorOutputType) {
    $factory = dataprocessor_get_factory();
    $dataProcessor = false;
    $dataProcessorClass = false;
    $outputClass = false;
    $dataProcessorOutput = false;

    $sql = "
        SELECT civicrm_data_processor.id as data_processor_id,  civicrm_data_processor_output.id AS output_id
        FROM civicrm_data_processor
        INNER JOIN civicrm_data_processor_output ON civicrm_data_processor.id = civicrm_data_processor_output.data_processor_id
        WHERE is_active = 1 AND civicrm_data_processor.name = %1 AND civicrm_data_processor_output.type = %2
      ";
    $params[1] = [$dataProcessorName, 'String'];
    $params[2] = [$dataProcessorOutputType, 'String'];
    $dao = CRM_Dataprocessor_BAO_DataProcessor::executeQuery($sql, $params, TRUE, 'CRM_Dataprocessor_BAO_DataProcessor');
    if (!$dao->fetch() || !$dao->data_processor_id) {
      return [$dataProcessor, $dataProcessorClass, $dataProcessorOutput, $outputClass];
    }

    $dataProcessor = civicrm_api3('DataProcessor', 'getsingle', array('id' => $dao->data_processor_id));
    $dataProcessorClass = \CRM_Dataprocessor_BAO_DataProcessor::dataProcessorToClass($dataProcessor);
    $dataProcessorOutput = civicrm_api3('DataProcessorOutput', 'getsingle', array('id' => $dao->output_id));
    $outputClass = $factory->getOutputByName($dataProcessorOutputType);

    return [$dataProcessor, $dataProcessorClass, $dataProcessorOutput, $outputClass];
  }

}
