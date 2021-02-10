<?php

use CRM_Dataprocessor_ExtensionUtil as E;

/**
 * Main page for Data Processor Output dashlet
 *
 */
class CRM_DataprocessorDashlet_Page_Dashlet extends CRM_Core_Page {

  /**
   * @var int
   */
  private $outputId;

  /**
   * @var String
   */
  private $dataProcessorName;

  /**
   * @var array
   */
  private $dataProcessor;

  /**
   * @var Civi\DataProcessor\ProcessorType\AbstractProcessorType
   */
  private $dataProcessorClass;

  protected $context;

  /**
   * Pre Process the results
   *
   * @return void
   */

  protected function preProcess() {
    $this->dataProcessorName = CRM_Utils_Request::retrieve('data_processor', 'String');
    $this->context = CRM_Utils_Request::retrieve('context', 'String');

    $this->dataProcessor = civicrm_api3('DataProcessor', 'getsingle', array('name' => $this->dataProcessorName));
    $this->dataProcessorClass = CRM_Dataprocessor_BAO_DataProcessor::dataProcessorToClass($this->dataProcessor);
    $this->assign('dataProcessorName', $this->dataProcessorName);
    $this->assign('context', $this->context);
  }

  /**
   * Dataprocessor Output as dashlet.
   *
   * @return void
   */

  public function run() {
    $this->preProcess();
    return parent::run();
  }

}
