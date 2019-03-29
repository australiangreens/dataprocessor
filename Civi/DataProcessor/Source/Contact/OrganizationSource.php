<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

namespace Civi\DataProcessor\Source\Contact;

use Civi\DataProcessor\DataSpecification\DataSpecification;
use Civi\DataProcessor\Source\AbstractCivicrmEntitySource;

use CRM_Dataprocessor_ExtensionUtil as E;

class OrganizationSource extends AbstractCivicrmEntitySource {

  protected $skipFields = array(
    'contact_type',
    'first_name',
    'middle_name',
    'last_name',
    'formal_title',
    'job_title',
    'gender_id',
    'prefix_id',
    'suffix_id',
    'birth_date',
    'household_name',
    'is_deceased',
    'deceased_date',
  );

  /**
   * Returns the entity name
   *
   * @return String
   */
  protected function getEntity() {
    return 'Contact';
  }

  /**
   * Returns the table name of this entity
   *
   * @return String
   */
  protected function getTable() {
    return 'civicrm_contact';
  }

  /**
   * @return \Civi\DataProcessor\DataSpecification\DataSpecification
   * @throws \Exception
   */
  public function getAvailableFilterFields() {
    if (!$this->availableFilterFields) {
      $this->availableFilterFields = new DataSpecification();
      $this->loadFields($this->availableFilterFields, $this->skipFields);
      $this->loadCustomGroupsAndFields($this->availableFilterFields, true, 'Organization');
    }
    return $this->availableFilterFields;
  }

  /**
   * @return \Civi\DataProcessor\DataSpecification\DataSpecification
   * @throws \Exception
   */
  public function getAvailableFields() {
    if (!$this->availableFields) {
      $this->availableFields = new DataSpecification();
      $this->loadFields($this->availableFields, $this->skipFields);
      $this->loadCustomGroupsAndFields($this->availableFields, false, 'Organization');
    }
    return $this->availableFields;
  }

  /**
   * Add the filters to the where clause of the data flow
   *
   * @param $configuration
   * @throws \Exception
   */
  protected function addFilters($configuration) {
    parent::addFilters($configuration);
    $this->addFilter('contact_type', '=', 'Organization');
  }


}