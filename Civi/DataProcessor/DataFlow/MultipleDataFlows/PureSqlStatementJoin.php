<?php

namespace Civi\DataProcessor\DataFlow\MultipleDataFlows;

class PureSqlStatementJoin implements SqlJoinInterface {

  public function __construct($sql) {
    $this->sql = $sql;
  }

  public function getJoinClause(DataFlowDescription $sourceDataFlowDescription) {
    return $this->sql;
  }

}
