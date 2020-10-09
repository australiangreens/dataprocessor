<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

namespace Civi\DataProcessor\FieldOutputHandler;

use CRM_Dataprocessor_ExtensionUtil as E;
use Civi\DataProcessor\Source\SourceInterface;
use Civi\DataProcessor\DataSpecification\FieldSpecification;
use Civi\DataProcessor\Exception\DataSourceNotFoundException;
use Civi\DataProcessor\Exception\FieldNotFoundException;

class EventRepeatingInfoFieldOutputHandler extends AbstractSimpleFieldOutputHandler {

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
    $event_id = $rawRecord[$this->inputFieldSpec->alias];
    $output = new FieldOutput();
    if ($event_id) {
      $repeat = \CRM_Core_BAO_RecurringEntity::getPositionAndCount($event_id, 'civicrm_event');
      if ($repeat) {
        $output->rawValue = $repeat;
        $output->formattedValue = E::ts('Repeating (%1 of %2)', array(1 => $repeat[0], 2 => $repeat[1]));
      }
    }
    return $output;
  }


}
