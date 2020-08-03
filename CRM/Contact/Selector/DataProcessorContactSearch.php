<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

class CRM_Contact_Selector_DataProcessorContactSearch {

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
  protected $dataProcessor;

  /**
   * @var \CRM_Dataprocessor_BAO_DataProcessorOutput
   */
  protected $dataProcessorOutput;

  public function __construct($customClass, $formValues=null, $params=null, $returnProperties=null) {
    $this->loadDataProcessor();
    $sortFields = $this->getSortFields();
    $this->sort = new CRM_Utils_Sort($sortFields);
    if (isset($formValues[CRM_Utils_Sort::SORT_ID])) {
      $this->sort->initSortID($formValues[CRM_Utils_Sort::SORT_ID]);
    }

    CRM_Dataprocessor_Form_Output_AbstractUIOutputForm::applyFilters($this->dataProcessorClass, $formValues);
    // Set the sort
    $sortDirection = 'ASC';
    if (!empty($this->sort->_vars[$this->sort->getCurrentSortID()])) {
      $sortField = $this->sort->_vars[$this->sort->getCurrentSortID()];
      if ($this->sort->getCurrentSortDirection() == CRM_Utils_Sort::DESCENDING) {
        $sortDirection = 'DESC';
      }
      $this->dataProcessorClass->getDataFlow()->addSort($sortField['name'], $sortDirection);
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
  protected function getSortFields() {
    $sortFields = array();
    $hiddenFields = $this->getHiddenFields();
    $sortColumnNr = 1;
    foreach($this->dataProcessorClass->getDataFlow()->getOutputFieldHandlers() as $outputFieldHandler) {
      $field = $outputFieldHandler->getOutputFieldSpecification();
      if (!in_array($field->alias, $hiddenFields)) {
        if ($outputFieldHandler instanceof \Civi\DataProcessor\FieldOutputHandler\OutputHandlerSortable) {
          $sortFields[$sortColumnNr] = array(
            'name' => $field->title,
            'sort' => $field->alias,
            'direction' => CRM_Utils_Sort::DONTCARE,
          );
          $sortColumnNr++;
        }
      }
    }
    return $sortFields;
  }

  /**
   * Generate contact ID query.
   *
   * @param array $params
   * @param $action
   * @param int $sortID
   * @param null $displayRelationshipType
   * @param string $queryOperator
   *
   * @return Object
   */
  public function contactIDQuery($params, $sortID, $displayRelationshipType = NULL, $queryOperator = 'AND') {
    $dataFlow = $this->dataProcessorClass->getDataFlow();
    $cids = array();
    $contactIdFieldName = $this->dataProcessorOutput['configuration']['contact_id_field'];
    try {
      while($record = $dataFlow->nextRecord()) {
        $cids[] = $record[$contactIdFieldName]->rawValue;
      }
    } catch (\Civi\DataProcessor\DataFlow\EndOfFlowException $e) {
      // do nothing
    }
    return CRM_Core_DAO::executeQuery("SELECT id as contact_id from civicrm_contact WHERE id in (".implode(", ", $cids).")");
  }

  /**
   * Return the data processor ID
   *
   * @return String
   */
  protected function getDataProcessorName() {
    $dataProcessorName = str_replace('civicrm/dataprocessor_contact_search/', '', CRM_Utils_System::currentPath());
    return $dataProcessorName;
  }

  /**
   * Retrieve the data processor and the output configuration
   *
   * @throws \Exception
   */
  protected function loadDataProcessor() {
    if (!$this->dataProcessorId) {
      $doNotUseCache = CRM_Utils_Request::retrieveValue('debug', 'Boolean', FALSE);

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
      $this->dataProcessorClass = \CRM_Dataprocessor_BAO_DataProcessor::dataProcessorToClass($this->dataProcessor, $doNotUseCache);
      $this->dataProcessorId = $dao->data_processor_id;

      $this->dataProcessorOutput = civicrm_api3('DataProcessorOutput', 'getsingle', array('id' => $dao->output_id));

      if (!$this->isConfigurationValid()) {
        throw new \Exception('Invalid configuration found of the data processor "' . $dataProcessorName . '"');
      }
    }
  }

  /**
   * Checks whether the output has a valid configuration
   *
   * @return bool
   */
  protected function isConfigurationValid() {
    if (!isset($this->dataProcessorOutput['configuration']['contact_id_field'])) {
      return false;
    }
    return true;
  }

  /**
   * Returns the name of the output for this search
   *
   * @return string
   */
  protected function getOutputName() {
    return 'contact_search';
  }

  /**
   * Returns whether the ID field is Visible
   *
   * @return bool
   */
  protected function isIdFieldVisible() {
    if (isset($this->dataProcessorOutput['configuration']['hide_id_field']) && $this->dataProcessorOutput['configuration']['hide_id_field']) {
      return false;
    }
    return true;
  }

  /**
   * Returns an array with hidden columns
   *
   * @return array
   */
  protected function getHiddenFields() {
    $hiddenFields = array();
    if (!$this->isIdFieldVisible()) {
      $hiddenFields[] = $this->dataProcessorOutput['configuration']['contact_id_field'];
    }
    return $hiddenFields;
  }


}
