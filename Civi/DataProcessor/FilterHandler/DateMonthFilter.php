<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

namespace Civi\DataProcessor\FilterHandler;

use Civi\DataProcessor\DataFlow\InMemoryDataFlow;
use Civi\DataProcessor\DataFlow\SqlDataFlow;
use Civi\DataProcessor\DataSpecification\FieldSpecification;
use Civi\DataProcessor\Exception\InvalidConfigurationException;
use CRM_Dataprocessor_ExtensionUtil as E;

class DateMonthFilter extends AbstractFieldFilterHandler {

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
    $this->fieldSpecification->type = 'Int';
    $this->fieldSpecification->options = [
      '1' => E::ts('January'),
      '2' => E::ts('February'),
      '3' => E::ts('March'),
      '4' => E::ts('April'),
      '5' => E::ts('May'),
      '6' => E::ts('June'),
      '7' => E::ts('July'),
      '8' => E::ts('August'),
      '9' => E::ts('September'),
      '10' => E::ts('October'),
      '11' => E::ts('November'),
      '12' => E::ts('December'),
    ];
  }

  /**
   * @param array $filter
   *   The filter settings
   * @return mixed
   * @throws \Exception
   */
  public function setFilter($filter) {
    $this->resetFilter();
    $dataFlow  = $this->dataSource->ensureField($this->inputFieldSpecification);
    if ($dataFlow && $dataFlow instanceof SqlDataFlow) {
      $tableAlias = $this->getTableAlias($dataFlow);
      $value = $filter['value'];
      if (!is_array($value)) {
        $value = explode(",", $value);
      }
      $this->whereClause = new SqlDataFlow\SimpleWhereClause($tableAlias, $this->inputFieldSpecification->getName(), $filter['op'], $value, 'Int', FALSE, 'MONTH(%s)');
      $dataFlow->addWhereClause($this->whereClause);
    } elseif ($dataFlow && $dataFlow instanceof InMemoryDataFlow) {
      $this->filterClass = new InMemoryDataFlow\SimpleFilter($this->inputFieldSpecification->getName(), $filter['op'], $filter['value'], function($value) {
        if ($value) {
          return date('n', strtotime($value));
        }
        return $value;
      });
      $dataFlow->addFilter($this->filterClass);
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
    $fieldSelect = \CRM_Dataprocessor_Utils_DataSourceFields::getAvailableFilterFieldsInDataSources($filter['data_processor_id'], [self::class, 'filterDateFields']);

    $form->add('select', 'field', E::ts('Field'), $fieldSelect, true, array(
      'style' => 'min-width:250px',
      'class' => 'crm-select2 huge data-processor-field-for-name',
      'placeholder' => E::ts('- select -'),
    ));
    if (isset($filter['configuration'])) {
      $configuration = $filter['configuration'];
      if (isset($configuration['field']) && isset($configuration['datasource'])) {
        $defaults['field'] = $configuration['datasource'] . '::' . $configuration['field'];
        $form->setDefaults($defaults);
      }
    }
  }

  /**
   * When this filter type has configuration specify the template file name
   * for the configuration form.
   *
   * @return false|string
   */
  public function getConfigurationTemplateFileName() {
    return "CRM/Dataprocessor/Form/Filter/Configuration/DateMonthFilter.tpl";
  }


  /**
   * Process the submitted values and create a configuration array
   *
   * @param $submittedValues
   * @return array
   */
  public function processConfiguration($submittedValues) {
    list($datasource, $field) = explode('::', $submittedValues['field'], 2);
    $configuration['field'] = $field;
    $configuration['datasource'] = $datasource;
    return $configuration;
  }

  protected function getOperatorOptions(FieldSpecification $fieldSpec) {
    return array(
      'IN' => E::ts('Is one of'),
      'NOT IN' => E::ts('Is not one of'),
    );
  }

  /**
   * Filters the date fields
   *
   * @param \Civi\DataProcessor\DataSpecification\FieldSpecification $field
   * @return bool
   */
  public static function filterDateFields(FieldSpecification $field) {
    if ($field->type == 'Date' || $field->type == 'Timestamp') {
      return true;
    }
    return false;
  }

}
