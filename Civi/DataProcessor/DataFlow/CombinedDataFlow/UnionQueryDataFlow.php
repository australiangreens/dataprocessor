<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

namespace Civi\DataProcessor\DataFlow\CombinedDataFlow;

use Civi\DataProcessor\DataFlow\EndOfFlowException;
use Civi\DataProcessor\DataFlow\InvalidFlowException;
use Civi\DataProcessor\DataFlow\MultipleDataFlows\DataFlowDescription;
use Civi\DataProcessor\DataFlow\MultipleDataFlows\MultipleSourceDataFlows;
use Civi\DataProcessor\DataFlow\SqlDataFlow;
use Civi\DataProcessor\DataFlow\SqlTableDataFlow;
use Civi\DataProcessor\DataSpecification\DataSpecification;
use Civi\DataProcessor\DataSpecification\SqlFieldSpecification;

class UnionQueryDataFlow extends SqlDataFlow implements MultipleSourceDataFlows {

  /**
   * @var \Civi\DataProcessor\DataFlow\MultipleDataFlows\DataFlowDescription[]
   */
  protected $sourceDataFlowDescriptions = array();

  /**
   * @var String
   */
  protected $name;

  public function __construct($name) {
    parent::__construct();
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

  public function getName() {
    return $this->name;
  }

  /**
   * Returns the From Statement.
   *
   * @return string
   */
  public function getFromStatement() {
    return "FROM {$this->getTableStatement()}";
  }

  /**
   * Returns the Table part in the from statement.
   *
   * @return string
   */
  public function getTableStatement() {
    $selectStatements = array();
    foreach($this->sourceDataFlowDescriptions as $sourceDataFlowDescription) {
      $sourceDataFlow = $sourceDataFlowDescription->getDataFlow();
      if ($sourceDataFlow instanceof SqlDataFlow) {
        $selectAndFrom = $sourceDataFlow->getSelectQueryStatement();
        $where = $sourceDataFlow->getWhereStatement();
        $groupBy = $sourceDataFlow->getGroupByStatement();
        $selectStatements[] = "{$selectAndFrom} {$where} {$groupBy}";
      }
    }

    $sql = implode(" UNION ", $selectStatements);

    return "({$sql}) `{$this->getName()}`";
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
        $fields[] = $field->getSqlSelectStatement($this->getName());
      } else {
        $fields[] = "`{$this->getName()}`.`{$field->name}` AS `{$field->alias}`";
      }
    }
    return $fields;
  }

}
