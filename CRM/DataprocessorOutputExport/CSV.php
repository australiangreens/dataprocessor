<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

use Civi\DataProcessor\Output\ExportOutputInterface;
use Civi\DataProcessor\Output\DirectDownloadExportOutputInterface;

use CRM_Dataprocessor_ExtensionUtil as E;

class CRM_DataprocessorOutputExport_CSV implements ExportOutputInterface, DirectDownloadExportOutputInterface {

  const MAX_DIRECT_SIZE = 500;

  const RECORDS_PER_JOB = 250;

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
    $form->add('text', 'delimiter', E::ts('Delimiter'), array(), true);
    $form->add('text', 'enclosure', E::ts('Enclosure'), array(), true);
    $form->add('text', 'escape_char', E::ts('Escape char'), array(), true);
    $form->add('checkbox', 'anonymous', E::ts('Available for anonymous users'), array(), false);

    $configuration = false;
    if ($output && isset($output['configuration'])) {
      $configuration = $output['configuration'];
    }
    if ($configuration && isset($configuration['delimiter']) && $configuration['delimiter']) {
      $defaults['delimiter'] = $configuration['delimiter'];
    } else {
      $defaults['delimiter'] = ';';
    }
    if ($configuration && isset($configuration['enclosure']) && $configuration['enclosure']) {
      $defaults['enclosure'] = $configuration['enclosure'];
    } else {
      $defaults['enclosure'] = '"';
    }
    if ($configuration && isset($configuration['escape_char']) && $configuration['escape_char']) {
      $defaults['escape_char'] = $configuration['escape_char'];
    } else {
      $defaults['escape_char'] = '\\';
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
    return "CRM/DataprocessorOutputExport/Form/Configuration/CSV.tpl";
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
    $configuration['delimiter'] = $submittedValues['delimiter'];
    $configuration['enclosure'] = $submittedValues['enclosure'];
    $configuration['escape_char'] = $submittedValues['escape_char'];
    $configuration['anonymous'] = $submittedValues['anonymous'];
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
   * Returns the mime type of the export file.
   *
   * @return string
   */
  public function mimeType() {
    return 'text/csv';
  }

  /**
   * Returns the url for the page/form this output will show to the user
   *
   * @param array $output
   * @param array $dataProcessor
   * @return string
   */
  public function getTitleForExport($output, $dataProcessor) {
    return E::ts('Download as CSV');
  }

  /**
   * Returns the url for the page/form this output will show to the user
   *
   * @param array $output
   * @param array $dataProcessor
   * @return string|false
   */
  public function getExportFileIcon($output, $dataProcessor) {
    return '<i class="fa fa-file-excel-o">&nbsp;</i>';
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
    if ($dataProcessorClass->getDataFlow()->recordCount() > self::MAX_DIRECT_SIZE) {
      $this->startBatchJob($dataProcessorClass, $dataProcessor, $outputBAO, $formValues, $sortFieldName, $sortDirection, $idField, $selectedIds);
    } else {
      $this->doDirectDownload($dataProcessorClass, $dataProcessor, $outputBAO, $sortFieldName, $sortDirection, $idField, $selectedIds);
    }
  }

  public function doDirectDownload(\Civi\DataProcessor\ProcessorType\AbstractProcessorType $dataProcessorClass, $dataProcessor, $outputBAO, $sortFieldName = null, $sortDirection = 'ASC', $idField, $selectedIds=array()) {
    $filename = date('Ymdhis').'_'.$dataProcessor['id'].'_'.$outputBAO['id'].'_'.CRM_Core_Session::getLoggedInContactID().'_'.$dataProcessor['name'].'.csv';
    $download_name = date('Ymdhis').'_'.$dataProcessor['name'].'.csv';

    $basePath = CRM_Core_Config::singleton()->templateCompileDir . 'dataprocessor_export_csv';
    CRM_Utils_File::createDir($basePath);
    CRM_Utils_File::restrictAccess($basePath.'/');

    $path = CRM_Core_Config::singleton()->templateCompileDir . 'dataprocessor_export_csv/'. $filename;
    if ($sortFieldName) {
      $dataProcessorClass->getDataFlow()->resetSort();
      $dataProcessorClass->getDataFlow()->addSort($sortFieldName, $sortDirection);
    }

    self::createHeaderLine($path, $dataProcessorClass, $outputBAO['configuration']);
    self::exportDataProcessor($path, $dataProcessorClass, $outputBAO['configuration'], $idField, $selectedIds);

    $mimeType = $this->mimeType();

    if (!$path) {
      \CRM_Core_Error::statusBounce('Could not retrieve the file');
    }

    $buffer = file_get_contents($path);
    if (!$buffer) {
      \CRM_Core_Error::statusBounce('The file is either empty or you do not have permission to retrieve the file');
    }

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

    $basePath = \CRM_Core_Config::singleton()->templateCompileDir . 'dataprocessor_export_csv';
    \CRM_Utils_File::createDir($basePath);
    \CRM_Utils_File::restrictAccess($basePath.'/');
    $filename = $basePath.'/'. $name.'.csv';

    self::createHeaderLine($filename, $dataProcessorClass, $outputBAO['configuration']);

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
          'CRM_DataprocessorOutputExport_CSV',
          'exportBatch'
        ), //call back method
        array($filename,$formValues, $dataProcessor['id'], $outputBAO['id'], $i, $recordsPerJob, $sortFieldName, $sortDirection, $idField, $selectedIds), //parameters,
        $title
      );
      //now add this task to the queue
      $queue->createItem($task);
    }

    $url = str_replace("&amp;", "&", $session->readUserContext());

    $runner = new \CRM_Queue_Runner(array(
      'title' => E::ts('Exporting data'), //title fo the queue
      'queue' => $queue, //the queue object
      'errorMode'=> CRM_Queue_Runner::ERROR_CONTINUE, //abort upon error and keep task in queue
      'onEnd' => array('CRM_DataprocessorOutputExport_CSV', 'onEnd'), //method which is called as soon as the queue is finished
      'onEndUrl' => $url,
    ));

    $runner->runAllViaWeb(); // does not return
  }

  protected static function createHeaderLine($filename, \Civi\DataProcessor\ProcessorType\AbstractProcessorType $dataProcessor, $configuration) {
    $file = fopen($filename, 'a');
    fwrite($file, "\xEF\xBB\xBF"); // BOF this will make sure excel opens the file correctly.
    $headerLine = array();
    foreach($dataProcessor->getDataFlow()->getOutputFieldHandlers() as $outputHandler) {
      $headerLine[] = self::encodeValue($outputHandler->getOutputFieldSpecification()->title, $configuration['escape_char'], $configuration['enclosure']);
    }
    fwrite($file, implode($configuration['delimiter'], $headerLine)."\r\n");
    fclose($file);
  }

  protected static function exportDataProcessor($filename, \Civi\DataProcessor\ProcessorType\AbstractProcessorType $dataProcessor, $configuration, $idField, $selectedIds=array()) {
    $file = fopen($filename, 'a');
    try {
      while($record = $dataProcessor->getDataFlow()->nextRecord()) {
        $row = array();
        $rowIsSelected = true;
        if (isset($idField) && is_array($selectedIds) && count($selectedIds)) {
          $rowIsSelected = false;
          $id = $record[$idField]->rawValue;
          if (in_array($id, $selectedIds)) {
            $rowIsSelected = true;
          }
        }
        if ($rowIsSelected) {
          foreach ($record as $field => $value) {
            $row[] = self::encodeValue($value->formattedValue, $configuration['escape_char'], $configuration['enclosure']);
          }
          fwrite($file, implode($configuration['delimiter'], $row) . "\r\n");
        }
      }
    } catch (\Civi\DataProcessor\DataFlow\EndOfFlowException $e) {
      // Do nothing
    }
    fclose($file);
  }

  protected static function encodeValue($value, $escape, $enclosure) {
    ///remove any ESCAPED double quotes within string.
    $value = str_replace("{$escape}{$enclosure}","{$enclosure}",$value);
    //then force escape these same double quotes And Any UNESCAPED Ones.
    $value = str_replace("{$enclosure}","{$escape}{$enclosure}",$value);
    //force wrap value in quotes and return
    return "{$enclosure}{$value}{$enclosure}";
  }

  public static function exportBatch(CRM_Queue_TaskContext $ctx, $filename, $params, $dataProcessorId, $outputId, $offset, $limit, $sortFieldName = null, $sortDirection = 'ASC', $idField=null, $selectedIds=array()) {
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
    self::exportDataProcessor($filename, $dataProcessorClass, $output['configuration'], $idField, $selectedIds);
    return TRUE;
  }

  public static function onEnd(CRM_Queue_TaskContext $ctx) {
    $queue_name = $ctx->queue->getName();
    $filename = $queue_name.'.csv';
    $downloadLink = CRM_Utils_System::url('civicrm/dataprocessor/form/output/download', 'filename='.$filename.'&directory=dataprocessor_export_csv');
    //set a status message for the user
    CRM_Core_Session::setStatus(E::ts('<a href="%1">Download CSV file</a>', array(1=>$downloadLink)), E::ts('Exported data'), 'success');
  }

  /**
   * Returns the url for the page/form this output will show to the user
   *
   * @param array $output
   * @param array $dataProcessor
   * @return string
   */
  public function getUrl($output, $dataProcessor) {
    return CRM_Utils_System::url('civicrm/dataprocessor/output/export', array(
      'name' => $dataProcessor['name'],
      'type' => $output['type']
    ));
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


}
