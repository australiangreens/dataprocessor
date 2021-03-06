<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

namespace Civi\DataProcessor\DataFlow\SqlDataFlow;

interface WhereClauseInterface {

  /**
   * Returns the where clause
   * E.g. contact_type = 'Individual'
   *
   * @return string
   */
  public function getWhereClause();

  /**
   * Returns true when this where clause can be added to the
   * join or whether this clause should be propagated to the where part of the query
   *
   * @return bool
   */
  public function isJoinClause();

}