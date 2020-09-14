<?php
/**
 * @author Klaas Eikelboom <klaas.eikelboom@civicoop.org>
 * @license AGPL-3.0
 */

namespace Civi\DataProcessor\FilterHandler;

use Civi\DataProcessor\DataFlow\SqlDataFlow;
use Civi\DataProcessor\Exception\InvalidConfigurationException;
use CRM_Dataprocessor_ExtensionUtil as E;

class WorldRegionFilter extends AbstractFieldFilterHandler {

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
  }

  /**
   * @param array $filter
   *   The filter settings
   * @return mixed
   */
  public function setFilter($filter) {
    $this->resetFilter();
    $dataFlow  = $this->dataSource->ensureField($this->inputFieldSpecification);
    $region_ids = $filter['value'];
    if (!is_array($region_ids)) {
      $region_ids = explode(",", $region_ids);
      if (!is_array($region_ids)) {
        $region_ids = [$region_ids];
      }
    }
    $countryTableAlias = 'civicrm_country_'.$this->inputFieldSpecification->alias;
    $countryFilters = array(
      new SqlDataFlow\SimpleWhereClause($countryTableAlias, 'region_id', 'IN', $region_ids),
    );
    if ($dataFlow && $dataFlow instanceof SqlDataFlow) {
      $this->whereClause = new SqlDataFlow\InTableWhereClause(
        'id',
        'civicrm_country',
        $countryTableAlias,
        $countryFilters,
        $dataFlow->getName(),
        $this->inputFieldSpecification->getName(),
        $filter['op']
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

    $form->add('select', 'country_id_field', E::ts('Country Field'), $fieldSelect, true, array(
      'style' => 'min-width:250px',
      'class' => 'crm-select2 huge data-processor-field-for-name',
      'placeholder' => E::ts('- select -'),
    ));

    if (isset($filter['configuration'])) {
      $configuration = $filter['configuration'];
      $defaults = array();
      if (isset($configuration['field']) && isset($configuration['datasource'])) {
        $defaults['country_id_field'] = $configuration['datasource'] . '::' . $configuration['field'];
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
    return "CRM/Dataprocessor/Form/Filter/Configuration/WorldRegionFilter.tpl";
  }

  /**
   * Process the submitted values and create a configuration array
   *
   * @param $submittedValues
   * @return array
   */
  public function processConfiguration($submittedValues) {
    list($datasource, $field) = explode('::', $submittedValues['country_id_field'], 2);
    $configuration['field'] = $field;
    $configuration['datasource'] = $datasource;
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
      'multiple' => false,
      'placeholder' => E::ts('- select -'),
    ]);
    $form->add('select', "{$fieldSpec->alias}_value",  E::ts('World Regions:'), $this->worldRegions(), false , [
      'style' => $minWidth,
      'class' => 'crm-select2 '.$sizeClass,
      'multiple' => true,
      'placeholder' => E::ts('- select -'),
    ]);

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

  protected function worldRegions(){
    $regions = [];
    $dao = \CRM_Core_DAO::executeQuery('select id, name from civicrm_worldregion order by name');
    while($dao->fetch()){
      $regions[$dao->id] = $dao->name;
    }
    return $regions;
  }

  protected function getOperatorOptions(\Civi\DataProcessor\DataSpecification\FieldSpecification $fieldSpec) {
    return array(
      'IN' => E::ts('Address is inside'),
      'NOT IN' => E::ts('Address is outside'),
    );
  }


}
