<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

namespace Civi\DataProcessor\DataFlow\InMemoryDataFlow;

class CheksumFilter implements FilterInterface {

  protected $checksum;

  protected $contact_id_field;

  protected $hash_field;

  public function __construct($contact_id_field, $hash_field, $checksum) {
    $this->contact_id_field = $contact_id_field;
    $this->hash_field = $hash_field;
    $this->checksum = $checksum;
  }

  /**
   * Returns true when the record is in the filter.
   * Returns false when the reocrd is not in the filter.
   *
   * @param $record
   *
   * @return bool
   */
  public function filterRecord($record) {
    if (!isset($record[$this->contact_id_field])) {
      return false;
    }
    if (!isset($record[$this->hash_field])) {
      return false;
    }

    list($cs, $ts, $lf) = explode('_', $this->checksum, 3);
    $hash = $record[$this->hash_field];
    $cid = $record[$this->contact_id_field];

    $_cs = md5("{$hash}_{$cid}_{$ts}_{$lf}");
    if ($_cs != $cs) {
      return false;
    }

    $now = time();
    return ($ts + ($lf * 60 * 60) >= $now);
  }

  public function getField() {
    return $this->field;
  }

  public function getOperator() {
    return $this->operator;
  }

  public function getValue() {
    return $this->value;
  }


}
