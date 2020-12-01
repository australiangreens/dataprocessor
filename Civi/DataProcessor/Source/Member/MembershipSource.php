<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

namespace Civi\DataProcessor\Source\Member;

use Civi\DataProcessor\Source\AbstractCivicrmEntitySource;

use CRM_Dataprocessor_ExtensionUtil as E;

class MembershipSource extends AbstractCivicrmEntitySource {

  /**
   * Returns the entity name
   *
   * @return String
   */
  protected function getEntity() {
    return 'Membership';
  }

  /**
   * Returns the table name of this entity
   *
   * @return String
   */
  protected function getTable() {
    return 'civicrm_membership';
  }

  /**
   * Returns the default configuration for this data source
   *
   * @return array
   */
  public function getDefaultConfiguration() {
    return array(
      'filter' => array(
        'is_test' => array (
          'op' => '=',
          'value' => '0',
        )
      )
    );
  }

  /**
   * Returns an array with possible aggregate functions.
   * Return false when aggregation is not possible.
   *
   * @return array|false
   */
  protected function getPossibleAggregateFunctions() {
    return [
      'max_start_date' => E::ts('Most recent by start date'),
      'min_start_date' => E::ts('Least recent by start date'),
      'max_end_date' => E::ts('Most recent by start date'),
      'min_end_date' => E::ts('Least recent by start date'),
      'max_join_date' => E::ts('Most recent by start date'),
      'min_join_date' => E::ts('Least recent by start date'),
    ];
  }

}
