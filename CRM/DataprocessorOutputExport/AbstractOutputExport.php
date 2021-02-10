<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

use Civi\DataProcessor\Output\ExportOutputInterface;
use Civi\DataProcessor\Output\DirectDownloadExportOutputInterface;

use CRM_Dataprocessor_ExtensionUtil as E;

abstract class CRM_DataprocessorOutputExport_AbstractOutputExport implements ExportOutputInterface, DirectDownloadExportOutputInterface {

  /**
   * Returns the directory name for storing temporary files.
   *
   * @return String
   */
  abstract public function getDirectory();

  /**
   * Returns the file extension.
   *
   * @return String
   */
  abstract public function getExtension();

  /**
   * Returns the mime type of the export file.
   *
   * @return string
   */
  abstract public function mimeType();

  /**
   * Run the export of the data processor.
   *
   * @param $filename
   * @param \Civi\DataProcessor\ProcessorType\AbstractProcessorType $dataProcessor
   * @param $configuration
   * @param $idField
   * @param array $selectedIds
   *
   * @return mixed
   */
  abstract protected function exportDataProcessor($filename, \Civi\DataProcessor\ProcessorType\AbstractProcessorType $dataProcessor, $configuration, $idField=null, $selectedIds=array());

  /**
   * Returns the number of records to run a direct download.
   * Otherwise a progressbar is shown to the user.
   *
   * @return int
   */
  protected function getMaxDirectDownload() {
    return 1;
  }

  /**
   * When a progressbar is shown to the user set the number
   * of records per job.
   *
   * @return int
   */
  protected function getJobSize() {
    return 1;
  }

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
    $form->add('checkbox', 'anonymous', E::ts('Is public'));
    $defaults = [];
    $configuration = false;
    if ($output && isset($output['configuration'])) {
      $configuration = $output['configuration'];
    }
    if ($configuration && isset($configuration['anonymous'])) {
      $defaults['anonymous'] = $configuration['anonymous'];
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
    return "CRM/DataprocessorOutputExport/Form/Configuration/GenericOutputExport.tpl";
  }

  /**
   * Process the submitted values and create a configuration array
   *
   * @param $submittedValues
   * @param array $output
   * @return array
   */
  public function processConfiguration($submittedValues, &$output) {
    $configuration = array();
    $configuration['anonymous'] = isset($submittedValues['anonymous']) ? $submittedValues['anonymous'] : false;
    return $configuration;
  }

  /**
   * Download export
   *
   * @param \Civi\DataProcessor\ProcessorType\AbstractProcessorType $dataProcessorClass
   * @param array $dataProcessor
   * @param array $outputBAO
   * @param array $formValues
   * @param string $sortFieldName
   * @param string $sortDirection
   * @param string $idField
   *  Set $idField to the name of the field containing the ID of the array $selectedIds
   * @param array $selectedIds
   *   Array with the selectedIds.
   * @return string
   * @throws \Exception
   */
  public function downloadExport(\Civi\DataProcessor\ProcessorType\AbstractProcessorType $dataProcessorClass, $dataProcessor, $outputBAO, $formValues, $sortFieldName = null, $sortDirection = 'ASC', $idField=null, $selectedIds=array()) {
    if ($dataProcessorClass->getDataFlow()->recordCount() > $this->getMaxDirectDownload()) {
      $this->startBatchJob($dataProcessorClass, $dataProcessor, $outputBAO, $formValues, $sortFieldName, $sortDirection, $idField, $selectedIds);
    } else {
      $this->doDirectDownload($dataProcessorClass, $dataProcessor, $outputBAO, $sortFieldName, $sortDirection, $idField, $selectedIds);
    }
  }

  public function doDirectDownload(\Civi\DataProcessor\ProcessorType\AbstractProcessorType $dataProcessorClass, $dataProcessor, $outputBAO, $sortFieldName = null, $sortDirection = 'ASC', $idField=null, $selectedIds=array()) {
    $filename = date('Ymdhis').'_'.$dataProcessor['id'].'_'.$outputBAO['id'].'_'.CRM_Core_Session::getLoggedInContactID().'_'.$dataProcessor['name'];
    $download_name = date('Ymdhis').'_'.$dataProcessor['name'].'.'.$this->getExtension();

    $basePath = CRM_Core_Config::singleton()->templateCompileDir . $this->getDirectory();
    CRM_Utils_File::createDir($basePath);
    CRM_Utils_File::restrictAccess($basePath.'/');

    $path = CRM_Core_Config::singleton()->templateCompileDir . $this->getDirectory().'/'. $filename;
    if ($sortFieldName) {
      $dataProcessorClass->getDataFlow()->resetSort();
      $dataProcessorClass->getDataFlow()->addSort($sortFieldName, $sortDirection);
    }

    $this->createHeader($path, $dataProcessorClass, $outputBAO['configuration'], $dataProcessor);
    $this->exportDataProcessor($path, $dataProcessorClass, $outputBAO['configuration'], $idField, $selectedIds);
    $this->createFooter($path, $dataProcessorClass, $outputBAO['configuration'], $dataProcessor);

    $mimeType = $this->mimeType();

    if (!$path) {
      \CRM_Core_Error::statusBounce('Could not retrieve the file');
    }

    $buffer = file_get_contents($path.'.'.$this->getExtension());
    if (!$buffer) {
      \CRM_Core_Error::statusBounce('The file is either empty or you do not have permission to retrieve the file');
    }

    CRM_Utils_System::setHttpHeader('Access-Control-Allow-Origin', '*');
    \CRM_Utils_System::download(
      $download_name,
      $mimeType,
      $buffer,
      NULL,
      TRUE,
      'download'
    );
  }

  protected function startBatchJob(\Civi\DataProcessor\ProcessorType\AbstractProcessorType $dataProcessorClass, $dataProcessor, $outputBAO, $formValues, $sortFieldName = null, $sortDirection = 'ASC', $idField=null, $selectedIds=array()) {
    $session = \CRM_Core_Session::singleton();

    $name = date('Ymdhis').'_'.$dataProcessor['id'].'_'.$outputBAO['id'].'_'.CRM_Core_Session::getLoggedInContactID().'_'.md5($dataProcessor['name']);

    $queue = \CRM_Queue_Service::singleton()->create(array(
      'type' => 'Sql',
      'name' => $name,
      'reset' => TRUE, //do flush queue upon creation
    ));

    $basePath = \CRM_Core_Config::singleton()->templateCompileDir . $this->getDirectory();
    \CRM_Utils_File::createDir($basePath);
    \CRM_Utils_File::restrictAccess($basePath.'/');
    $filename = $basePath.'/'. $name;

    $task = new \CRM_Queue_Task(
      array(
        'CRM_DataprocessorOutputExport_AbstractOutputExport',
        'exportBatchHeader'
      ), //call back method
      array($filename,$formValues, $dataProcessor['id'], $outputBAO['id'], $sortFieldName, $sortDirection, $idField, $selectedIds), //parameters,
      E::ts('Create header'),
    );
    //now add this task to the queue
    $queue->createItem($task);

    $count = $dataProcessorClass->getDataFlow()->recordCount();
    $recordsPerJob = $this->getJobSize();
    for($i=0; $i < $count; $i = $i + $recordsPerJob) {
      $title = E::ts('Exporting records %1/%2', array(
        1 => ($i+$recordsPerJob) <= $count ? $i+$recordsPerJob : $count,
        2 => $count,
      ));

      //create a task without parameters
      $task = new \CRM_Queue_Task(
        array(
          'CRM_DataprocessorOutputExport_AbstractOutputExport',
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
        'CRM_DataprocessorOutputExport_AbstractOutputExport',
        'exportBatchFooter'
      ), //call back method
      array($filename,$formValues, $dataProcessor['id'], $outputBAO['id'], $sortFieldName, $sortDirection, $idField, $selectedIds), //parameters,
      E::ts('Create footer'),
    );
    //now add this task to the queue
    $queue->createItem($task);

    $url = str_replace("&amp;", "&", $session->readUserContext());

    $runner = new \CRM_Queue_Runner(array(
      'title' => E::ts('Exporting data'), //title fo the queue
      'queue' => $queue, //the queue object
      'errorMode'=> \CRM_Queue_Runner::ERROR_CONTINUE, //abort upon error and keep task in queue
      'onEnd' => array('CRM_DataprocessorOutputExport_AbstractOutputExport', 'onEnd'), //method which is called as soon as the queue is finished
      'onEndUrl' => $url,
    ));

    $runner->runAllViaWeb(); // does not return
  }

  public static function exportBatch(CRM_Queue_TaskContext $ctx, $filename, $params, $dataProcessorId, $outputId, $offset, $limit, $sortFieldName = null, $sortDirection = 'ASC', $idField=null, $selectedIds=array()) {
    $factory = dataprocessor_get_factory();
    $dataProcessor = civicrm_api3('DataProcessor', 'getsingle', array('id' => $dataProcessorId));
    $output = civicrm_api3('DataProcessorOutput', 'getsingle', array('id' => $outputId));
    $dataProcessorClass = \CRM_Dataprocessor_BAO_DataProcessor::dataProcessorToClass($dataProcessor);
    CRM_Dataprocessor_Form_Output_AbstractUIOutputForm::applyFilters($dataProcessorClass, $params);
    if ($sortFieldName) {
      $dataProcessorClass->getDataFlow()->resetSort();
      $dataProcessorClass->getDataFlow()->addSort($sortFieldName, $sortDirection);
    }
    $dataProcessorClass->getDataFlow()->setOffset($offset);
    $dataProcessorClass->getDataFlow()->setLimit($limit);
    $outputClass = $factory->getOutputByName($output['type']);
    if (!$outputClass instanceof CRM_DataprocessorOutputExport_AbstractOutputExport) {
      throw new \Exception('Invalid output class');
    }

    $outputClass->exportDataProcessor($filename, $dataProcessorClass, $output['configuration'], $idField, $selectedIds);
    return TRUE;
  }

  public static function exportBatchHeader(CRM_Queue_TaskContext $ctx, $filename, $params, $dataProcessorId, $outputId, $sortFieldName = null, $sortDirection = 'ASC', $idField=null, $selectedIds=array()) {
    $factory = dataprocessor_get_factory();
    $dataProcessor = civicrm_api3('DataProcessor', 'getsingle', array('id' => $dataProcessorId));
    $output = civicrm_api3('DataProcessorOutput', 'getsingle', array('id' => $outputId));
    $dataProcessorClass = \CRM_Dataprocessor_BAO_DataProcessor::dataProcessorToClass($dataProcessor);
    CRM_Dataprocessor_Form_Output_AbstractUIOutputForm::applyFilters($dataProcessorClass, $params);
    if ($sortFieldName) {
      $dataProcessorClass->getDataFlow()->addSort($sortFieldName, $sortDirection);
    }
    $outputClass = $factory->getOutputByName($output['type']);
    if (!$outputClass instanceof CRM_DataprocessorOutputExport_AbstractOutputExport) {
      throw new \Exception('Invalid output class');
    }
    $outputClass->createHeader($filename, $dataProcessorClass, $output['configuration'], $dataProcessor, $idField, $selectedIds);
    return TRUE;
  }

  public static function exportBatchFooter(CRM_Queue_TaskContext $ctx, $filename, $params, $dataProcessorId, $outputId, $sortFieldName = null, $sortDirection = 'ASC', $idField=null, $selectedIds=array()) {
    $factory = dataprocessor_get_factory();
    $dataProcessor = civicrm_api3('DataProcessor', 'getsingle', array('id' => $dataProcessorId));
    $output = civicrm_api3('DataProcessorOutput', 'getsingle', array('id' => $outputId));
    $dataProcessorClass = \CRM_Dataprocessor_BAO_DataProcessor::dataProcessorToClass($dataProcessor);
    CRM_Dataprocessor_Form_Output_AbstractUIOutputForm::applyFilters($dataProcessorClass, $params);
    if ($sortFieldName) {
      $dataProcessorClass->getDataFlow()->addSort($sortFieldName, $sortDirection);
    }
    $outputClass = $factory->getOutputByName($output['type']);
    if (!$outputClass instanceof CRM_DataprocessorOutputExport_AbstractOutputExport) {
      throw new \Exception('Invalid output class');
    }
    $outputClass->createFooter($filename, $dataProcessorClass, $output['configuration'], $dataProcessor, $idField, $selectedIds);
    return TRUE;
  }

  /**
   * Run this function when the progressbar is finished.
   *
   * @param \CRM_Queue_TaskContext $ctx
   */
  public static function onEnd(CRM_Queue_TaskContext $ctx) {
    $factory = dataprocessor_get_factory();
    $queue_name = $ctx->queue->getName();
    [$_1, $dataProcessorId, $outputId, $_2, $_3] = explode("_", $queue_name);
    $dataProcessor = civicrm_api3('DataProcessor', 'getsingle', array('id' => $dataProcessorId));
    $output = civicrm_api3('DataProcessorOutput', 'getsingle', array('id' => $outputId));
    $outputClass = $factory->getOutputByName($output['type']);
    if (!$outputClass instanceof CRM_DataprocessorOutputExport_AbstractOutputExport) {
      throw new \Exception('Invalid output class');
    }

    $filename = $queue_name.'.'.$outputClass->getExtension();
    $downloadLink = CRM_Utils_System::url('civicrm/dataprocessor/form/output/download', 'filename='.$filename.'&directory='.$outputClass->getDirectory());
    $download_name = $dataProcessor['name'].'.'.$outputClass->getExtension();
    //set a status message for the user
    CRM_Core_Session::setStatus(E::ts('Download <a href="%1">%2</a>', array(1=>$downloadLink, 2 => $download_name)), E::ts('Exported data'), 'success');
  }

  /**
   * Checks whether the current user has access to this output
   *
   * @param array $output
   * @param array $dataProcessor
   * @return bool
   */
  public function checkPermission($output, $dataProcessor) {
    $anonymous = false;
    if (isset($output['configuration']) && isset($output['configuration']['anonymous'])) {
      $anonymous = $output['configuration']['anonymous'] ? true : false;
    }
    $userId = \CRM_Core_Session::getLoggedInContactID();
    if ($userId) {
      return true;
    } elseif ($anonymous) {
      return true;
    }
    return false;
  }

  /**
   * Returns the url for the page/form this output will show to the user
   *
   * @param array $output
   * @param array $dataProcessor
   * @return string
   */
  public function getUrl($output, $dataProcessor) {
    $frontendUrl = false;
    if (isset($output['configuration']) && isset($output['configuration']['anonymous'])) {
      $frontendUrl = $output['configuration']['anonymous'] ? true : false;
    }

    return CRM_Utils_System::url('civicrm/dataprocessor/output/export', array(
      'dataprocessor' => $dataProcessor['name'],
      'type' => $output['type']
    ), TRUE, NULL, FALSE, $frontendUrl);
  }

  /**
   * Returns the url for the page/form this output will show to the user
   *
   * @param array $output
   * @param array $dataProcessor
   * @return string
   */
  public function getTitleForLink($output, $dataProcessor) {
    return $dataProcessor['title'];
  }

  /**
   * This function could be overridden in child classes to create a header.
   * For example the CSV export uses this function.
   *
   * @param $filename
   * @param \Civi\DataProcessor\ProcessorType\AbstractProcessorType $dataProcessorClass
   * @param $configuration
   * @param $dataProcessor
   */
  protected function createHeader($filename, \Civi\DataProcessor\ProcessorType\AbstractProcessorType $dataProcessorClass, $configuration, $dataProcessor, $idField=null, $selectedIds=array()) {
    // Do nothing by default.
  }

  /**
   * This function could be overridden in child classes to create a footer.
   * For example the PDF export uses this function.
   *
   * @param $filename
   * @param \Civi\DataProcessor\ProcessorType\AbstractProcessorType $dataProcessorClass
   * @param $configuration
   * @param $dataProcessor
   */
  protected function createFooter($filename, \Civi\DataProcessor\ProcessorType\AbstractProcessorType $dataProcessorClass, $configuration, $dataProcessor, $idField=null, $selectedIds=array()) {
    // Do nothing by default.
  }

}