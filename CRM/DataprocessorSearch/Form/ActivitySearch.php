<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

use CRM_Dataprocessor_ExtensionUtil as E;

class CRM_DataprocessorSearch_Form_ActivitySearch extends CRM_DataprocessorSearch_Form_AbstractSearch {

  /**
   * Returns the name of the default Entity
   *
   * @return string
   */
  public function getDefaultEntity() {
    return 'Contact';
  }

  /**
   * Returns the url for view of the record action
   *
   * @param $row
   *
   * @return false|string
   */
  protected function link($row) {
    $activity = civicrm_api3('Activity', 'getsingle', ['id' => $row['id'], "return" => ["target_contact_id", "source_record_id", "activity_type_id"]]);
    $activity['cid'] = reset($activity['target_contact_id']);
    unset($activity['target_contact_id']);
    unset($activity['target_contact_name']);
    unset($activity['target_contact_sort_name']);
    $activity['cxt'] = '';
    // CRM-3553
    $accessMailingReport = FALSE;
    if (!empty($activity['mailingId'])) {
      $accessMailingReport = TRUE;
    }

    $actionLinks = \CRM_Activity_Selector_Activity::actionLinks(CRM_Utils_Array::value('activity_type_id', $activity),
      CRM_Utils_Array::value('source_record_id', $activity),
      $accessMailingReport,
      CRM_Utils_Array::value('activity_id', $activity)
    );
    $link = $actionLinks[\CRM_Core_Action::VIEW];

    $values = $activity;
    $extra = isset($link['extra']) ? \CRM_Core_Action::replace($link['extra'], $values) : NULL;
    $frontend = isset($link['fe']);
    if (isset($link['qs']) && !\CRM_Utils_System::isNull($link['qs'])) {
      $urlPath = \CRM_Utils_System::url(\CRM_Core_Action::replace($link['url'], $values), \CRM_Core_Action::replace($link['qs'], $values), FALSE, NULL, TRUE, $frontend);
    }
    else {
      $urlPath = \CRM_Utils_Array::value('url', $link, '#');
    }
    return $urlPath;
  }

  /**
   * Returns the link text for view of the record action
   *
   * @param $row
   *
   * @return false|string
   */
  protected function linkText($row) {
    return E::ts('View activity');
  }

  /**
   * Return the data processor name
   *
   * @return String
   */
  protected function getDataProcessorName() {
    $dataProcessorName = str_replace('civicrm/dataprocessor_activity_search/', '', CRM_Utils_System::currentPath());
    return $dataProcessorName;
  }

  /**
   * Returns the name of the output for this search
   *
   * @return string
   */
  protected function getOutputName() {
    return 'activity_search';
  }

  /**
   * Checks whether the output has a valid configuration
   *
   * @return bool
   */
  protected function isConfigurationValid() {
    if (!isset($this->dataProcessorOutput['configuration']['activity_id_field'])) {
      return false;
    }
    return true;
  }

  /**
   * Returns the name of the ID field in the dataset.
   *
   * @return string
   */
  protected function getIdFieldName() {
    return $this->dataProcessorOutput['configuration']['activity_id_field'];
  }

  /**
   * @return string
   */
  protected function getEntityTable() {
    return 'civicrm_activity';
  }

  /**
   * Builds the list of tasks or actions that a searcher can perform on a result set.
   *
   * @return array
   */
  public function buildTaskList() {
    if (!$this->_taskList) {
      $this->_taskList = CRM_Activity_Task::permissionedTaskTitles(CRM_Core_Permission::getPermission());
    }
    return $this->_taskList;
  }

  /**
   * Return altered rows
   *
   * Save the ids into the queryParams value. So that when an action is done on the selected record
   * or on all records, the queryParams will hold all the activity ids so that in the next step only the selected record, or
   * all records are populated.
   */
  protected function retrieveEntityIds() {
    $this->dataProcessorClass->getDataFlow()->setLimit(false);
    $this->dataProcessorClass->getDataFlow()->setOffset(0);
    $this->entityIDs = [];
    $id_field = $this->getIdFieldName();
    try {
      while($record = $this->dataProcessorClass->getDataFlow()->nextRecord()) {
        if ($id_field && isset($record[$id_field])) {
          $this->entityIDs[] = $record[$id_field]->rawValue;
        }
      }
    } catch (\Civi\DataProcessor\DataFlow\EndOfFlowException $e) {
      // Do nothing
    }
    $this->_queryParams[0] = array(
      'activity_id',
      '=',
      array(
        'IN' => $this->entityIDs,
      ),
      0,
      0
    );
    $this->controller->set('queryParams', $this->_queryParams);
  }

}
