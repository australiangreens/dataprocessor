<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

namespace Civi\DataProcessor\DataFlow;

use Civi\DataProcessor\DataFlow\Sort\SortSpecification;
use Civi\DataProcessor\DataFlow\SqlDataFlow\WhereClauseInterface;
use Civi\DataProcessor\DataFlow\Utils\Aggregator;
use \Civi\DataProcessor\DataSpecification\DataSpecification;

abstract class SqlDataFlow extends AbstractDataFlow {

  /**
   * @var null|\CRM_Core_DAO
   */
  protected $dao = null;

  /**
   * @var null|int
   */
  protected $count = null;

  protected $whereClauses = array();

  protected $sqlStatements = array();

  protected $sqlCountStatements = array();

  /**
   * Returns an array with the fields for in the select statement in the sql query.
   *
   * @return string[]
   * @throws \Civi\DataProcessor\DataSpecification\FieldExistsException
   */
  abstract public function getFieldsForSelectStatement();

  /**
   * Returns an array with the fields for in the group by statement in the sql query.
   *
   * @return string[]
   * @throws \Civi\DataProcessor\DataSpecification\FieldExistsException
   */
  public function getFieldsForGroupByStatement() {
    $fields = array();
    foreach($this->aggregateOutputHandlers as $outputHandler) {
      $fields[] = $outputHandler->getAggregateFieldSpec()->getSqlGroupByStatement($this->getName());
    }
    return $fields;
  }

  /**
   * Returns the Table part in the from statement.
   *
   * @return string
   */
  abstract public function getTableStatement();

  /**
   * Returns the From Statement.
   *
   * @return string
   */
  public function getFromStatement() {
    return "FROM {$this->getTableStatement()}";
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

    try {
      $selectAndFrom = $this->getSelectQueryStatement();
      $where = $this->getWhereStatement();
      $groupBy = $this->getGroupByStatement();
      $orderBy = $this->getOrderByStatement();
      $countName = 'count_'.$this->getName();
      $sql = "{$selectAndFrom} {$where} {$groupBy} {$orderBy}";
      $countSql = "SELECT COUNT(*) AS count FROM ({$sql}) `{$countName}`";

      //$countSql = "SELECT COUNT(*) AS `count` {$from} {$where} {$groupBy}";
      $this->sqlCountStatements[] = $countSql;
      $countDao = \CRM_Core_DAO::executeQuery($countSql);
      $this->count = 0;
      while ($countDao->fetch()) {
        $this->count = $this->count + $countDao->count;
      }

      // Build Limit and Offset.
      $limitStatement = "";
      if ($this->offset !== FALSE && $this->limit !== FALSE) {
        $limitStatement = "LIMIT {$this->offset}, {$this->limit}";
      }
      elseif ($this->offset === FALSE && $this->limit !== FALSE) {
        $limitStatement = "LIMIT 0, {$this->limit}";
      }
      elseif ($this->offset !== FALSE && $this->limit === FALSE) {
        $calculatedLimit = $this->count - $this->offset;
        $limitStatement = "LIMIT {$this->offset}, {$calculatedLimit}";
      }
      $sql .= " {$limitStatement}";
      $this->sqlStatements[] = $sql;
      $this->dao = \CRM_Core_DAO::executeQuery($sql);
    } catch (\Exception $e) {
      throw new \Exception(
        "Error in DataFlow query.
        \r\nData flow: {$this->getName()}
        \r\nCount query: {$countSql}
        \r\nQuery: $sql
        \r\nMessage: {$e->getMessage()}", 0, $e);


    }
  }

  /**
   * Returns whether this flow has been initialized or not
   *
   * @return bool
   */
  public function isInitialized() {
    if ($this->dao !== null) {
      return true;
    }
    return false;
  }

  /**
   * Resets the initialized state. This function is called
   * when a setting has changed. E.g. when offset or limit are set.
   *
   * @return void
   */
  protected function resetInitializeState() {
    parent::resetInitializeState();
    $this->dao = null;
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

    if (!$this->dao->fetch()) {
      throw new EndOfFlowException();
    }
    $record = array();
    foreach($this->dataSpecification->getFields() as $field) {
      $alias = $field->alias;
      $record[$fieldNamePrefix.$alias] = $this->dao->$alias;
    }
    return $record;
  }

  /**
   * @return int
   */
  public function recordCount() {
    if (!$this->isInitialized()) {
      $this->initialize();
    }
    return $this->count;
  }

  /**
   * @return string
   * @throws \Civi\DataProcessor\DataSpecification\FieldExistsException
   */
  public function getSelectQueryStatement() {
    $select = implode(", ", $this->getFieldsForSelectStatement());
    $from = $this->getFromStatement();
    return "SELECT DISTINCT {$select} {$from}";
  }

  public function getGroupByStatement() {
    $groupByFields = $this->getFieldsForGroupByStatement();
    if (!count($groupByFields)) {
      return "";
    }
    return "GROUP BY ".implode(", ", $groupByFields);
  }

  /**
   * Returns the where statement for this query.
   *
   * @return string
   */
  public function getWhereStatement() {
    $clauses = array("1");
    foreach($this->getWhereClauses() as $clause) {
      $clauses[] = $clause->getWhereClause();
    }
    return "WHERE ". implode(" AND ", $clauses);
  }

  /**
   * @param \Civi\DataProcessor\DataFlow\SqlDataFlow\WhereClauseInterface $clause
   *
   * @return \Civi\DataProcessor\DataFlow\SqlDataFlow
   */
  public function addWhereClause(WhereClauseInterface $clause) {
    foreach($this->whereClauses as $c) {
      if ($c->getWhereClause() == $clause->getWhereClause()) {
        return $this; // Where clause is already added do not add it again.
      }
    }
    $this->whereClauses[] = $clause;
    return $this;
  }

  /**
   * @param \Civi\DataProcessor\DataFlow\SqlDataFlow\WhereClauseInterface $clause
   *
   * @return \Civi\DataProcessor\DataFlow\SqlDataFlow
   */
  public function removeWhereClause(WhereClauseInterface $clause) {
    foreach($this->whereClauses as  $i => $c) {
      if ($c->getWhereClause() == $clause->getWhereClause()) {
        unset($this->whereClauses[$i]);
      }
    }
    return $this;
  }

  /**
   * Return all the where clauses
   *
   * @return array
   */
  public function getWhereClauses() {
    return $this->whereClauses;
  }

  /**
   * Get the order by statement
   *
   * @return string
   */
  public function getOrderByStatement() {
    $orderBys = array();
    foreach($this->sortSpecifications as $sortSpecification) {
      $dir = 'ASC';
      switch($sortSpecification->getDirection()) {
        case SortSpecification::DESC:
          $dir = 'DESC';
          break;
      }
      if ($sortSpecification->getField()) {
        $fieldName = $sortSpecification->getField()->alias;
        $orderBys[] = "`{$fieldName}` {$dir}";
      }
    }
    if (count($orderBys)) {
      return "ORDER BY ".implode(", ", $orderBys);
    }
    return "";
  }

  /**
   * Returns debug information
   *
   * @return string
   */
  public function getDebugInformation() {
    return array(
      'query' => $this->sqlStatements,
      'count query' => $this->sqlCountStatements,
    );
  }

  /**
   * @return \CRM_Core_DAO|null
   */
  public function getDataObject() {
    return $this->dao;
  }

  /**
   * @param $records
   * @param string $fieldNameprefix
   *
   * @return array();
   */
  protected function aggregate($records, $fieldNameprefix="") {
    // Aggregation is done in the database.
    return $records;
  }

}
