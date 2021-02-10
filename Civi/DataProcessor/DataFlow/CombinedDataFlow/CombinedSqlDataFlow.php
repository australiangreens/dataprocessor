<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

namespace Civi\DataProcessor\DataFlow\CombinedDataFlow;

use \Civi\DataProcessor\DataFlow\EndOfFlowException;
use Civi\DataProcessor\DataFlow\InvalidFlowException;
use Civi\DataProcessor\DataFlow\MultipleDataFlows\DataFlowDescription;
use Civi\DataProcessor\DataFlow\MultipleDataFlows\JoinInterface;
use Civi\DataProcessor\DataFlow\MultipleDataFlows\MultipleSourceDataFlows;
use Civi\DataProcessor\DataFlow\MultipleDataFlows\SqlJoinInterface;
use Civi\DataProcessor\DataFlow\SqlDataFlow;
use Civi\DataProcessor\DataFlow\SqlTableDataFlow;
use \Civi\DataProcessor\DataSpecification\DataSpecification;


class CombinedSqlDataFlow extends SqlDataFlow implements MultipleSourceDataFlows {

  /**
   * @var \Civi\DataProcessor\DataFlow\MultipleDataFlows\DataFlowDescription[]
   */
  protected $sourceDataFlowDescriptions = array();

  /**
   * @var null|String
   */
  protected $primary_table;

  /**
   * @var null|String
   */
  protected $primary_table_alias;

  /**
   * @var String
   */
  protected $name;

  public function __construct($name = 'combined_sql_data_flow', $primary_table=null, $primary_table_alias=null) {
    parent::__construct();
    $this->primary_table = $primary_table;
    $this->primary_table_alias = $primary_table_alias;
    $this->name = $name;
  }

  /**
   * Adds a source data flow
   *
   * @param \Civi\DataProcessor\DataFlow\MultipleDataFlows\DataFlowDescription $dataFlowDescription
   * @return void
   * @throws \Civi\DataProcessor\DataFlow\InvalidFlowException
   */
  public function addSourceDataFlow(DataFlowDescription $dataFlowDescription) {
    if (!$dataFlowDescription->getDataFlow() instanceof SqlDataFlow) {
      throw new InvalidFlowException();
    }
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
    if (!$dataFlowDescription->getDataFlow() instanceof SqlDataFlow) {
      throw new InvalidFlowException();
    }
    foreach($this->sourceDataFlowDescriptions as $idx => $sourceDataFlowDescription) {
      if ($sourceDataFlowDescription === $dataFlowDescription) {
        unset($this->sourceDataFlowDescriptions[$idx]);
      }
    }
  }

  /**
   * Returns the Table part in the from statement.
   *
   * @return string
   */
  public function getTableStatement() {
    $sourceDataFlowDescription = reset($this->sourceDataFlowDescriptions);
    $dataFlow = $sourceDataFlowDescription->getDataFlow();
    return $dataFlow->getTableStatement();
  }

  /**
   * Returns the From Statement.
   *
   * @return string
   */
  public function getFromStatement() {
    $fromStatements = array();
    $sourceDataFlowDescription = reset($this->sourceDataFlowDescriptions);
    $dataFlow = $sourceDataFlowDescription->getDataFlow();
    $fromStatements[] = $dataFlow->getFromStatement();
    $fromStatements = array_merge($fromStatements, $this->getJoinStatement(1));
    return implode(" ", $fromStatements);
  }

  /**
   * Returns the join Statement part.
   *
   * @param int $skip
   * @return string
   */
  public function getJoinStatement($skip=0) {
    $fromStatements = array();
    $i = 0;
    foreach($this->sourceDataFlowDescriptions as $sourceDataFlowDescription) {
      $i++;
      if ($i > $skip) {
        if ($sourceDataFlowDescription->getJoinSpecification()) {
          $joinStatement = $sourceDataFlowDescription->getJoinSpecification()->getJoinClause($sourceDataFlowDescription);
          if (is_array($joinStatement)) {
            $fromStatements = array_merge($fromStatements, $joinStatement);
          } else {
            $fromStatements[] = $joinStatement;
          }
        }
        if ($sourceDataFlowDescription->getDataFlow() instanceof CombinedSqlDataFlow) {
          $fromStatements = array_merge($fromStatements, $sourceDataFlowDescription->getDataFlow()->getJoinStatement(0));
        }
      }
    }
    return $fromStatements;
  }

  /**
   * Returns an array with the fields for in the select statement in the sql query.
   *
   * @return string[]
   */
  public function getFieldsForSelectStatement() {
    $fields = array();
    foreach($this->sourceDataFlowDescriptions as $sourceDataFlowDescription) {
      $fields = array_merge($fields, $sourceDataFlowDescription->getDataFlow()->getFieldsForSelectStatement());
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
    foreach($this->sourceDataFlowDescriptions as $sourceDataFlowDescription) {
      if ($sourceDataFlowDescription->getDataFlow() instanceof SqlDataFlow) {
        $fields = array_merge($fields, $sourceDataFlowDescription->getDataFlow()->getFieldsForGroupByStatement());
      }
    }
    return $fields;
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
    foreach($this->sourceDataFlowDescriptions as $sourceDataFlowDescription) {
      foreach ($sourceDataFlowDescription->getDataFlow()->getDataSpecification()->getFields() as $field) {
        $alias = $field->alias;
        $record[$alias] = $this->dao->$alias;
      }
    }
    return $record;
  }

  /**
   * @return DataSpecification
   * @throws \Civi\DataProcessor\DataSpecification\FieldExistsException
   */
  public function getDataSpecification() {
    if (!$this->dataSpecification) {
      $this->dataSpecification = new DataSpecification();
      foreach ($this->sourceDataFlowDescriptions as $sourceDataFlowDescription) {
        $dataFlow = $sourceDataFlowDescription->getDataFlow();
        $namePrefix = $dataFlow->getName();
        $this->dataSpecification->merge($dataFlow->getDataSpecification(), $namePrefix);
      }
    }
    return $this->dataSpecification;
  }

  public function getName() {
    return $this->name;
  }

  /**
   * Return all the where clauses
   *
   * @param bool $includeJoinClause
   * @param bool $includeNonJoinClause
   * @return array
   */
  public function getWhereClauses($includeJoinClause=TRUE, $includeNonJoinClause=TRUE) {
    $clauses = parent::getWhereClauses($includeJoinClause, $includeNonJoinClause);
    foreach($this->sourceDataFlowDescriptions as $sourceDataFlowDescription) {
      if ($sourceDataFlowDescription->getDataFlow() instanceof SqlDataFlow && !$sourceDataFlowDescription->getDataFlow() instanceof SubqueryDataFlow) {
        foreach($sourceDataFlowDescription->getDataFlow()->getWhereClauses() as $clause) {
          $clauses[] = $clause;
        }
      }
    }
    return $clauses;
  }

  /**
   * @return null|String
   */
  public function getPrimaryTable() {
    return $this->primary_table;
  }

  /**
   * @return null|String
   */
  public function getPrimaryTableAlias() {
    return $this->primary_table_alias;
  }

  /**
   * @param \Civi\DataProcessor\DataFlow\SqlDataFlow\WhereClauseInterface $clause
   *
   * @return \Civi\DataProcessor\DataFlow\SqlDataFlow
   */
  public function removeWhereClause(SqlDataFlow\WhereClauseInterface $clause) {
    foreach($this->whereClauses as  $i => $c) {
      if ($c->getWhereClause() == $clause->getWhereClause()) {
        unset($this->whereClauses[$i]);
      }
    }
    foreach($this->sourceDataFlowDescriptions as $sourceDataFlowDescription) {
      if ($sourceDataFlowDescription->getDataFlow() instanceof SqlDataFlow && !$sourceDataFlowDescription->getDataFlow() instanceof SubqueryDataFlow) {
        $sourceDataFlowDescription->getDataFlow()->removeWhereClause($clause);
      }
    }
    return $this;
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
    $sourceDataFlowDescriptions = array();
    foreach($this->sourceDataFlowDescriptions as $sourceDataFlowDescription) {
      $sourceDataFlowDescriptions[] = clone $sourceDataFlowDescription;
    }
    $this->sourceDataFlowDescriptions = $sourceDataFlowDescriptions;
  }

}
