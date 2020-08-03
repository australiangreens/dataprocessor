<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

use Civi\DataProcessor\DataFlow\SqlDataFlow;
use Civi\DataProcessor\DataFlow\InMemoryDataFlow;
use Civi\DataProcessor\DataFlow\SqlDataFlow\SimpleWhereClause;
use Civi\DataProcessor\Exception\DataSourceNotFoundException;
use Civi\DataProcessor\Exception\FieldNotFoundException;
use Civi\DataProcessor\ProcessorType\AbstractProcessorType;
use CRM_Dataprocessor_ExtensionUtil as E;

class CRM_Contact_Form_DataProcessorContactSummaryTab extends CRM_DataprocessorSearch_Form_AbstractSearch {
  public function buildQuickform() {
    parent::buildQuickform();
    $this->add('hidden', 'data_processor');
    $this->setDefaults(array('data_processor' => $this->getDataProcessorName()));
    $this->assign('no_result_text', $this->dataProcessorOutput['configuration']['no_result_text']);
  }

  /**
   * Returns the default row limit.
   *
   * @return int
   */
  protected function getDefaultLimit() {
    $defaultLimit = 25;
    if (!empty($this->dataProcessorOutput['configuration']['default_limit'])) {
      $defaultLimit = $this->dataProcessorOutput['configuration']['default_limit'];
    }
    return $defaultLimit;
  }


  /**
   * Returns the name of the ID field in the dataset.
   *
   * @return string
   */
  protected function getIdFieldName() {
    return false;
  }

  /**
   * @return false|string
   */
  protected function getEntityTable() {
    return false;
  }

  /**
   * Returns the url for view of the record action
   *
   * @param $row
   *
   * @return false|string
   */
  protected function link($row) {
    return false;
  }

  /**
   * Returns the link text for view of the record action
   *
   * @param $row
   *
   * @return false|string
   */
  protected function linkText($row) {
    return false;
  }

  /**
   * Return the data processor ID
   *
   * @return String
   */
  protected function getDataProcessorName() {
    $dataProcessorName = str_replace('civicrm/dataprocessor_contact_summary/', '', CRM_Utils_System::currentPath());
    return $dataProcessorName;
  }

  /**
   * Returns the name of the output for this search
   *
   * @return string
   */
  protected function getOutputName() {
    return 'contact_summary_tab';
  }

  /**
   * Checks whether the output has a valid configuration
   *
   * @return bool
   */
  protected function isConfigurationValid() {
    return TRUE;
  }

  /**
   * Add buttons for other outputs of this data processor
   */
  protected function addExportOutputs() {
    // Don't add exports
  }

  /**
   * Alter the data processor.
   *
   * Use this function in child classes to add for example additional filters.
   *
   * E.g. The contact summary tab uses this to add additional filtering on the contact id of
   * the displayed contact.
   *
   * @param \Civi\DataProcessor\ProcessorType\AbstractProcessorType $dataProcessorClass
   */
  protected function alterDataProcessor(AbstractProcessorType $dataProcessorClass) {
    $cid = CRM_Utils_Request::retrieveValue('contact_id', 'Integer', NULL, TRUE);
    CRM_Contact_DataProcessorContactSummaryTab::alterDataProcessor($cid, $this->dataProcessorOutput, $dataProcessorClass);
  }
}
