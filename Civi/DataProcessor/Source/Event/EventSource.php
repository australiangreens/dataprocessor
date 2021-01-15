<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

namespace Civi\DataProcessor\Source\Event;

use Civi\DataProcessor\DataFlow\MultipleDataFlows\DataFlowDescription;
use Civi\DataProcessor\DataFlow\MultipleDataFlows\SimpleNonRequiredJoin;
use Civi\DataProcessor\DataFlow\SqlTableDataFlow;
use Civi\DataProcessor\DataSpecification\DataSpecification;
use Civi\DataProcessor\DataSpecification\FieldSpecification;
use Civi\DataProcessor\DataSpecification\Utils;
use Civi\DataProcessor\Source\AbstractCivicrmEntitySource;

use Civi\DataProcessor\Utils\AlterExportInterface;
use CRM_Dataprocessor_ExtensionUtil as E;

class EventSource extends AbstractCivicrmEntitySource implements AlterExportInterface {

  /**
   * @var SqlTableDataFlow
   */
  protected $locBlockDataFlow;

  /**
   * Returns the entity name
   *
   * @return String
   */
  protected function getEntity() {
    return 'Event';
  }

  /**
   * Returns the table name of this entity
   *
   * @return String
   */
  protected function getTable() {
    return 'civicrm_event';
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
    if (isset($data['configuration']) && is_array($data['configuration'])) {
      $configuration = $data['configuration'];

      if (isset($configuration['filter']['event_type_id'])) {
        $event_types = [];
        foreach ($configuration['filter']['event_type_id']['value'] as $event_type_id) {
          try {
            $event_types[] = civicrm_api3('OptionValue', 'getvalue', [
              'option_group_id' => 'event_type',
              'value' => $event_type_id,
              'return' => 'name'
            ]);
          } catch (\CiviCRM_API3_Exception $ex) {
            $event_types[] = $event_type_id;
          }
        }
        $configuration['filter']['event_type_id']['value'] = $event_types;
      }

      $data['configuration'] = $configuration;
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
    if (isset($data['configuration']) && is_array($data['configuration'])) {
      $configuration = $data['configuration'];
      if (isset($configuration['filter']['event_type_id'])) {
        $event_types = [];
        foreach ($configuration['filter']['event_type_id']['value'] as $event_type_id) {
          try {
            $event_types[] = civicrm_api3('OptionValue', 'getvalue', [
              'option_group_id' => 'event_type',
              'name' => $event_type_id,
              'return' => 'value'
            ]);
          } catch (\CiviCRM_API3_Exception $ex) {
            $event_types[] = $event_type_id;
          }
        }
        $configuration['filter']['event_type_id']['value'] = $event_types;
      }
      $data['configuration'] = $configuration;
    }
    return $data;
  }

  /**
   * Load the fields from this entity.
   *
   * @param DataSpecification $dataSpecification
   * @throws \Civi\DataProcessor\DataSpecification\FieldExistsException
   */
  protected function loadFields(DataSpecification $dataSpecification, $fieldsToSkip=array()) {
    parent::loadFields($dataSpecification, $fieldsToSkip);

    // Add the location block fields.
    $daoClass = \CRM_Core_DAO_AllCoreTables::getFullName('LocBlock');
    $aliasPrefix = $this->getSourceName().'_locblock_';
    Utils::addDAOFieldsToDataSpecification($daoClass, $dataSpecification, array('id'), '', $aliasPrefix);
  }

  /**
   * Ensure that the entity table is added the to the data flow.
   *
   * @return \Civi\DataProcessor\DataFlow\AbstractDataFlow
   * @throws \Exception
   */
  protected function ensureEntity() {
    $dataFlow = parent::ensureEntity();
    $locBlockDataFlowFound = false;
    foreach($this->additionalDataFlowDescriptions as $descriptor) {
      if ($descriptor->getDataFlow()->getName() == $this->getSourceName() . '_locblock') {
        $locBlockDataFlowFound = true;
      }
    }
    if (!$locBlockDataFlowFound) {
      $this->locBlockDataFlow = new SqlTableDataFlow('civicrm_loc_block', $this->getSourceName() . '_locblock');
      $join = new SimpleNonRequiredJoin($this->primaryDataFlow->getName(), 'loc_block_id', $this->getSourceName() . '_locblock', 'id');
      $join->setDataProcessor($this->dataProcessor);
      $this->additionalDataFlowDescriptions[] = new DataFlowDescription($this->locBlockDataFlow, $join);
    }
    return $dataFlow;
  }

  /**
   * Ensure that filter field is accesible in the query
   *
   * @param FieldSpecification $field
   * @return \Civi\DataProcessor\DataFlow\AbstractDataFlow|null
   * @throws \Exception
   */
  public function ensureField(FieldSpecification $field) {
    if ($this->getAvailableFilterFields()->doesAliasExists($field->alias) || $this->getAvailableFilterFields()->doesFieldExist($field->name)) {
      $spec = $this->getAvailableFilterFields()->getFieldSpecificationByAlias($field->alias);
      if (!$spec) {
        $spec = $this->getAvailableFilterFields()->getFieldSpecificationByName($field->name);
      }
      if (stripos($spec->alias, $this->getSourceName().'_locblock_') === 0) {
        $this->ensureEntity();
        return $this->locBlockDataFlow;
      }
    }
    return parent::ensureField($field);
  }

  /**
   * Ensures a field is in the data source
   *
   * @param \Civi\DataProcessor\DataSpecification\FieldSpecification $fieldSpecification
   * @return SourceInterface
   * @throws \Exception
   */
  public function ensureFieldInSource(FieldSpecification $fieldSpecification) {
    if (stripos($fieldSpecification->alias, $this->getSourceName().'_locblock_') === 0) {
      $this->ensureEntity();
      $this->locBlockDataFlow->getDataSpecification()->addFieldSpecification($fieldSpecification);
      return $this;
    } else {
      return parent::ensureFieldInSource($fieldSpecification);
    }
  }

}
