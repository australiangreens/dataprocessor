<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

namespace Civi\DataProcessor\FieldOutputHandler;

use CRM_Dataprocessor_ExtensionUtil as E;

class FormattedAddressFieldOutputHandler extends AbstractSimpleSortableFieldOutputHandler {

  /**
   * Returns the label of the field for selecting a field.
   *
   * This could be override in a child class.
   *
   * @return string
   */
  protected function getFieldTitle() {
    return E::ts('Address ID Field');
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
    $addressId = $rawRecord[$this->inputFieldSpec->alias];
    $formattedAddress = "";
    try {
      $address = civicrm_api3('Address', 'getsingle', ['id' => $addressId]);
      $formattedAddress = \CRM_Utils_Address::format($address);
    } catch (\Exception $e) {
      // Do nothing
    }

    $formattedValue = new HTMLFieldOutput($addressId);
    $formattedValue->formattedValue = strip_tags($formattedAddress);
    $formattedValue->setHtmlOutput($formattedAddress);
    return $formattedValue;
  }


}
