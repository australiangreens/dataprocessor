<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

namespace Civi\DataProcessor\DataFlow;

use \Civi\DataProcessor\DataFlow\MultipleDataFlows\DataFlowDescription;
use Civi\DataProcessor\DataFlow\Utils\Aggregator;
use \Civi\DataProcessor\DataSpecification\DataSpecification;
use Civi\DataProcessor\DataSpecification\FieldSpecification;
use \Civi\DataProcessor\FieldOutputHandler\AbstractFieldOutputHandler;
use Civi\DataProcessor\DataFlow\Sort\SortSpecification;
use Civi\DataProcessor\FieldOutputHandler\OutputHandlerAggregate;

abstract class AbstractDataFlow {

  /**
   * @var null|array
   */
  private $_allRecords = null;

  private $currentRecordIndex = 0;

  /**
   * @var \Civi\DataProcessor\FieldOutputHandler\AbstractFieldOutputHandler[]
   */
  protected $outputFieldHandlers;

  /**
   * @var false|int
   */
  protected $offset = false;

  /**
   * @var false|int
   */
  protected $limit = false;

  /**
   * @var \Civi\DataProcessor\DataFlow\MultipleDataFlows\DataFlowDescription
   */
  protected $dataFlowDescription;

  /**
   * @var \Civi\DataProcessor\DataSpecification\DataSpecification
   */
  protected $dataSpecification;

  /**
   * @var \Civi\DataProcessor\FieldOutputHandler\OutputHandlerAggregate[]
   */
  protected $aggregateOutputHandlers = array();

  /**
   * @var SortSpecification[]
   */
  protected $sortSpecifications = array();

  /**
   * Initialize the data flow
   *
   * @return void
   * @throws \Civi\DataProcessor\Exception\DataFlowException
   */
  abstract public function initialize();

  /**
   * Returns whether this flow has been initialized or not
   *
   * @return bool
   */
  abstract public function isInitialized();

  /**
   * Resets the initialized state. This function is called
   * when a setting has changed. E.g. when offset or limit are set.
   *
   * @return void
   */
  protected function resetInitializeState() {
    $this->currentRecordIndex = 0;
    unset($this->_allRecords);
  }

  /**
   * Returns the next record in an associative array
   *
   * @param string $fieldNameprefix
   *   The prefix before the name of the field within the record.
   * @return array
   * @throws \Civi\DataProcessor\Exception\EndOfFlowException
   * @throws \Civi\DataProcessor\Exception\DataFlowException
   */
  abstract public function retrieveNextRecord($fieldNameprefix='');

  /**
   * Returns a name for this data flow.
   *
   * @return string
   */
  abstract public function getName();

  public function __construct() {
  }

  /**
   * Returns the next record or throws EndOfFlowException when the end
   * of the dataflow is reached.
   *
   * @param string $fieldNamePrefix
   *   The prefix before the name of the field within the record.
   * @return array
   * @throws \Civi\DataProcessor\DataFlow\EndOfFlowException
   * @throws \Civi\DataProcessor\Exception\DataFlowException
   */
  public function nextRecord($fieldNamePrefix = '') {
    $allRecords = $this->allRecords($fieldNamePrefix);
    if (isset($allRecords[$this->currentRecordIndex])) {
      $record = $allRecords[$this->currentRecordIndex];
      $this->currentRecordIndex++;
      return $record;
    }
    throw new EndOfFlowException();
  }

  /**
   * @return int
   */
  public function recordCount() {
    $allRecords = $this->allRecords();
    return count($allRecords);
  }

  /**
   * @return DataSpecification
   */
  public function getDataSpecification() {
    if (!$this->dataSpecification) {
      $this->dataSpecification = new DataSpecification();
    }
    return $this->dataSpecification;
  }

  /**
   * Returns an array of all records in the data flow.
   *
   * @param string $fieldNameprefix
   *   The prefix before the name of the field within the record
   * @return array
   */
  public function allRecords($fieldNameprefix = '') {
    if (!isset($this->_allRecords) || !is_array($this->_allRecords)) {
      $this->_allRecords = [];
      $_allRecords = [];
      try {
        while ($record = $this->retrieveNextRecord($fieldNameprefix)) {
          $_allRecords[] = $record;
        }
      } catch (EndOfFlowException $e) {
        // Do nothing
      }
      $_allRecords = $this->aggregate($_allRecords, $fieldNameprefix);
      foreach($_allRecords as $record) {
        $this->_allRecords[] = $this->formatRecordOutput($record);
      }
      usort($this->_allRecords, array($this, 'sort'));
    }

    return $this->_allRecords;
  }

  public function formatRecordOutput($record) {
    $formattedRecord = array();
    foreach($this->outputFieldHandlers as $outputFieldHandler) {
      $formattedRecord[$outputFieldHandler->getOutputFieldSpecification()->alias] = $outputFieldHandler->formatField($record, $formattedRecord);
    }
    return $formattedRecord;
  }

  /**
   * @param false|int $offset
   *
   * @return \Civi\DataProcessor\DataFlow\AbstractDataFlow
   */
  public function setOffset($offset) {
    $this->offset = $offset;
    $this->resetInitializeState();
    return $this;
  }

  /**
   * @param false|int $limit
   *
   * @return \Civi\DataProcessor\DataFlow\AbstractDataFlow
   */
  public function setLimit($limit) {
    $this->limit = $limit;
    $this->resetInitializeState();
    return $this;
  }

  /**
   * @param \Civi\DataProcessor\DataFlow\MultipleDataFlows\DataFlowDescription $dataFlowDescription
   * @return \Civi\DataProcessor\DataFlow\AbstractDataFlow
   */
  public function setDataFlowDescription(DataFlowDescription $dataFlowDescription) {
    $this->dataFlowDescription = $dataFlowDescription;
    return $this;
  }

  /**
   * @return \Civi\DataProcessor\DataFlow\MultipleDataFlows\DataFlowDescription
   */
  public function getDataFlowDescription() {
    return $this->dataFlowDescription;
  }

  /**
   * @param \Civi\DataProcessor\FieldOutputHandler\AbstractFieldOutputHandler $outputFieldHandler
   */
  public function addOutputFieldHandlers(AbstractFieldOutputHandler $outputFieldHandler) {
    $this->outputFieldHandlers[] = $outputFieldHandler;
  }

  /**
   * @param \Civi\DataProcessor\FieldOutputHandler\AbstractFieldOutputHandler $outputFieldHandler[]
   */
  public function setOutputFieldHandlers($handlers) {
    $this->outputFieldHandlers = $handlers;
  }

  /**
   * @return \Civi\DataProcessor\FieldOutputHandler\AbstractFieldOutputHandler[]
   */
  public function getOutputFieldHandlers() {
    return $this->outputFieldHandlers;
  }


  /**
   * Returns debug information
   *
   * @return array
   */
  public function getDebugInformation() {
    return array();
  }

  /**
   * @param \Civi\DataProcessor\DataFlow\OutputHandlerAggregate $aggregateOutputHandler
   */
  public function addAggregateOutputHandler(OutputHandlerAggregate $aggregateOutputHandler) {
    $this->aggregateOutputHandlers[] = $aggregateOutputHandler;
  }

  /**
   * @param \Civi\DataProcessor\DataFlow\OutputHandlerAggregate $aggregateOutputHandler
   */
  public function removeAggregateOutputHandler(OutputHandlerAggregate $aggregateOutputHandler) {
    foreach($this->aggregateOutputHandlers as $key => $item) {
      if ($item->getAggregateFieldSpec()->alias == $aggregateOutputHandler->getAggregateFieldSpec()->alias) {
        unset($this->aggregateOutputHandlers[$key]);
        break;
      }
    }
  }

  /**
   * Adds a field for sorting
   *
   * @param $fieldName
   * @param $direction
   */
  public function addSort($fieldName, $direction) {
    $direction = strtoupper($direction);
    $this->sortSpecifications[] = new SortSpecification($this, $fieldName, $direction);
  }

  /**
   * Resets the sorting
   */
  public function resetSort() {
    $this->sortSpecifications = [];
  }

  /**
   * Sort compare function
   * Returns 0 when both values are equal
   * Returns -1 when a is less than b
   * Return 1 when b is less than a
   *
   * @param $row_a
   * @param $row_b
   * @return int
   */
  protected function sort($row_a, $row_b) {
    $compareValue = 0;
    foreach($this->sortSpecifications as $sortSpecification) {
      $compareValue = $sortSpecification->compare($row_a, $row_b);
      if ($compareValue != 0) {
        break;
      }
    }
    return $compareValue;
  }

  /**
   * @param $records
   * @param string $fieldNameprefix
   *
   * @return array();
   */
  protected function aggregate($records, $fieldNameprefix="") {
    if (count($this->aggregateOutputHandlers)) {
      $aggregator = new Aggregator($records, $this->aggregateOutputHandlers, $this->dataSpecification);
      $records = $aggregator->aggregateRecords($fieldNameprefix);
    }
    return $records;
  }

  /**
   * When an object is cloned, PHP 5 will perform a shallow copy of all of the
   * object's properties. Any properties that are references to other
   * variables, will remain references. Once the cloning is complete, if a
   * __clone() method is defined, then the newly created object's __clone()
   * method will be called, to allow any necessary properties that need to be
   * changed. NOT CALLABLE DIRECTLY.
   *
   * @return void
   * @link https://php.net/manual/en/language.oop5.cloning.php
   */
  public function __clone() {
    $this->dataSpecification = clone $this->dataSpecification;
  }


}
