<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

namespace Civi\DataProcessor\DataFlow\SqlDataFlow;

class SimpleWhereClause implements WhereClauseInterface {

  protected $table_alias;

  protected $field;

  protected $operator;

  protected $value;

  protected $isJoinClause = FALSE;

  protected $sqlFunction = null;

  public function __construct($table_alias, $field, $operator, $value, $valueType = 'String', $isJoinClause=FALSE, $sqlFunction=null) {
    if (is_array($value)) {
      switch ($operator) {
        case '=':
          $operator = 'IN';
          break;
        case '!=':
          $operator = 'NOT IN';
          break;

        case 'IS NULL':
          $operator = 'IS NULL';
          break;
        case 'IS NOT NULL':
          $operator = 'IS NOT NULL';
          break;
      }
    }

    $this->isJoinClause = $isJoinClause;
    $this->table_alias = $table_alias;
    $this->field = $field;
    $this->operator = $operator;

    if ($operator == 'IS NULL' || $operator == 'IS NOT NULL') {
      $this->value = NULL;
      return;
    }

    if (is_array($value)) {
      $esacpedValues = array();
      foreach($value as $val) {
        switch ($valueType) {
          case 'String':
          case 'Text':
          case 'Memo':
            $esacpedValues[] = "'" . \CRM_Utils_Type::escape($val, $valueType) . "'";
            break;
          default:
            $esacpedValues[] = \CRM_Utils_Type::escape($val, $valueType);
            break;
        }
      }
      if ($operator == 'BETWEEN' || $operator == 'NOT BETWEEN') {
        $this->value = implode(" AND ", $esacpedValues);
      }
      else {
        $this->value = "(" . implode(", ", $esacpedValues) . ")";
      }
    } else {
      switch ($valueType) {
        case 'String':
        case 'Text':
        case 'Memo':
          $this->value = "'" . \CRM_Utils_Type::escape($value, $valueType) . "'";
          break;

        default:
          $this->value = \CRM_Utils_Type::escape($value, $valueType);
          break;
      }
    }

    if ($sqlFunction) {
      $this->sqlFunction = $sqlFunction;
    }
  }

  /**
   * Returns true when this where clause can be added to the
   * join or whether this clause should be propagated to the where part of the query
   *
   * @return bool
   */
  public function isJoinClause() {
    return $this->isJoinClause;
  }

  /**
   * Returns the where clause
   * E.g. contact_type = 'Individual'
   *
   * @return string
   */
  public function getWhereClause() {
    if ($this->isJoinClause()) {
      return "`{$this->table_alias}`.`{$this->field}` {$this->operator} {$this->value}";
    }
    $fieldStatement = "`{$this->table_alias}`.`{$this->field}`";
    if ($this->sqlFunction) {
      $fieldStatement = sprintf($this->sqlFunction, $fieldStatement);
    }
    switch ($this->operator) {
      case 'NOT IN':
      case 'NOT LIKE':
      case '!=':
        return "({$fieldStatement} {$this->operator} {$this->value} OR `{$this->table_alias}`.`{$this->field}` IS NULL)";
        break;
      case 'IS NULL':
        return "(`{$this->table_alias}`.`{$this->field}` {$this->operator} OR `{$this->table_alias}`.`{$this->field}` = '')";
        break;
      case 'IS NOT NULL':
        return "(`{$this->table_alias}`.`{$this->field}` {$this->operator} AND `{$this->table_alias}`.`{$this->field}` != '')";
        break;
      default:
        return "{$fieldStatement} {$this->operator} {$this->value}";
        break;
    }
  }

}
