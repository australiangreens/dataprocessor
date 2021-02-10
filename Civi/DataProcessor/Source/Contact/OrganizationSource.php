<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

namespace Civi\DataProcessor\Source\Contact;

use Civi\DataProcessor\DataFlow\SqlDataFlow\AndClause;
use Civi\DataProcessor\DataFlow\SqlDataFlow\OrClause;
use Civi\DataProcessor\DataFlow\SqlDataFlow\SimpleWhereClause;
use Civi\DataProcessor\DataSpecification\DataSpecification;
use Civi\DataProcessor\Source\AbstractCivicrmEntitySource;

use CRM_Dataprocessor_ExtensionUtil as E;

class OrganizationSource extends AbstractCivicrmEntitySource {

  protected $skipFields = array(
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

  protected $skipFilterFields = array(
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
   * Returns the default configuration for this data source
   *
   * @return array
   */
  public function getDefaultConfiguration() {
    return array(
      'filter' => array(
        'is_deleted' => array (
          'op' => '=',
          'value' => '0',
        )
      )
    );
  }

  /**
   * @return \Civi\DataProcessor\DataSpecification\DataSpecification
   * @throws \Exception
   */
  public function getAvailableFilterFields() {
    if (!$this->availableFilterFields) {
      $this->availableFilterFields = new DataSpecification();
      $this->loadFields($this->availableFilterFields, $this->skipFilterFields);
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

  /**
   * Adds an inidvidual filter to the data source
   *
   * @param $filter_field_alias
   * @param $op
   * @param $values
   *
   * @throws \Exception
   */
  protected function addFilter($filter_field_alias, $op, $values) {
    if ($filter_field_alias == 'contact_sub_type' && $op == 'IN') {
      $contactTypeClauses = [];
      foreach ($values as $value) {
        $contactTypeSearchName = '%' . \CRM_Core_DAO::VALUE_SEPARATOR . $value . \CRM_Core_DAO::VALUE_SEPARATOR . '%';
        $contactTypeClauses[] = new SimpleWhereClause($this->getSourceName(), 'contact_sub_type', 'LIKE', $contactTypeSearchName, 'String', TRUE);
      }
      if (count($contactTypeClauses)) {
        $contactTypeClause = new OrClause($contactTypeClauses, TRUE);
        $entityDataFlow = $this->ensureEntity();
        $entityDataFlow->addWhereClause($contactTypeClause);
      }
    } elseif ($filter_field_alias == 'contact_sub_type' && $op == 'NOT IN') {
      $contactTypeClauses = [];
      foreach($values as $value) {
        $contactTypeSearchName = '%'.\CRM_Core_DAO::VALUE_SEPARATOR.$value.\CRM_Core_DAO::VALUE_SEPARATOR.'%';
        $contactTypeClauses[] = new SimpleWhereClause($this->getSourceName(), 'contact_sub_type', 'NOT LIKE', $contactTypeSearchName, 'String',TRUE);
      }
      if (count($contactTypeClauses)) {
        $contactTypeClause = new AndClause($contactTypeClauses, TRUE);
        $entityDataFlow = $this->ensureEntity();
        $entityDataFlow->addWhereClause($contactTypeClause);
      }
    } else {
      parent::addFilter($filter_field_alias, $op, $values);
    }
  }


}
