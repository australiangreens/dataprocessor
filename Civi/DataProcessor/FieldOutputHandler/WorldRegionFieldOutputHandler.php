<?php
/**
 * @author Klaas Eikelboom <klaas.eikelboom@civicoop.org
 * @license AGPL-3.0
 */

namespace Civi\DataProcessor\FieldOutputHandler;

use CRM_Dataprocessor_ExtensionUtil as E;

class WorldRegionFieldOutputHandler extends AbstractSimpleFieldOutputHandler {

  /**
   * Returns the label of the field for selecting a field.
   *
   * This could be override in a child class.
   *
   * @return string
   */
  protected function getFieldTitle() {
    return E::ts('Country Field');
  }

  /**
   * Returns the data type of this field
   *
   * @return String
   */
  protected function getType() {
    return 'String';
  }

  /**
   * Returns the formatted value
   *
   * @param $rawRecord
   * @param $formattedRecord
   *
   * @return \Civi\DataProcessor\FieldOutputHandler\FieldOutput
   */
  public function formatField($rawRecord, $formattedRecord) {
    $sql = 'SELECT wr.name FROM civicrm_worldregion wr
            JOIN   civicrm_country c on (c.region_id = wr.id)
            WHERE  c.id = %1';
    $countryId = $rawRecord[$this->inputFieldSpec->alias];
    if (isset($countryId)) {
      $regionName = \CRM_Core_DAO::singleValueQuery($sql, [
        1 => [
          $countryId,
          'Integer',
        ],
      ]);
    }
    else {
      $regionName = "";
    }
    $formattedValue = new HTMLFieldOutput($regionName);
    $formattedValue->setHtmlOutput($regionName);
    return $formattedValue;
  }
}
