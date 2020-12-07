<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

namespace Civi\DataProcessor\FilterHandler;

use Civi\DataProcessor\DataFlow\SqlDataFlow;
use Civi\DataProcessor\Exception\InvalidConfigurationException;
use CRM_Dataprocessor_ExtensionUtil as E;

class ContactTypeFilter extends AbstractFieldFilterHandler {

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
    $contactTypeIds = $filter['value'];
    if (!is_array($contactTypeIds)) {
      $contactTypeIds = explode(",", $contactTypeIds);
    }
    $contactTableAlias = 'civicrm_contact_'.$this->inputFieldSpecification->alias;
    $contactTypeApiParams['id']['IN'] = $contactTypeIds;
    $contactTypeApiParams['options']['limit'] = 0;
    $contactTypeApi = civicrm_api3('ContactType', 'get', $contactTypeApiParams);
    $contactTypeClauses = array();
    foreach($contactTypeApi['values'] as $contactTypeApiResult) {
      if (empty($contactTypeApiResult['parent_id'])) {
        $contactTypeClauses[] = new SqlDataFlow\SimpleWhereClause($contactTableAlias, 'contact_type', '=', $contactTypeApiResult['name']);
      } else {
        $contactTypeSearchName = '%'.\CRM_Core_DAO::VALUE_SEPARATOR.$contactTypeApiResult['name'].\CRM_Core_DAO::VALUE_SEPARATOR.'%';
        $contactTypeClauses[] = new SqlDataFlow\SimpleWhereClause($contactTableAlias, 'contact_sub_type', 'LIKE', $contactTypeSearchName);
      }
    }
    if (count($contactTypeClauses)) {
      $contactTypeClause = new SqlDataFlow\OrClause($contactTypeClauses);
      if ($dataFlow && $dataFlow instanceof SqlDataFlow) {
        $tableAlias = $this->getTableAlias($dataFlow);
        $this->whereClause = new SqlDataFlow\InTableWhereClause(
          'id',
          'civicrm_contact',
          $contactTableAlias,
          array($contactTypeClause),
          $tableAlias,
          $this->inputFieldSpecification->getName(),
          $filter['op']
        );

        $dataFlow->addWhereClause($this->whereClause);
      }
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
    $form->add('select', 'contact_id_field', E::ts('Contact ID Field'), $fieldSelect, true, array(
      'style' => 'min-width:250px',
      'class' => 'crm-select2 huge data-processor-field-for-name',
      'placeholder' => E::ts('- select -'),
    ));

    if (isset($filter['configuration'])) {
      $configuration = $filter['configuration'];
      $defaults = array();
      if (isset($configuration['field']) && isset($configuration['datasource'])) {
        $defaults['contact_id_field'] = $configuration['datasource'] . '::' . $configuration['field'];
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
    return "CRM/Dataprocessor/Form/Filter/Configuration/ContactTypeFilter.tpl";
  }


  /**
   * Process the submitted values and create a configuration array
   *
   * @param $submittedValues
   * @return array
   */
  public function processConfiguration($submittedValues) {
    list($datasource, $field) = explode('::', $submittedValues['contact_id_field'], 2);
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

    $api_params['is_active'] = 1;
    $form->add('select', "{$fieldSpec->alias}_op", E::ts('Operator:'), $operations, true, [
      'style' => $minWidth,
      'class' => 'crm-select2 '.$sizeClass,
      'multiple' => FALSE,
      'placeholder' => E::ts('- select -'),
    ]);
    $form->addEntityRef( "{$fieldSpec->alias}_value", NULL, array(
      'placeholder' => E::ts('Select a contact type'),
      'entity' => 'ContactType',
      'api' => array('params' => $api_params),
      'create' => false,
      'multiple' => true,
      'select' => ['minimumInputLength' => 0],
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
    );
  }


}
