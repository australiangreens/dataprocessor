<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

namespace Civi\DataProcessor\DataFlow\InMemoryDataFlow;

class SimpleFilter implements FilterInterface {

  protected $field;

  protected $operator;

  protected $value;

  protected $valueCallBackFunction;

  public function __construct($field, $operator, $value, $valueCallBackFunction=null) {
    if (is_array($value)) {
      switch ($operator) {
        case '=':
          $operator = 'IN';
          break;
        case '!=':
          $operator = 'NOT IN';
          break;
      }
    }

    $this->field = $field;
    $this->operator = $operator;
    if ($operator == 'IS NULL' || $operator == 'IS NOT NULL') {
      $this->value = NULL;
    } else {
      $this->value = $value;
    }

    if ($valueCallBackFunction) {
      $this->valueCallBackFunction = $valueCallBackFunction;
    }
  }

  /**
   * Returns true when the record is in the filter.
   * Returns false when the reocrd is not in the filter.
   *
   * @param $record
   *
   * @return bool
   */
  public function filterRecord($record) {
    if (isset($record[$this->field]) && is_callable($this->valueCallBackFunction)) {
      $record[$this->field] = call_user_func($this->valueCallBackFunction, $record[$this->field]);
    }
    switch ($this->operator) {
      case 'IS NULL':
        return !(isset($record[$this->field]) && $record[$this->field]!='');
        break;
      case 'IS NOT NULL':
        return (isset($record[$this->field]) && $record[$this->field]!='');
        break;
      case '=':
        return (isset($record[$this->field]) && $record[$this->field]==$this->value);
        break;
      case '!=':
        return (!isset($record[$this->field]) || $record[$this->field]!=$this->value);
        break;
      case '>':
        return (isset($record[$this->field]) && $record[$this->field]>$this->value);
        break;
      case '<':
        return (isset($record[$this->field]) && $record[$this->field]<$this->value);
        break;
      case '>=':
        return (isset($record[$this->field]) && $record[$this->field]>=$this->value);
        break;
      case '<=':
        return (isset($record[$this->field]) && $record[$this->field]<=$this->value);
        break;
      case 'IN':
        return (isset($record[$this->field]) && in_array($record[$this->field], $this->value));
        break;
      case 'NOT IN':
        return !(isset($record[$this->field]) && in_array($record[$this->field], $this->value));
        break;
    }
  }

  public function getField() {
    return $this->field;
  }

  public function getOperator() {
    return $this->operator;
  }

  public function getValue() {
    return $this->value;
  }


}
