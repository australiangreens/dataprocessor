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
   * @var \CRM_Dataprocessor_BAO_Output
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
    }
    throw new \Exception('Unable to export');
  }

  protected function getDataProcessorName() {
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
