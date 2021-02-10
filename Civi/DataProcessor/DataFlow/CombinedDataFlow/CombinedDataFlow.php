<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

namespace Civi\DataProcessor\DataFlow\CombinedDataFlow;

use \Civi\DataProcessor\DataFlow\AbstractDataFlow;
use \Civi\DataProcessor\DataFlow\EndOfFlowException;
use Civi\DataProcessor\DataFlow\InvalidFlowException;
use Civi\DataProcessor\DataFlow\MultipleDataFlows\DataFlowDescription;
use Civi\DataProcessor\DataFlow\MultipleDataFlows\JoinInterface;
use Civi\DataProcessor\DataFlow\MultipleDataFlows\MultipleSourceDataFlows;
use \Civi\DataProcessor\DataSpecification\DataSpecification;


class CombinedDataFlow extends AbstractDataFlow implements MultipleSourceDataFlows {

  /**
   * @var DataFlowDescription[]
   */
  protected $sourceDataFlowDescriptions = array();

  /**
   * @var int
   */
  protected $currentRecordIndex = 0;

  /**
   * @var array
   */
  protected $recordSet = array();

  /**
   * @var int
   */
  protected $recordCount = 0;

  /**
   * @var bool
   */
  protected $initialized = false;

  /**
   * @var \Civi\DataProcessor\DataSpecification\DataSpecification
   */
  protected $dataSpecification;

  /**
   * @var int
   */
  protected $batchSize = 100;

  public function __construct() {
    $this->dataSpecification = new DataSpecification();
  }

  /**
   * Adds a source data flow
   *
   * @param \Civi\DataProcessor\DataFlow\MultipleDataFlows\DataFlowDescription $dataFlowDescription
   * @return void
   */
  public function addSourceDataFlow(DataFlowDescription $dataFlowDescription) {
    $this->sourceDataFlowDescriptions[] = $dataFlowDescription;
  }

  /**
   * Removes a source data flow
   *
   * @param \Civi\DataProcessor\DataFlow\MultipleDataFlows\DataFlowDescription $dataFlowDescription
   * @return void
   * @throws \Civi\DataProcessor\DataFlow\InvalidFlowException
   */
  public function removeSourceDataFlow(DataFlowDescription $dataFlowDescription) {
    foreach($this->sourceDataFlowDescriptions as $idx => $sourceDataFlowDescription) {
      if ($sourceDataFlowDescription === $dataFlowDescription) {
        unset($this->sourceDataFlowDescriptions[$idx]);
      }
    }
  }

  /**
   * @param \Civi\DataProcessor\FieldOutputHandler\AbstractFieldOutputHandler $outputFieldHandler[]
   */
  public function setOutputFieldHandlers($handlers) {
    parent::setOutputFieldHandlers($handlers);
    foreach($this->sourceDataFlowDescriptions as $sourceDataFlowDescription) {
      $sourceDataFlowDescription->getDataFlow()->setOutputFieldHandlers($handlers);
    }
  }

  /**
   * Initialize the data flow
   *
   * @return void
   */
  public function initialize() {
    if ($this->isInitialized()) {
      return;
    }

    $this->recordSet = array();
    $this->recordCount = 0;

    if (count($this->sourceDataFlowDescriptions) < 1) {
      $this->initialized = true;
      return;
    }

    $allRecords = array();
    for($i=0; $i<count($this->sourceDataFlowDescriptions); $i++) {
      do {
        $batch = $this->getAllRecordsFromDataFlowAsArray($this->sourceDataFlowDescriptions[$i]->getDataFlow(), $this->batchSize);
        for($j=$i+1; $j<count($this->sourceDataFlowDescriptions); $j++) {
          $this->sourceDataFlowDescriptions[$j]->getJoinSpecification()->prepareRightDataFlow($batch, $this->sourceDataFlowDescriptions[$j]->getDataFlow());
          $rightRecords = $this->getAllRecordsFromDataFlowAsArray($this->sourceDataFlowDescriptions[$j]->getDataFlow());
          $batch = $this->sourceDataFlowDescriptions[$j]->getJoinSpecification()->join($batch, $rightRecords);
        }
        $allRecords = array_merge($allRecords, $batch);
      } while(count($batch) >= $this->batchSize || $this->batchSize == 0);
    }
    $this->recordCount = count($allRecords);

    if ($this->offset || $this->limit) {
      $offset = $this->offset !== FALSE ? $this->offset : 0;
      for ($i = $offset; $i < count($allRecords); $i++) {
        $this->recordSet[] = $allRecords[$i];
        if ($this->limit && count($this->recordSet) >= $this->limit) {
          break;
        }
      }
    } else {
      $this->recordSet = $allRecords;
    }

    $this->initialized = true;
  }

  /**
   * Return all records for a given data flow.
   *
   * @param \Civi\DataProcessor\DataFlow\AbstractDataFlow $dataFlow
   * @param int $batchSize 0 for unlimited
   * @return array
   * @throws \Civi\DataProcessor\DataFlow\EndOfFlowException
   */
  protected function getAllRecordsFromDataFlowAsArray(AbstractDataFlow $dataFlow, $batchSize=0) {
    $records = array();
    try {
      $i = 0;
      while(($record = $dataFlow->retrieveNextRecord()) && ($i < $batchSize || $batchSize == 0)) {
        $records[] = $record;
        $i++;
      }
    } catch (EndOfFlowException $e) {
      // Do nothing
    }
    return $records;
  }

  /**
   * Returns whether this flow has been initialized or not
   *
   * @return bool
   */
  public function isInitialized() {
    return $this->initialized;
  }

  /**
   * Resets the initialized state. This function is called
   * when a setting has changed. E.g. when offset or limit are set.
   *
   * @return void
   */
  protected function resetInitializeState() {
    parent::resetInitializeState();
    $this->initialized = false;
  }

  /**
   * Returns the next record in an associative array
   *
   * @param string $fieldNamePrefix
   *   The prefix before the name of the field within the record
   * @return array
   * @throws EndOfFlowException
   */
  public function retrieveNextRecord($fieldNamePrefix='') {
    if (!$this->isInitialized()) {
      $this->initialize();
    }

    if (!isset($this->recordSet[$this->currentRecordIndex])) {
      throw new EndOfFlowException();
    }
    $record = $this->recordSet[$this->currentRecordIndex];
    $out = array();
    if (strlen($fieldNamePrefix)) {
      foreach ($record as $field => $value) {
        $out[$fieldNamePrefix . $field] = $value;
      }
    }
    $this->currentRecordIndex++;
    return $record;
  }

  /**
   * @return int
   */
  public function recordCount() {
    if (!$this->isInitialized()) {
      $this->initialize();
    }
    return $this->recordCount;
  }


  /**
   * @return DataSpecification
   * @throws \Civi\DataProcessor\DataSpecification\FieldExistsException
   */
  public function getDataSpecification() {
    $dataSpecification = new DataSpecification();
    foreach($this->dataFlows as $dataFlow) {
      $namePrefix = $dataFlow['dataflow']->getName();
      $dataSpecification->merge($dataFlow['dataflow']->getDataSpecification(), $namePrefix);
    }
    return $dataSpecification;
  }

  public function getName() {
    return 'combined_data_flow';
  }

  public function getDebugInformation() {
    $debug = array();
    foreach($this->sourceDataFlowDescriptions as $sourceDataFlowDescription) {
      $debug[$sourceDataFlowDescription->getDataFlow()->getName()] = $sourceDataFlowDescription->getDataFlow()->getDebugInformation();
    }
    return $debug;

  }

}
