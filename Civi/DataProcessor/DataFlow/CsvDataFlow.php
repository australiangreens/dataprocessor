<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

namespace Civi\DataProcessor\DataFlow;

use CRM_Dataprocessor_ExtensionUtil as E;

class CsvDataFlow extends InMemoryDataFlow {

  protected $data = [];

  protected $currentPointer = 0;

  protected $isInitialized = FALSE;

  protected $uri;

  protected $delimiter = ',';

  protected $enclosure = '"';

  protected $escape = '\\';

  protected $skipRows = 1;

  private $uriHandle;

  public function __construct($uri, $skipRows = 1, $delimiter=',', $enclosure='"', $escape='\\') {
    parent::__construct();
    $this->uri = $uri;
    $this->skipRows = $skipRows;
    $this->delimiter = $delimiter;
    $this->enclosure = $enclosure;
    $this->escape = $escape;
  }

  /**
   * Returns the header row
   *
   * @param $headerRowNumber
   * @return array
   */
  public function getHeaderRow($headerRowNumber=0) {
    $header = array();
    $handle = fopen($this->uri, 'r');
    for($i=1; $i<$headerRowNumber; $i++) {
      $skipRow = fgetcsv($handle, 0, $this->delimiter, $this->enclosure, $this->escape);
      if ($i == 0) {
        // This is the first row, initialize the header with at least as many columns as this row
        foreach($skipRow as $col_idx => $col) {
          $header[$col_idx] = E::ts('Column %1', array(1=>$col_idx));
        }
      }
    }
    if ($headerRowNumber) {
      $headerRow = fgetcsv($handle, 0, $this->delimiter, $this->enclosure, $this->escape);
      if ($headerRow) {
        foreach ($headerRow as $col_idx => $col) {
          $header[$col_idx] = $col;
        }
      }
    }
    fclose($handle);
    return $header;
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

    $this->uriHandle = fopen($this->uri, 'r');
    for($i=0; $i<$this->skipRows; $i++) {
      $skipRow = fgetcsv($this->uriHandle, 0, $this->delimiter, $this->enclosure, $this->escape);
    }

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
    $this->initialize();

    do {
      $row = fgetcsv($this->uriHandle, 0, $this->delimiter, $this->enclosure, $this->escape);
      if (!$row) {
        throw new EndOfFlowException();
      }

      $record = array();
      foreach($this->dataSpecification->getFields() as $field) {
        $alias = $field->alias;
        $col_index = str_replace("col_", "", $field->name);
        $record[$fieldNameprefix.$alias] = $row[$col_index];
      }
    } while (!$this->filterRecord($record));

    return $record;
  }

  /**
   * Returns a name for this data flow.
   *
   * @return string
   */
  public function getName() {
    return 'csv';
  }

}
