<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */
namespace Civi\DataProcessor\DataFlow\InMemoryDataFlow;

interface FilterInterface {

  /**
   * Returns true when the record is in the filter.
   * Returns false when the reocrd is not in the filter.
   *
   * @param $record
   *
   * @return bool
   */
  public function filterRecord($record);

}
