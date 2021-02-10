<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

namespace Civi\DataProcessor\FieldOutputHandler;

use CRM_Dataprocessor_ExtensionUtil as E;

class CleanFileNameFieldOutputHandler extends AbstractSimpleFieldOutputHandler {

  /**
   * Returns the formatted value
   *
   * @param $rawRecord
   * @param $formattedRecord
   *
   * @return \Civi\DataProcessor\FieldOutputHandler\FieldOutput
   */
  public function formatField($rawRecord, $formattedRecord) {
    $value = $rawRecord[$this->inputFieldSpec->alias];
    $value = \CRM_Utils_File::cleanFileName(basename($value));
    return new FieldOutput($value);
  }

  /**
   * Returns the label of the field for selecting a field.
   *
   * This could be override in a child class.
   *
   * @return string
   */
  protected function getFieldTitle() {
    return E::ts('Filename/Path/Uri Field');
  }

}
