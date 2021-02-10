<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

namespace Civi\DataProcessor\DataFlow\CombinedDataFlow;

use Civi\DataProcessor\DataFlow\EndOfFlowException;
use Civi\DataProcessor\DataFlow\SqlDataFlow;
use Civi\DataProcessor\DataFlow\SqlTableDataFlow;
use Civi\DataProcessor\DataSpecification\DataSpecification;
use Civi\DataProcessor\DataSpecification\SqlFieldSpecification;

class SubqueryDataFlow extends CombinedSqlDataFlow {

  /**
   * Returns the From Statement.
   *
   * @return string
   */
  public function getFromStatement() {
    return "FROM {$this->getTableStatement()}";
  }

  /**
   * Returns the join Statement part.
   *
   * @param int $skip
   * @return string
   */
  public function getJoinStatement($skip=0) {
    $fromStatements = array();
    return $fromStatements;
  }

  /**
   * Returns the Table part in the from statement.
   *
   * @return string
   */
  public function getTableStatement() {
    $fields = array();
    $groupByFields = array();
    foreach($this->sourceDataFlowDescriptions as $sourceDataFlowDescription) {
      $fields = array_merge($fields, $sourceDataFlowDescription->getDataFlow()->getFieldsForSelectStatement());
      if ($sourceDataFlowDescription->getDataFlow() instanceof SqlDataFlow) {
        $groupByFields = array_merge($groupByFields, $sourceDataFlowDescription->getDataFlow()->getFieldsForGroupByStatement());
      }
    }

    $fromStatements = array();
    $sourceDataFlowDescription = reset($this->sourceDataFlowDescriptions);
    $dataFlow = $sourceDataFlowDescription->getDataFlow();
    $fromStatements[] = $dataFlow->getFromStatement();

    foreach($this->sourceDataFlowDescriptions as $sourceDataFlowDescription) {
      if ($sourceDataFlowDescription->getJoinSpecification()) {
        $joinStatement = $sourceDataFlowDescription->getJoinSpecification()
          ->getJoinClause($sourceDataFlowDescription);
        if (is_array($joinStatement)) {
          $fromStatements = array_merge($fromStatements, $joinStatement);
        } else {
          $fromStatements[] = $joinStatement;
        }
      }
      if ($sourceDataFlowDescription->getDataFlow() instanceof SubqueryDataFlow) {
        $fromStatements = array_merge($fromStatements, $sourceDataFlowDescription->getDataFlow()->getJoinStatement(0));
      }
    }

    $alias = $this->getName();
    if (empty($alias)) {
      $alias = $this->getPrimaryTableAlias();
    }
    $from = implode(" ", $fromStatements);
    $select = implode(", ", $fields);
    $where = $this->getWhereStatement();
    $groupBy = "";
    if (count($groupByFields)) {
      $groupBy = "GROUP BY ".implode(", ", $groupByFields);
    }
    return "(SELECT {$select} {$from} {$where} {$groupBy}) `{$alias}`";
  }

  /**
   * Returns an array with the fields for in the select statement in the sql query.
   *
   * @return string[]
   * @throws \Civi\DataProcessor\DataSpecification\FieldExistsException
   */
  public function getFieldsForSelectStatement() {
    $fields = array();
    foreach($this->getDataSpecification()->getFields() as $field) {
      if ($field instanceof SqlFieldSpecification) {
        $fields[] = $field->getSqlSelectStatement($this->name);
      } else {
        $fields[] = "`{$this->name}`.`{$field->name}` AS `{$field->alias}`";
      }
    }
    return $fields;
  }

  /**
   * Returns an array with the fields for in the group by statement in the sql query.
   *
   * @return string[]
   */
  public function getFieldsForGroupByStatement() {
    $fields = array();
    foreach($this->aggregateOutputHandlers as $outputHandler) {
      $fields[] = $outputHandler->getAggregateFieldSpec()->getSqlGroupByStatement($this->getName());
    }
    return $fields;
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
    foreach ($this->getDataSpecification()->getFields() as $field) {
      $alias = $field->alias;
      $record[$alias] = $this->dao->$alias;
    }
    return $record;
  }


}
