<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

namespace Civi\DataProcessor\Source\Tag;

use Civi\DataProcessor\Source\AbstractCivicrmEntitySource;
use CRM_Dataprocessor_ExtensionUtil as E;

class EntityTagSource extends AbstractCivicrmEntitySource {

  /**
   * Returns the entity name
   *
   * @return String
   */
  protected function getEntity() {
    return 'EntityTag';
  }

  /**
   * Returns the table name of this entity
   *
   * @return String
   */
  protected function getTable() {
    return 'civicrm_entity_tag';
  }


}
