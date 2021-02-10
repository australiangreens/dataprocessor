<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

use CRM_Dataprocessor_ExtensionUtil as E;

class CRM_DataprocessorSearch_Task extends CRM_Core_Task {

  static $objectType = null;

  /**
   * These tasks are the core set of tasks that the user can perform
   * on a contact / group of contacts.
   *
   * @return array
   *   the set of tasks for a group of contacts
   */
  public static function tasks() {
    if (!(self::$_tasks)) {
      $dataProcessorName = str_replace('civicrm/dataprocessor_search/', '', CRM_Utils_System::currentPath());
      self::$objectType = 'dataprocessor_'.$dataProcessorName;

      self::$_tasks = array();
      parent::tasks();
    }

    return self::$_tasks;
  }

  /**
   * These tasks are the core set of tasks that the user can perform
   * on participants
   *
   * @param int $value
   *
   * @return array
   *   the set of tasks for a group of participants
   */
  public static function getTask($value) {
    static::tasks();
    if (!CRM_Utils_Array::value($value, self::$_tasks)) {
      // Children can specify a default task (eg. print), pick another if it is not valid.
      $value = key(self::$_tasks);
    }
    if ($value && isset(self::$_tasks[$value])) {
      return array(
        CRM_Utils_Array::value('class', self::$_tasks[$value]),
        CRM_Utils_Array::value('result', self::$_tasks[$value]),
      );
    }
    return array(null, null);
  }

  /**
   * Add data processor searches to the search action designer list
   *
   * @param $types
   */
  public static function searchActionDesignerTypes(&$types) {
    $dao = CRM_Core_DAO::executeQuery("
        SELECT `d`.`name`, `d`.`title`
        FROM `civicrm_data_processor` `d`
        INNER JOIN `civicrm_data_processor_output` `o` ON `o`.`data_processor_id` = `d`.`id`
        WHERE `d`.`is_active` = 1 AND `o`.`type` = 'search'");
    while ($dao->fetch()) {
      $types['dataprocessor_'.$dao->name] = array(
        'title' => E::ts('Data processor %1', array(1=>$dao->title)),
        'class' => 'CRM_DataprocessorSearch_Form_Task_SearchActionDesigner',
        'id_field_title' => E::ts('ID'),
      );
    }
  }

}
