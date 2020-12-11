<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

namespace Civi\DataProcessor\DataFlow\Sort;

use Civi\DataProcessor\DataSpecification\FieldSpecification;
use Civi\DataProcessor\DataFlow\AbstractDataFlow;
use Civi\DataProcessor\FieldOutputHandler\OutputHandlerSortable;

class SortSpecification {

  const ASC = "ASC";
  const DESC = "DESC";

  /**
   * @var FieldSpecification
   */
  protected $field;

  /**
   * @var String
   */
  protected $fieldName;

  /**
   * @var string
   *   The direction of the sort either ASC or DESC
   */
  protected $direction;

  /**
   * @var AbstractDataFlow
   */
  protected $dataFlow;

  /**
   * @var \Civi\DataProcessor\DataFlow\Sort\SortComparer
   */
  protected $comparer;

  public function __construct(AbstractDataFlow $dataFlow=null, $fieldName="", $direction=self::ASC) {
    $this->dataFlow = $dataFlow;
    $this->direction = $direction;
    $this->fieldName = $fieldName;
    $this->setField($fieldName);
  }

  /**
   * @param \Civi\DataProcessor\DataFlow\AbstractDataFlow $dataFlow
   * @return \Civi\DataProcessor\DataFlow\Sort\SortSpecification
   */
  public function setDataFlow(AbstractDataFlow $dataFlow) {
    $this->dataFlow = $dataFlow;
    return $this;
  }

  /**
   * @param $fieldName
   * @return $this
   */
  public function setField($fieldName) {
    if ($fieldName && $this->dataFlow) {
      foreach($this->dataFlow->getOutputFieldHandlers() as $outputFieldHandler) {
        if ($outputFieldHandler->getOutputFieldSpecification()->alias == $fieldName && $outputFieldHandler instanceof OutputHandlerSortable) {
          $this->field = $outputFieldHandler->getSortableInputFieldSpec();
        }
      }
    }
    return $this;
  }

  /**
   * @param $direction
   * @return $this
   */
  public function setDirection($direction) {
    $this->direction = $direction;
    return $this;
  }

  /**
   * @return string
   */
  public function getDirection() {
    return $this->direction;
  }

  /**
   * @return \Civi\DataProcessor\DataSpecification\FieldSpecification
   */
  public function getField() {
    return $this->field;
  }

  /**
   * @return \Civi\DataProcessor\DataFlow\Sort\SortComparer;
   */
  protected function getComparer(){
    $factory = dataprocessor_get_factory();
    $sortFactory = $factory->getSortCompareFactory();
    return $sortFactory->getComparer($this->field->type);
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
  public function compare($row_a, $row_b) {
    $comparer = $this->getComparer();
    $compareValue = $comparer->sort($row_a[$this->fieldName]->rawValue, $row_b[$this->fieldName]->rawValue);
    if ($this->direction == self::DESC) {
      if ($compareValue < 0) {
        $compareValue = 1;
      } elseif ($compareValue > 0) {
        $compareValue = -1;
      }
    }
    return $compareValue;
  }


}
