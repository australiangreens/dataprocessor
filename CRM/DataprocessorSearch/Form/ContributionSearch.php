<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

use CRM_Dataprocessor_ExtensionUtil as E;

class CRM_DataprocessorSearch_Form_ContributionSearch extends CRM_DataprocessorSearch_Form_AbstractSearch {

  /**
   * Returns the name of the default Entity
   *
   * @return string
   */
  public function getDefaultEntity() {
    return 'Contribution';
  }

  /**
   * Returns the url for view of the record action
   *
   * @param $row
   *
   * @return false|string
   */
  protected function link($row) {
    return CRM_Utils_System::url('civicrm/contact/view/contribution', 'reset=1&id='.$row['id'].'&cid='.$row['id'].'&action=view');
  }

  /**
   * Returns the link text for view of the record action
   *
   * @param $row
   *
   * @return false|string
   */
  protected function linkText($row) {
    return E::ts('View contribution');
  }

  /**
   * Checks whether the output has a valid configuration
   *
   * @return bool
   */
  protected function isConfigurationValid() {
    if (!isset($this->dataProcessorOutput['configuration']['contribution_id_field'])) {
      return false;
    }
    return true;
  }

  /**
   * Return the data processor ID
   *
   * @return String
   */
  protected function getDataProcessorName() {
    $dataProcessorName = str_replace('civicrm/dataprocessor_contribution_search/', '', CRM_Utils_System::currentPath());
    return $dataProcessorName;
  }

  /**
   * Returns the name of the output for this search
   *
   * @return string
   */
  protected function getOutputName() {
    return 'contribution_search';
  }

  /**
   * Returns the name of the ID field in the dataset.
   *
   * @return string
   */
  protected function getIdFieldName() {
    return $this->dataProcessorOutput['configuration']['contribution_id_field'];
  }

  /**
   * @return string
   */
  protected function getEntityTable() {
    return 'civicrm_contribution';
  }

  /**
   * Returns whether we want to use the prevnext cache.
   * @return bool
   */
  protected function usePrevNextCache() {
    return false;
  }

  /**
   * Builds the list of tasks or actions that a searcher can perform on a result set.
   *
   * @return array
   */
  public function buildTaskList() {
    if (!$this->_taskList) {
      $taskParams['softCreditFiltering'] = FALSE;
      $this->_taskList = CRM_Contribute_Task::permissionedTaskTitles(CRM_Core_Permission::getPermission(), $taskParams);
    }
    return $this->_taskList;
  }

  /**
   * Return altered rows
   *
   * Save the ids into the queryParams value. So that when an action is done on the selected record
   * or on all records, the queryParams will hold all the activity ids so that in
   * the next step only the selected record, or all records are populated.
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
    } catch (\Civi\DataProcessor\Exception\DataFlowException $e) {
      // Do nothing
    }
    $this->_queryParams[0] = array(
      'contribution_id',
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
