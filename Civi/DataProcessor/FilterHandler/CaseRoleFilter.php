<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

namespace Civi\DataProcessor\FilterHandler;

use Civi\DataProcessor\DataFlow\SqlDataFlow;
use Civi\DataProcessor\Exception\InvalidConfigurationException;
use CRM_Dataprocessor_ExtensionUtil as E;

class CaseRoleFilter extends AbstractFieldFilterHandler {

  /**
   * @var array
   */
  protected $relationship_type_ids = array();

  public function __construct() {
    parent::__construct();
  }

  /**
   * Initialize the filter
   *
   * @throws \Civi\DataProcessor\Exception\DataSourceNotFoundException
   * @throws \Civi\DataProcessor\Exception\InvalidConfigurationException
   * @throws \Civi\DataProcessor\Exception\FieldNotFoundException
   */
  protected function doInitialization() {
    if (!isset($this->configuration['datasource']) || !isset($this->configuration['field'])) {
      throw new InvalidConfigurationException(E::ts("Filter %1 requires a field to filter on. None given.", array(1=>$this->title)));
    }
    $this->initializeField($this->configuration['datasource'], $this->configuration['field']);

    if (isset($this->configuration['relationship_types']) && is_array($this->configuration['relationship_types'])) {
      $this->relationship_type_ids = array();
      foreach($this->configuration['relationship_types'] as $rel_type) {
        try {
          $this->relationship_type_ids[] = civicrm_api3('RelationshipType', 'getvalue', [
            'return' => 'id',
            'name_a_b' => $rel_type
          ]);
        } catch (\CiviCRM_API3_Exception $e) {
          // Do nothing
        }
      };
    }
  }


  /**
   * @param array $filter
   *   The filter settings
   * @return mixed
   */
  public function setFilter($filter) {
    $this->resetFilter();

    $dataFlow  = $this->dataSource->ensureField($this->inputFieldSpecification);
    $cids = $filter['value'];
    if (!is_array($cids)) {
      $cids = array($cids);
    }
    $relationshipTableAlias = 'civicrm_relationship_'.$this->inputFieldSpecification->alias;
    $relationshipFilters = array(
      new SqlDataFlow\SimpleWhereClause($relationshipTableAlias, 'is_active', '=', '1'),
      new SqlDataFlow\SimpleWhereClause($relationshipTableAlias, 'case_id', 'IS NOT NULL', 0),
    );
    if ($filter['op'] != 'IS NULL') {
      $relationshipFilters[] = new SqlDataFlow\SimpleWhereClause($relationshipTableAlias, 'contact_id_b', 'IN', $cids);
    }
    if (count($this->relationship_type_ids)) {
      $relationshipFilters[] = new SqlDataFlow\SimpleWhereClause($relationshipTableAlias, 'relationship_type_id', 'IN', $this->relationship_type_ids, 'Integer');
    }

    $inOperator = $filter['op'];
    if ($filter['op'] == 'IS NULL') {
      $inOperator = 'NOT IN';
    }

    if ($dataFlow && $dataFlow instanceof SqlDataFlow) {
      $this->whereClause = new SqlDataFlow\InTableWhereClause(
        'case_id',
        'civicrm_relationship',
        $relationshipTableAlias,
        $relationshipFilters,
        $dataFlow->getName(),
        $this->inputFieldSpecification->name,
        $inOperator
      );

      $dataFlow->addWhereClause($this->whereClause);
    }
  }

  /**
   * Returns true when this filter has additional configuration
   *
   * @return bool
   */
  public function hasConfiguration() {
    return true;
  }

  /**
   * When this filter type has additional configuration you can add
   * the fields on the form with this function.
   *
   * @param \CRM_Core_Form $form
   * @param array $filter
   */
  public function buildConfigurationForm(\CRM_Core_Form $form, $filter=array()) {
    $fieldSelect = \CRM_Dataprocessor_Utils_DataSourceFields::getAvailableFilterFieldsInDataSources($filter['data_processor_id']);
    $relationshipTypeApi = civicrm_api3('RelationshipType', 'get', array('is_active' => 1, 'options' => array('limit' => 0)));
    $relationshipTypes = array();
    foreach($relationshipTypeApi['values'] as $relationship_type) {
      $relationshipTypes[$relationship_type['name_a_b']] = $relationship_type['label_a_b'];
    }

    $form->add('select', 'case_id_field', E::ts('Case ID Field'), $fieldSelect, true, array(
      'style' => 'min-width:250px',
      'class' => 'crm-select2 huge data-processor-field-for-name',
      'placeholder' => E::ts('- select -'),
    ));

    $form->add('select', 'relationship_types', E::ts('Restrict to roles'), $relationshipTypes, false, array(
      'style' => 'min-width:250px',
      'class' => 'crm-select2 huge',
      'placeholder' => E::ts('- Filter on all roles -'),
      'multiple' => true,
    ));

    if (isset($filter['configuration'])) {
      $configuration = $filter['configuration'];
      $defaults = array();
      if (isset($configuration['field']) && isset($configuration['datasource'])) {
        $defaults['case_id_field'] = $configuration['datasource'] . '::' . $configuration['field'];
      }
      if (isset($configuration['relationship_types'])) {
        $defaults['relationship_types'] = $configuration['relationship_types'];
      }
      $form->setDefaults($defaults);
    }
  }

  /**
   * When this filter type has configuration specify the template file name
   * for the configuration form.
   *
   * @return false|string
   */
  public function getConfigurationTemplateFileName() {
    return "CRM/Dataprocessor/Form/Filter/Configuration/CaseRoleFilter.tpl";
  }


  /**
   * Process the submitted values and create a configuration array
   *
   * @param $submittedValues
   * @return array
   */
  public function processConfiguration($submittedValues) {
    list($datasource, $field) = explode('::', $submittedValues['case_id_field'], 2);
    $configuration['field'] = $field;
    $configuration['datasource'] = $datasource;
    $configuration['relationship_types'] = isset($submittedValues['relationship_types']) ? $submittedValues['relationship_types'] : array();
    return $configuration;
  }

  /**
   * Add the elements to the filter form.
   *
   * @param \CRM_Core_Form $form
   * @param array $defaultFilterValue
   * @param string $size
   *   Possible values: full or compact
   * @return array
   *   Return variables belonging to this filter.
   */
  public function addToFilterForm(\CRM_Core_Form $form, $defaultFilterValue, $size='full') {
    $fieldSpec = $this->getFieldSpecification();
    $alias = $fieldSpec->alias;
    $operations = $this->getOperatorOptions($fieldSpec);

    $title = $fieldSpec->title;
    if ($this->isRequired()) {
      $title .= ' <span class="crm-marker">*</span>';
    }

    $sizeClass = 'huge';
    $minWidth = 'min-width: 250px;';
    if ($size =='compact') {
      $sizeClass = 'medium';
      $minWidth = '';
    }

    $form->add('select', "{$fieldSpec->alias}_op", E::ts('Operator:'), $operations, true, [
      'style' => $minWidth,
      'class' => 'crm-select2 '.$sizeClass,
      'multiple' => FALSE,
      'placeholder' => E::ts('- select -'),
    ]);
    $form->addEntityRef( "{$fieldSpec->alias}_value", NULL, array(
      'placeholder' => E::ts('Select a contact'),
      'entity' => 'Contact',
      'create' => false,
      'multiple' => true,
      'style' => $minWidth,
      'class' => $sizeClass,
    ));

    if (isset($defaultFilterValue['op'])) {
      $defaults[$alias . '_op'] = $defaultFilterValue['op'];
    } else {
      $defaults[$alias . '_op'] = key($operations);
    }
    if (isset($defaultFilterValue['value'])) {
      $defaults[$alias.'_value'] = $defaultFilterValue['value'];
    }
    if (count($defaults)) {
      $form->setDefaults($defaults);
    }

    $filter['type'] = $fieldSpec->type;
    $filter['alias'] = $fieldSpec->alias;
    $filter['title'] = $title;
    $filter['size'] = $size;

    return $filter;
  }

  protected function getOperatorOptions(\Civi\DataProcessor\DataSpecification\FieldSpecification $fieldSpec) {
    return array(
      'IN' => E::ts('Is one of'),
      'NOT IN' => E::ts('Is not one of'),
      'null' => E::ts('Is empty'),
    );
  }


}
