<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

namespace Civi\DataProcessor\DataSpecification;

class FixedValueSqlFieldSpecification extends FieldSpecification {

  /**
   * @var String
   */
  public $value;

  public function __construct($value, $alias, $type, $title) {
    $this->name = $alias;
    $this->alias = $alias;
    $this->type = $type;
    $this->title = $title;
    if ($value) {
      $this->value = \CRM_Utils_Type::escape($value, $type);
    } else {
      $this->value = "NULL";
    }
  }

  /**
   * Returns the select statement for this field.
   * E.g. COUNT(civicrm_contact.id) AS contact_id_count
   *
   * @param String $table_alias
   *
   * @return String
   */
  public function getSqlSelectStatement($table_alias) {
    return "{$this->value} as `{$this->alias}`";
  }

  /**
   * Returns the group by statement for this field.
   * E.g. civicrm_contribution.financial_type_id
   * or MONTH(civicrm_contribution.receive_date)
   *
   * @param String $table_alias
   *
   * @return String
   */
  public function getSqlGroupByStatement($table_alias) {
    return "`{$this->alias}`";
  }

  /**
   * Returns the SQL column name for this field.
   * This could be used in join statements
   *
   * @param $table_alias
   *
   * @return string
   */
  public function getSqlColumnName($table_alias) {
    return "`{$this->alias}`";
  }


}
