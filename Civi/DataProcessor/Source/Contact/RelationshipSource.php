<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

namespace Civi\DataProcessor\Source\Contact;

use Civi\DataProcessor\Source\AbstractCivicrmEntitySource;
use Civi\DataProcessor\DataFlow\SqlDataFlow\SimpleWhereClause;
use Civi\DataProcessor\DataSpecification\CustomFieldSpecification;
use Civi\DataProcessor\DataSpecification\DataSpecification;
use Civi\DataProcessor\DataSpecification\FieldSpecification;
use Civi\DataProcessor\Utils\AlterExportInterface;
use CRM_Dataprocessor_ExtensionUtil as E;

class RelationshipSource extends AbstractCivicrmEntitySource implements AlterExportInterface {

  /**
   * Returns the entity name
   *
   * @return String
   */
  protected function getEntity() {
    return 'Relationship';
  }

  /**
   * Returns the table name of this entity
   *
   * @return String
   */
  protected function getTable() {
    return 'civicrm_relationship';
  }

  /**
   * @return \Civi\DataProcessor\DataSpecification\DataSpecification
   * @throws \Exception
   */
  public function getAvailableFilterFields() {
    if (!$this->availableFilterFields) {
      $this->availableFilterFields = new DataSpecification();

      $alias = $this->getSourceName(). '_relationship_type_id';
      $options = array();
      $relationship_types = civicrm_api3('RelationshipType', 'get', array('options' => array('limit' => 0)));
      foreach($relationship_types['values'] as $rel_type) {
        $options[$rel_type['id']] = $rel_type['label_a_b'];
      }
      $fieldSpec = new FieldSpecification('relationship_type_id', 'Integer', E::ts('Relationship type'), $options, $alias);
      $this->availableFilterFields->addFieldSpecification($fieldSpec->name, $fieldSpec);

      $this->loadFields($this->availableFilterFields, array('relationship_type_id'));
      $this->loadCustomGroupsAndFields($this->availableFilterFields, true);
    }
    return $this->availableFilterFields;
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
    if ($this->getAvailableFilterFields()->doesFieldExist($filter_field_alias)) {
      $spec = $this->getAvailableFilterFields()->getFieldSpecificationByName($filter_field_alias);
      if ($spec instanceof CustomFieldSpecification) {
        $customGroupDataFlow = $this->ensureCustomGroup($spec->customGroupTableName, $spec->customGroupName);
        $customGroupTableAlias = $customGroupDataFlow->getTableAlias();
        $customGroupDataFlow->addWhereClause(
          new SimpleWhereClause($customGroupTableAlias, $spec->customFieldColumnName, $op, $values, $spec->type, TRUE)
        );
      } else {
        if ($filter_field_alias == 'relationship_type_id') {
          $relationship_types = civicrm_api3('RelationshipType', 'get', array('options' => array('limit' => 0)));
          $selectedRelationShipTypeIds = array();
          foreach($relationship_types['values'] as $rel_type) {
            if (in_array($rel_type['name_a_b'], $values) || in_array($rel_type['id'], $values)) {
              $selectedRelationShipTypeIds[] = $rel_type['id'];
            }
          }
          $values = $selectedRelationShipTypeIds;
        }
        $entityDataFlow = $this->ensureEntity();
        $entityDataFlow->addWhereClause(new SimpleWhereClause($this->getSourceName(), $spec->name,$op, $values, $spec->type, TRUE));
      }
    }
  }

  /**
   * Function to alter the export data.
   * E.g. use this to convert ids to names
   *
   * @param array $data
   *
   * @return array
   */
  public function alterExportData($data) {
    if (isset($data['configuration']['filter']['relationship_type_id']['value'])) {
      $relationship_types = civicrm_api3('RelationshipType', 'get', ['options' => ['limit' => 0]]);
      $selectedRelationShipTypeIds = [];
      foreach ($relationship_types['values'] as $rel_type) {
        if (in_array($rel_type['name_a_b'], $data['configuration']['filter']['relationship_type_id']['value'])
          || in_array($rel_type['id'], $data['configuration']['filter']['relationship_type_id']['value'])) {
          $selectedRelationShipTypeIds[] = $rel_type['name_a_b'];
        }
      }
      $data['configuration']['filter']['relationship_type_id']['value'] = $selectedRelationShipTypeIds;
    }
    return $data;
  }

  /**
   * Function to alter the export data.
   * E.g. use this to convert names to ids
   *
   * @param array $data
   *
   * @return array
   */
  public function alterImportData($data) {
    if (isset($data['configuration']['filter']['relationship_type_id']['value'])) {
      $relationship_types = civicrm_api3('RelationshipType', 'get', ['options' => ['limit' => 0]]);
      $selectedRelationShipTypeIds = [];
      foreach ($relationship_types['values'] as $rel_type) {
        if (in_array($rel_type['name_a_b'], $data['configuration']['filter']['relationship_type_id']['value'])
          || in_array($rel_type['id'], $data['configuration']['filter']['relationship_type_id']['value'])) {
          $selectedRelationShipTypeIds[] = $rel_type['id'];
        }
      }
      $data['configuration']['filter']['relationship_type_id']['value'] = $selectedRelationShipTypeIds;
    }
    return $data;
  }

  /**
   * When this source has additional configuration you can add
   * the fields on the form with this function.
   *
   * @param \CRM_Core_Form $form
   * @param array $source
   */
  public function buildConfigurationForm(\CRM_Core_Form $form, $source=array()) {
    if (isset($source['configuration'])) {
      $source = $this->alterImportData($source);
    }
    parent::buildConfigurationForm($form, $source);
  }


}
