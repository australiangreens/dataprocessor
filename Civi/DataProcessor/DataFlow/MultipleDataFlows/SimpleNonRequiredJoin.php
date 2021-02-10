<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

namespace Civi\DataProcessor\DataFlow\MultipleDataFlows;

use Civi\DataProcessor\DataFlow\AbstractDataFlow;
use Civi\DataProcessor\DataFlow\CombinedDataFlow\CombinedSqlDataFlow;
use Civi\DataProcessor\DataFlow\SqlDataFlow;
use Civi\DataProcessor\DataFlow\SqlTableDataFlow;
use Civi\DataProcessor\ProcessorType\AbstractProcessorType;
use Civi\DataProcessor\DataFlow\SqlDataFlow\WhereClauseInterface;

class SimpleNonRequiredJoin  extends  SimpleJoin {

  /**
   * @var WhereClauseInterface[]
   */
  protected $filterClauses = array();

  public function __construct($left_prefix = null, $left_field = null, $right_prefix = null, $right_field = null, $type = "LEFT") {
    parent::__construct($left_prefix, $left_field, $right_prefix, $right_field, $type);
  }

  /**
   * @param array $configuration
   *
   * @return \Civi\DataProcessor\DataFlow\MultipleDataFlows\JoinInterface
   */
  public function setConfiguration($configuration) {
    return parent::setConfiguration($configuration);
  }


  /**
   * @param WhereClauseInterface $clause
   *
   * @return \Civi\DataProcessor\DataFlow\MultipleDataFlows\JoinInterface
   */
  public function addFilterClause(WhereClauseInterface $clause) {
    $this->filterClauses[] = $clause;
    return $this;
  }

  /**
   * Returns the SQL join statement
   *
   * For example:
   *  INNER JOIN civicrm_contact source_3 ON source_3.id = source_2.contact_id
   * OR
   *  LEFT JOIN civicrm_contact source_3 ON source3.id = source_2.contact_id
   *
   * @param \Civi\DataProcessor\DataFlow\MultipleDataFlows\DataFlowDescription $sourceDataFlowDescription
   *   The source data flow description used to genereate the join stament.
   *
   * @return string
   */
  public function getJoinClause(DataFlowDescription $sourceDataFlowDescription) {
    $this->initialize();
    $joinClause = "ON 1";
    if ($this->left_table && $this->right_table && $sourceDataFlowDescription->getJoinSpecification()) {
      $leftColumnName = "`{$this->left_table}`.`{$this->left_field}`";
      if ($this->leftFieldSpec) {
        $leftColumnName = $this->leftFieldSpec->getSqlColumnName($this->left_table);
      }
      $rightColumnName = "`{$this->right_table}`.`{$this->right_field}`";
      if ($this->rightFieldSpec) {
        $rightColumnName = $this->rightFieldSpec->getSqlColumnName($this->right_table);
      }
      $joinClause = "ON {$leftColumnName}  = {$rightColumnName}";
    }
    if ($sourceDataFlowDescription->getDataFlow() instanceof SqlDataFlow) {
      $tablePart = $sourceDataFlowDescription->getDataFlow()->getTableStatement();
    }

    $extraClause  = "";
    $dataFlow = $sourceDataFlowDescription->getDataFlow();
    if ($dataFlow  instanceof  SqlDataFlow) {
      $whereClauses = $dataFlow->getWhereClauses();
      foreach($whereClauses as $whereClause) {
        if ($whereClause->isJoinClause() && $whereClause) {
          $this->filterClauses[] = $whereClause;
          $dataFlow->removeWhereClause($whereClause);
        }
      }
    }
    if (count($this->filterClauses)) {
      $extraClauses = array();
      foreach($this->filterClauses as $filterClause) {
        $extraClauses[] = $filterClause->getWhereClause();
      }
      $extraClause = " AND (".implode(" AND ", $extraClauses). ")";
    }

    return "{$this->type} JOIN {$tablePart} {$joinClause} {$extraClause}";
  }


}
