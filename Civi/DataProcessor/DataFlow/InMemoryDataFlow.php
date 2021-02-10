<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

namespace Civi\DataProcessor\DataFlow;

use Civi\DataProcessor\DataFlow\InMemoryDataFlow\FilterInterface;

class InMemoryDataFlow extends AbstractDataFlow {

  protected $data = [];

  protected $currentPointer = 0;

  protected $isInitialized = FALSE;

  /**
   * @var \Civi\DataProcessor\DataFlow\InMemoryDataFlow\FilterInterface[]
   */
  protected $filters = [];

  public function __construct($data=null) {
    parent::__construct();
    $this->data = $data;
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
    $this->currentPointer = 0;
    $this->isInitialized = TRUE;
  }

  /**
   * Returns whether this flow has been initialized or not
   *
   * @return bool
   */
  public function isInitialized() {
    return $this->isInitialized;
  }

  /**
   * Resets the initialized state. This function is called
   * when a setting has changed. E.g. when offset or limit are set.
   *
   * @return void
   */
  protected function resetInitializeState() {
    parent::resetInitializeState();
    $this->isInitialized = FALSE;
  }

  /**
   * Returns the next record in an associative array
   *
   * @param string $fieldNameprefix
   *   The prefix before the name of the field within the record.
   * @return array
   * @throws EndOfFlowException
   */
  public function retrieveNextRecord($fieldNameprefix='') {
    if (!$this->isInitialized()) {
      $this->initialize();
    }
    do {
      if (!isset($this->data[$this->currentPointer])) {
        throw new EndOfFlowException();
      }
      $data = $this->data[$this->currentPointer];
      $record = [];
      foreach ($this->dataSpecification->getFields() as $field) {
        $alias = $field->alias;
        $name = $field->name;
        $record[$fieldNameprefix . $alias] = $data[$name];
      }
      $this->currentPointer++;
    } while (!$this->filterRecord($record));

    return $record;
  }

  /**
   * @param $record
   *
   * @return bool
   */
  protected function filterRecord($record) {
    foreach($this->filters as $filter) {
      if (!$filter->filterRecord($record)) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Returns a name for this data flow.
   *
   * @return string
   */
  public function getName() {
    return 'in_memory';
  }

  /**
   * Adds a filter to the data flow.
   *
   * @param \Civi\DataProcessor\DataFlow\InMemoryDataFlow\FilterInterface $filter
   */
  public function addFilter(FilterInterface $filter) {
    if (!in_array($filter, $this->filters)) {
      $this->filters[] = $filter;
    }
  }

  /**
   * Removes a filter from the data flow.
   *
   * @param \Civi\DataProcessor\DataFlow\InMemoryDataFlow\FilterInterface $filter
   */
  public function removeFilter(FilterInterface $filter) {
    $key = array_search($filter, $this->filters);
    if ($key!==false) {
      unset($this->filters[$key]);
    }
  }

  /**
   * @return \Civi\DataProcessor\DataFlow\InMemoryDataFlow\FilterInterface[]
   */
  public function getFilters() {
    return $this->filters;
  }

}
