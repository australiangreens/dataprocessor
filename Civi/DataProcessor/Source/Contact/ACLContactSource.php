<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

namespace Civi\DataProcessor\Source\Contact;

use Civi\DataProcessor\DataFlow\MultipleDataFlows\DataFlowDescription;
use Civi\DataProcessor\DataFlow\MultipleDataFlows\MultipleSourceDataFlows;
use Civi\DataProcessor\DataFlow\MultipleDataFlows\PureSqlStatementJoin;
use Civi\DataProcessor\DataFlow\SqlDataFlow\AndClause;
use Civi\DataProcessor\DataFlow\SqlDataFlow\OrClause;
use Civi\DataProcessor\DataFlow\SqlDataFlow\SimpleWhereClause;
use Civi\DataProcessor\DataFlow\SqlTableDataFlow;
use Civi\DataProcessor\DataFlow\SqlDataFlow\PureSqlStatementClause;

class ACLContactSource extends ContactSource {

  /**
   * @var \Civi\DataProcessor\DataFlow\CombinedDataFlow\CombinedSqlDataFlow
   */
  protected $dataFlow;

  protected $aclJoins = [];
  protected $aclWhereClause = null;

  public function initialize() {
    $tables = array();
    $whereTables = array();
    $where = \CRM_ACL_API::whereClause(\CRM_ACL_API::VIEW, $tables, $whereTables, NULL, FALSE, TRUE, FALSE);

    foreach ($whereTables as $tableAndAlias => $joinCriteria) {
      $this->addAclJoin($tableAndAlias, $joinCriteria);
    }
    parent::initialize();
    $this->addAclWhere($where);
  }

  /**
   * This is a more generalisable path but requires a bit more work before it
   * can be accepted into Dataprocessor
   * @param String $tableAndAlias
   * @param String $joinCriteria
   */
  protected function addAclJoin($tableAndAlias, $joinCriteria) {
    list($table, $alias) = explode(' ', $tableAndAlias);
    $aclTable = new SqlTableDataFlow($table, $alias);
    // CiviCRM's ACL hook expects the contact table to be called contact_a.
    // In DataProcessor, it might be called something else.
    $joinCriteria = str_replace('contact_a', $this->primaryDataFlow->getName(), $joinCriteria);
    $join = new PureSqlStatementJoin(' JOIN ' . $tableAndAlias . ' ON ' . $joinCriteria);
    $this->additionalDataFlowDescriptions[$tableAndAlias] = new DataFlowDescription($aclTable, $join);
    $this->aclJoins[] = $tableAndAlias;
  }

  protected function addAclWhere($where) {
    $where = str_replace('contact_a', $this->primaryDataFlow->getName(), $where);
    $this->aclWhereClause = new PureSqlStatementClause($where);
    $this->primaryDataFlow->addWhereClause($this->aclWhereClause);
  }

  /**
   * Adds an inidvidual filter to the data source
   *
   * @param $filter_field_alias
   * @param $op
   * @param $values
   *
   * @throws \Exception
   */
  protected function addFilter($filter_field_alias, $op, $values) {
    if ($filter_field_alias == 'contact_sub_type' && $op == 'IN') {
      $contactTypeClauses = [];
      foreach ($values as $value) {
        $contactTypeSearchName = '%' . \CRM_Core_DAO::VALUE_SEPARATOR . $value . \CRM_Core_DAO::VALUE_SEPARATOR . '%';
        $contactTypeClauses[] = new SimpleWhereClause($this->getSourceName(), 'contact_sub_type', 'LIKE', $contactTypeSearchName, 'String', TRUE);
      }
      if (count($contactTypeClauses)) {
        $contactTypeClause = new OrClause($contactTypeClauses, TRUE);
        $entityDataFlow = $this->ensureEntity();
        $entityDataFlow->addWhereClause($contactTypeClause);
      }
    } elseif ($filter_field_alias == 'contact_sub_type' && $op == 'NOT IN') {
      $contactTypeClauses = [];
      foreach($values as $value) {
        $contactTypeSearchName = '%'.\CRM_Core_DAO::VALUE_SEPARATOR.$value.\CRM_Core_DAO::VALUE_SEPARATOR.'%';
        $contactTypeClauses[] = new SimpleWhereClause($this->getSourceName(), 'contact_sub_type', 'NOT LIKE', $contactTypeSearchName, 'String',TRUE);
      }
      if (count($contactTypeClauses)) {
        $contactTypeClause = new AndClause($contactTypeClauses, TRUE);
        $entityDataFlow = $this->ensureEntity();
        $entityDataFlow->addWhereClause($contactTypeClause);
      }
    } else {
      parent::addFilter($filter_field_alias, $op, $values);
    }
  }

  /**
   * This function is called after a source is loaded from the cache.
   * @return void
   */
  public function sourceLoadedFromCache() {
    if ($this->primaryDataFlow && $this->aclWhereClause) {
      $this->primaryDataFlow->removeWhereClause($this->aclWhereClause);
    }
    $this->aclWhereClause = null;
    if (count($this->aclJoins)) {
      foreach($this->aclJoins as $tableAndAlias) {
        if (isset($this->additionalDataFlowDescriptions[$tableAndAlias])) {
          if ($this->dataFlow && $this->dataFlow instanceof MultipleSourceDataFlows) {
            $this->dataFlow->removeSourceDataFlow($this->additionalDataFlowDescriptions[$tableAndAlias]);
          }
          unset($this->additionalDataFlowDescriptions[$tableAndAlias]);
        }
      }
      $this->aclJoins = [];
    }
  }

}
