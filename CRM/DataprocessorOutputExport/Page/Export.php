<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

class CRM_DataprocessorOutputExport_Page_Export extends CRM_Core_Page {

  /**
   * @var array
   */
  protected $dataProcessor;

  /**
   * @var \Civi\DataProcessor\ProcessorType\AbstractProcessorType;
   */
  protected $dataProcessorClass;

  /**
   * @var int
   */
  protected $dataProcessorId;

  /**
   * @var array
   */
  protected $dataProcessorOutput;

  /**
   * Run page.
   */
  public function run() {
    $this->loadDataProcessor();
    $sortFields = $this->addColumnHeaders();
    $this->sort = new CRM_Utils_Sort($sortFields);

    $this->runExport();
  }

  protected function startBatchJob(\Civi\DataProcessor\ProcessorType\AbstractProcessorType $dataProcessorClass, $dataProcessor, $outputBAO, $formValues, $sortFieldName = null, $sortDirection = 'ASC', $idField=null, $selectedIds=array()) {
    $session = \CRM_Core_Session::singleton();

    $name = date('Ymdhis').'_'.$dataProcessor['id'].'_'.$outputBAO['id'].'_'.CRM_Core_Session::getLoggedInContactID().'_'.md5($dataProcessor['name']);

    $queue = \CRM_Queue_Service::singleton()->create(array(
      'type' => 'Sql',
      'name' => $name,
      'reset' => TRUE, //do flush queue upon creation
    ));

    $basePath = \CRM_Core_Config::singleton()->templateCompileDir . 'dataprocessor_export_pdf';
    \CRM_Utils_File::createDir($basePath);
    \CRM_Utils_File::restrictAccess($basePath.'/');
    $filename = $basePath.'/'. $name.'.html';

    $count = $dataProcessorClass->getDataFlow()->recordCount();
    $recordsPerJob = self::RECORDS_PER_JOB;
    for($i=0; $i < $count; $i = $i + $recordsPerJob) {
      $title = E::ts('Exporting records %1/%2', array(
        1 => ($i+$recordsPerJob) <= $count ? $i+$recordsPerJob : $count,
        2 => $count,
      ));

      //create a task without parameters
      $task = new \CRM_Queue_Task(
        array(
          'CRM_DataprocessorOutputExport_PDF',
          'exportBatch'
        ), //call back method
        array($filename,$formValues, $dataProcessor['id'], $outputBAO['id'], $i, $recordsPerJob, $sortFieldName, $sortDirection, $idField, $selectedIds), //parameters,
        $title
      );
      //now add this task to the queue
      $queue->createItem($task);
    }

    $task = new \CRM_Queue_Task(
      array(
        'CRM_DataprocessorOutputExport_PDF',
        'exportBatchFooter'
      ), //call back method
      array($filename,$formValues, $dataProcessor['id'], $outputBAO['id'], $i, $recordsPerJob, $sortFieldName, $sortDirection, $idField, $selectedIds), //parameters,
      $title
    );
    //now add this task to the queue
    $queue->createItem($task);

    $url = str_replace("&amp;", "&", $session->readUserContext());

    $runner = new \CRM_Queue_Runner(array(
      'title' => E::ts('Exporting data'), //title fo the queue
      'queue' => $queue, //the queue object
      'errorMode'=> \CRM_Queue_Runner::ERROR_CONTINUE, //abort upon error and keep task in queue
      'onEnd' => array('CRM_DataprocessorOutputExport_PDF', 'onEnd'), //method which is called as soon as the queue is finished
      'onEndUrl' => $url,
    ));

    $runner->runAllViaWeb(); // does not return
  }

  protected function runExport() {
    $factory = dataprocessor_get_factory();
    CRM_Dataprocessor_Form_Output_AbstractUIOutputForm::applyFilters($this->dataProcessorClass, array());

    // Set the sort
    $sortDirection = 'ASC';
    $sortFieldName = null;
    if (!empty($this->sort->_vars[$this->sort->getCurrentSortID()])) {
      $sortField = $this->sort->_vars[$this->sort->getCurrentSortID()];
      if ($this->sort->getCurrentSortDirection() == CRM_Utils_Sort::DESCENDING) {
        $sortDirection = 'DESC';
      }
      $sortFieldName = $sortField['name'];
    }

    $outputClass = $factory->getOutputByName($this->dataProcessorOutput['type']);
    if ($outputClass instanceof \Civi\DataProcessor\Output\DirectDownloadExportOutputInterface) {
      $outputClass->doDirectDownload($this->dataProcessorClass, $this->dataProcessor, $this->dataProcessorOutput, array(), $sortFieldName, $sortDirection);
      \CRM_Utils_System::civiExit();
    }
    throw new \Exception('Unable to export');
  }

  protected function getDataProcessorName() {
    $name = CRM_Utils_Request::retrieveValue('dataprocessor', 'String', NULL, FALSE);
    if ($name) {
      return $name;
    }
    return CRM_Utils_Request::retrieveValue('name', 'String', NULL, TRUE);
  }

  protected function getOutputName() {
    return CRM_Utils_Request::retrieveValue('type', 'String', NULL, TRUE);
  }

  /**
   * Retrieve the data processor and the output configuration
   *
   * @throws \Exception
   */
  protected function loadDataProcessor() {
    $factory = dataprocessor_get_factory();
    if (!$this->dataProcessorId) {
      $dataProcessorName = $this->getDataProcessorName();
      $sql = "
        SELECT civicrm_data_processor.id as data_processor_id,  civicrm_data_processor_output.id AS output_id
        FROM civicrm_data_processor
        INNER JOIN civicrm_data_processor_output ON civicrm_data_processor.id = civicrm_data_processor_output.data_processor_id
        WHERE is_active = 1 AND civicrm_data_processor.name = %1 AND civicrm_data_processor_output.type = %2
      ";
      $params[1] = [$dataProcessorName, 'String'];
      $params[2] = [$this->getOutputName(), 'String'];
      $dao = CRM_Dataprocessor_BAO_DataProcessor::executeQuery($sql, $params, TRUE, 'CRM_Dataprocessor_BAO_DataProcessor');
      if (!$dao->fetch()) {
        throw new \Exception('Could not find Data Processor "' . $dataProcessorName.'"');
      }

      $this->dataProcessor = civicrm_api3('DataProcessor', 'getsingle', array('id' => $dao->data_processor_id));
      $this->dataProcessorClass = \CRM_Dataprocessor_BAO_DataProcessor::dataProcessorToClass($this->dataProcessor);
      $this->dataProcessorId = $dao->data_processor_id;

      $this->dataProcessorOutput = civicrm_api3('DataProcessorOutput', 'getsingle', array('id' => $dao->output_id));

      $outputClass = $factory->getOutputByName($this->dataProcessorOutput['type']);
      if (!$outputClass instanceof \Civi\DataProcessor\Output\DirectDownloadExportOutputInterface) {
        throw new \Exception('Invalid output');
      }
      if (!$outputClass->checkPermission($this->dataProcessorOutput, $this->dataProcessor)) {
        CRM_Utils_System::permissionDenied();
        CRM_Utils_System::civiExit();
      }
    }
  }

  /**
   * Add the headers for the columns
   *
   * @return array
   *   Array with all possible sort fields.
   *
   * @throws \Civi\DataProcessor\DataFlow\InvalidFlowException
   */
  protected function addColumnHeaders() {
    $sortFields = array();
    $columnHeaders = array();
    $sortColumnNr = 1;
    foreach($this->dataProcessorClass->getDataFlow()->getOutputFieldHandlers() as $outputFieldHandler) {
      $field = $outputFieldHandler->getOutputFieldSpecification();
      $columnHeaders[$field->alias] = $field->title;
      if ($outputFieldHandler instanceof \Civi\DataProcessor\FieldOutputHandler\OutputHandlerSortable) {
        $sortFields[$sortColumnNr] = array(
          'name' => $field->title,
          'sort' => $field->alias,
          'direction' => CRM_Utils_Sort::DONTCARE,
        );
        $sortColumnNr++;
      }
    }
    return $sortFields;
  }

}
