<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

namespace Civi\DataProcessor\Source\Contact;

use Civi\DataProcessor\DataFlow\SqlDataFlow\SimpleWhereClause;
use Civi\DataProcessor\DataFlow\SqlTableDataFlow;
use Civi\DataProcessor\DataSpecification\DataSpecification;
use Civi\DataProcessor\DataSpecification\FieldExistsException;
use Civi\DataProcessor\DataSpecification\FieldSpecification;
use Civi\DataProcessor\Source\AbstractSource;
use Civi\DataProcessor\DataSpecification\CustomFieldSpecification;

use CRM_Dataprocessor_ExtensionUtil as E;


class MultipleCustomGroupSource extends AbstractSource {

  /**
   * @var string
   */
  protected $custom_group_name;

  /**
   * @var string
   */
  protected $custom_group_title;

  /**
   * @var string
   */
  protected $custom_group_table_name;

  /**
   * @var \Civi\DataProcessor\DataSpecification\DataSpecification
   */
  protected $availableFields;

  /**
   * @var \Civi\DataProcessor\DataSpecification\DataSpecification
   */
  protected $availableFilterFields;

  public function __construct($custom_group_name, $custom_group_title, $custom_group_table_name) {
    parent::__construct();
    $this->custom_group_name = $custom_group_name;
    $this->custom_group_title = $custom_group_title;
    $this->custom_group_table_name = $custom_group_table_name;
  }

  /**
   * Initialize the join
   *
   * @return void
   */
  public function initialize() {
    if (!$this->dataFlow) {
      $this->dataFlow = new SqlTableDataFlow($this->custom_group_table_name, $this->getSourceName());
      $this->addFilters($this->configuration);
    }
  }

  /**
   * @return \Civi\DataProcessor\DataSpecification\DataSpecification
   * @throws \Exception
   */
  public function getAvailableFields() {
    if (!$this->availableFields) {
      $this->availableFields = new DataSpecification();
      $this->availableFields->addFieldSpecification('entity_id', new FieldSpecification('entity_id','Integer', E::ts('Contact ID'), null, $this->getSourceName().'_entity_id'));
      $this->loadCustomGroupsAndFields($this->availableFields, false);
    }
    return $this->availableFields;
  }

  /**
   * @return \Civi\DataProcessor\DataSpecification\DataSpecification
   * @throws \Exception
   */
  public function getAvailableFilterFields() {
    if (!$this->availableFilterFields) {
      $this->availableFilterFields = new DataSpecification();
      $this->availableFilterFields->addFieldSpecification('entity_id', new FieldSpecification('entity_id','Integer', E::ts('Contact ID'), null, $this->getSourceName().'_entity_id'));
      $this->loadCustomGroupsAndFields($this->availableFilterFields, true);
    }
    return $this->availableFilterFields;
  }

  /**
   * Add custom fields to the available fields section
   *
   * @param DataSpecification $dataSpecification
   * @param bool $onlySearchAbleFields
   * @throws \Civi\DataProcessor\DataSpecification\FieldExistsException
   * @throws \Exception
   */
  protected function loadCustomGroupsAndFields(DataSpecification $dataSpecification, $onlySearchAbleFields) {
    $params['options']['limit'] = 0;
    $params['custom_group_id'] = $this->custom_group_name;
    $params['is_active'] = 1;
    if ($onlySearchAbleFields) {
      $params['is_searchable'] = 1;
    }
    $customFields = civicrm_api3('CustomField', 'get', $params);
    foreach ($customFields['values'] as $field) {
      $alias = $this->getSourceName() . '_' . $field['name'];
      $customFieldSpec = new CustomFieldSpecification(
        $this->custom_group_name, $this->custom_group_table_name, $this->custom_group_title,
        $field,
        $alias
      );
      $dataSpecification->addFieldSpecification($customFieldSpec->name, $customFieldSpec);
    }
  }

  /**
   * Ensures a field is in the data source
   *
   * @param \Civi\DataProcessor\DataSpecification\FieldSpecification $fieldSpecification
   * @return \Civi\DataProcessor\Source\SourceInterface
   * @throws \Exception
   */
  public function ensureFieldInSource(FieldSpecification $fieldSpecification) {
    try {
      $this->dataFlow->getDataSpecification()->addFieldSpecification($fieldSpecification->alias, $fieldSpecification);
    } catch (FieldExistsException $e) {
      // Do nothing.
    }
    return $this;
  }

  /**
   * Add the filters to the where clause of the data flow
   *
   * @param $configuration
   * @throws \Exception
   */
  protected function addFilters($configuration) {
    if (isset($configuration['filter']) && is_array($configuration['filter'])) {
      foreach($configuration['filter'] as $filter_alias => $filter_field) {
        $this->addFilter($filter_alias, $filter_field['op'], $filter_field['value']);
      }
    }
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
    $spec = null;
    if ($this->getAvailableFilterFields()->doesAliasExists($filter_field_alias)) {
      $spec = $this->getAvailableFilterFields()->getFieldSpecificationByAlias($filter_field_alias);
    } elseif ($this->getAvailableFilterFields()->doesFieldExist($filter_field_alias)) {
      $spec = $this->getAvailableFilterFields()->getFieldSpecificationByName($filter_field_alias);
    }

    if ($spec) {
      $this->dataFlow->addWhereClause(new SimpleWhereClause($this->getSourceName(), $spec->getName(),$op, $values, $spec->type, TRUE));
    }
  }



}
