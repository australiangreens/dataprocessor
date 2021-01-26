<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

namespace Civi\DataProcessor\FilterHandler;

use Civi\DataProcessor\DataFlow\CombinedDataFlow\CombinedSqlDataFlow;
use Civi\DataProcessor\DataFlow\SqlDataFlow;
use Civi\DataProcessor\DataFlow\SqlTableDataFlow;
use Civi\DataProcessor\DataSpecification\FieldSpecification;
use Civi\DataProcessor\Exception\FilterRequiredException;
use Civi\DataProcessor\ProcessorType\AbstractProcessorType;

use CRM_Dataprocessor_ExtensionUtil as E;

abstract class AbstractFilterHandler {

  /**
   * @var \Civi\DataProcessor\ProcessorType\AbstractProcessorType
   */
  protected $data_processor;

  /**
   * @var bool
   */
  protected $is_required;

  /**
   * @var bool
   */
  protected $is_exposed;

  /**
   * @var array
   */
  protected $defaultFilterValues;

  /**
   * @var array
   */
  protected $filter;

  /**
   * @var String
   */
  protected $alias;

  /**
   * @var String
   */
  protected $title;

  /**
   * @var array
   */
  protected $configuration;

  /**
   * @var bool
   */
  protected $is_initialized = false;

  /**
   * @return \Civi\DataProcessor\DataSpecification\FieldSpecification
   */
  abstract public function getFieldSpecification();

  /**
   * Initialize the filter
   *
   * @throws \Civi\DataProcessor\Exception\DataSourceNotFoundException
   * @throws \Civi\DataProcessor\Exception\InvalidConfigurationException
   * @throws \Civi\DataProcessor\Exception\FieldNotFoundException
   */
  abstract protected function doInitialization();


  /**
   * Resets the filter
   *
   * @return void
   */
  abstract public function resetFilter();

  /**
   * @param array $filterParams
   *   The filter settings
   * @return mixed
   */
  abstract public function setFilter($filterParams);

  public function __construct() {

  }

  public function initialize($filter) {
    if ($this->isInitialized()) {
      return;
    }
    $this->filter = $filter;
    $this->alias = $filter['name'];
    $this->title = $filter['title'];
    $this->configuration = $filter['configuration'];
    $this->is_required = $filter['is_required'];
    $this->is_exposed = $filter['is_exposed'];
    $this->defaultFilterValues = $filter['filter_value'];

    $this->doInitialization();

    $this->setDefaultFilterValues();
    $this->is_initialized = true;
  }

  /**
   * Sets the default filter.
   *
   * @throws \Exception
   */
  public function setDefaultFilterValues() {
    if (!empty($this->defaultFilterValues)) {
      $this->applyFilterFromSubmittedFilterParams($this->defaultFilterValues);
    }
  }

  /**
   * Return whether the filter is initialized
   *
   * @return bool
   */
  public function isInitialized() {
    return $this->is_initialized;
  }

  public function setDataProcessor(AbstractProcessorType $dataProcessor) {
    $this->data_processor = $dataProcessor;
  }

  /**
   * @return bool
   */
  public function isRequired() {
    return $this->is_required;
  }

  /**
   * @return bool
   */
  public function isExposed() {
    return $this->is_exposed;
  }

  /**
   * @return array
   */
  public function getDefaultFilterValues() {
    return $this->defaultFilterValues;
  }

  /**
   * Returns true when this filter has additional configuration
   *
   * @return bool
   */
  public function hasConfiguration() {
    return false;
  }

  /**
   * When this filter type has additional configuration you can add
   * the fields on the form with this function.
   *
   * @param \CRM_Core_Form $form
   * @param array $filter
   */
  public function buildConfigurationForm(\CRM_Core_Form $form, $filter=array()) {
    // Example add a checkbox to the form.
    // $form->add('checkbox', 'show_label', E::ts('Show label'));
  }

  /**
   * When this filter type has configuration specify the template file name
   * for the configuration form.
   *
   * @return false|string
   */
  public function getConfigurationTemplateFileName() {
    // Example return "CRM/FormFieldLibrary/Form/FieldConfiguration/TextField.tpl";
    return false;
  }


  /**
   * Process the submitted values and create a configuration array
   *
   * @param $submittedValues
   * @return array
   */
  public function processConfiguration($submittedValues) {
    // Add the show_label to the configuration array.
    // $configuration['show_label'] = $submittedValues['show_label'];
    // return $configuration;
    return array();
  }

  /**
   * File name of the template to add this filter to the criteria form.
   *
   * @return string
   */
  public function getTemplateFileName() {
    return "CRM/Dataprocessor/Form/Filter/GenericFilter.tpl";
  }

  /**
   * Validate the submitted filter parameters.
   *
   * @param $submittedValues
   * @return array
   */
  public function validateSubmittedFilterParams($submittedValues) {
    $errors = array();
    if ($this->isRequired()) {
      $filterSpec = $this->getFieldSpecification();
      $filterName = $filterSpec->alias;
      $processedSubmittedValues = $this->processSubmittedValues($submittedValues);
      if ($filterSpec->type == 'Date' || $filterSpec->type == 'Timestamp') {
        $relative = \CRM_Utils_Array::value("relative", $processedSubmittedValues);
        $from = \CRM_Utils_Array::value("from", $processedSubmittedValues);
        $to = \CRM_Utils_Array::value("to", $processedSubmittedValues);
        $fromTime = \CRM_Utils_Array::value("from_time", $processedSubmittedValues);
        $toTime = \CRM_Utils_Array::value("to_time", $processedSubmittedValues);
        $op = \CRM_Utils_Array::value("op", $processedSubmittedValues);

        if ($relative != 'null') {
          list($from, $to) = \CRM_Utils_Date::getFromTo($relative, $from, $to, $fromTime, $toTime);
        }
        if (!$from && !$to) {
          $errors[$filterName . '_relative'] = E::ts('Field %1 is required', [1 => $filterSpec->title]);
        }
      }
      elseif (!isset($processedSubmittedValues['op']) || !(isset($processedSubmittedValues['value']) && $processedSubmittedValues['value'])) {
        $errors[$filterName . '_value'] = E::ts('Field %1 is required', [1 => $filterSpec->title]);
      }
    }
    return $errors;
  }

  /**
   * Apply the submitted filter
   *
   * @param $submittedValues
   * @throws \Exception
   */
  public function applyFilterFromSubmittedFilterParams($submittedValues) {
    $isFilterSet = FALSE;
    $filterSpec = $this->getFieldSpecification();
    if ($filterSpec->type == 'Date' || $filterSpec->type == 'Timestamp') {
      $isFilterSet = $this->applyDateFilter($submittedValues);
    }
    elseif (isset($submittedValues['op'])) {
      switch ($submittedValues['op']) {
        case 'IN':
          if (isset($submittedValues['value']) && $submittedValues['value']) {
            $filterParams = [
              'op' => 'IN',
              'value' => $submittedValues['value'],
            ];
            $this->setFilter($filterParams);
            $isFilterSet = TRUE;
          }
          break;
        case 'NOT IN':
          if (isset($submittedValues['value']) && $submittedValues['value']) {
            $filterParams = [
              'op' => 'NOT IN',
              'value' => $submittedValues['value'],
            ];
            $this->setFilter($filterParams);
            $isFilterSet = TRUE;
          }
          break;
        case '=':
        case '!=':
        case '>':
        case '<':
        case '>=':
        case '<=':
          if (isset($submittedValues['value']) && $submittedValues['value']) {
            $filterParams = [
              'op' => $submittedValues['op'],
              'value' => $submittedValues['value'],
            ];
            $this->setFilter($filterParams);
            $isFilterSet = TRUE;
          }
          break;
        case 'has':
          if (isset($submittedValues['value']) && $submittedValues['value']) {
            $filterParams = [
              'op' => 'LIKE',
              'value' => '%' . $submittedValues['value'] . '%',
            ];
            $this->setFilter($filterParams);
            $isFilterSet = TRUE;
          }
          break;
        case 'nhas':
          if (isset($submittedValues['value']) && $submittedValues['value']) {
            $filterParams = [
              'op' => 'NOT LIKE',
              'value' => '%' . $submittedValues['value'] . '%',
            ];
            $this->setFilter($filterParams);
            $isFilterSet = TRUE;
          }
          break;
        case 'sw':
          if (isset($submittedValues['value']) && $submittedValues['value']) {
            $filterParams = [
              'op' => 'LIKE',
              'value' => $submittedValues['value'] . '%',
            ];
            $this->setFilter($filterParams);
            $isFilterSet = TRUE;
          }
          break;
        case 'ew':
          if (isset($submittedValues['value']) && $submittedValues['value']) {
            $filterParams = [
              'op' => 'LIKE',
              'value' => '%' . $submittedValues['value'],
            ];
            $this->setFilter($filterParams);
            $isFilterSet = TRUE;
          }
          break;

        case 'null':
          $filterParams = [
            'op' => 'IS NULL',
            'value' => '',
          ];
          $this->setFilter($filterParams);
          $isFilterSet = TRUE;
          break;
        case 'not null':
          $filterParams = [
            'op' => 'IS NOT NULL',
            'value' => '',
          ];
          $this->setFilter($filterParams);
          $isFilterSet = TRUE;
          break;
        case 'bw':
          if (isset($submittedValues['min']) && $submittedValues['min'] && isset($submittedValues['max']) && $submittedValues['max']) {
            $filterParams = [
              'op' => 'BETWEEN',
              'value' => array($submittedValues['min'], $submittedValues['max']),
            ];
            $this->setFilter($filterParams);
            $isFilterSet = TRUE;
          }
          break;
        case 'nbw':
          if (isset($submittedValues['min']) && $submittedValues['min'] && isset($submittedValues['max']) && $submittedValues['max']) {
            $filterParams = [
              'op' => 'NOT BETWEEN',
              'value' => array($submittedValues['min'], $submittedValues['max']),
            ];
            $this->setFilter($filterParams);
            $isFilterSet = TRUE;
          }
          break;
      }
    }
    if ($this->isRequired() && !$isFilterSet) {
      throw new FilterRequiredException('Field ' . $filterSpec->title . ' is required');
    }
  }

  /**
   * Process the submitted values to a filter value
   * Which could then be processed by applyFilter function
   *
   * @param $submittedValues
   * @return array
   */
  public function processSubmittedValues($submittedValues) {
    $return = array();
    $filterSpec = $this->getFieldSpecification();
    $alias = $filterSpec->alias;
    if (isset($submittedValues[$alias.'_op'])) {
      $return['op'] = $submittedValues[$alias . '_op'];
    }
    if (isset($submittedValues[$alias.'_value'])) {
      $return['value'] = $submittedValues[$alias . '_value'];
      if (empty($return['value'])) {
        // Don't save an empty default criteria to the database or we can't override (eg. via URL)
        unset($return['value']);
      }
    }
    if (isset($submittedValues[$alias.'_relative'])) {
      $return['relative'] = $submittedValues[$alias . '_relative'];
    }
    if (isset($submittedValues[$alias.'_from'])) {
      $return['from'] = $submittedValues[$alias . '_from'];
    } elseif (isset($submittedValues[$alias.'_low'])) {
      $return['from'] = $submittedValues[$alias . '_low'];
    }
    if (isset($submittedValues[$alias.'_to'])) {
      $return['to'] = $submittedValues[$alias . '_to'];
    } elseif (isset($submittedValues[$alias.'_high'])) {
      $return['to'] = $submittedValues[$alias . '_high'];
    }
    if (isset($submittedValues[$alias.'_from_time'])) {
      $return['from_time'] = $submittedValues[$alias.'_from_time'];
    }
    if (isset($submittedValues[$alias.'_to_time'])) {
      $return['to_time'] = $submittedValues[$alias.'_to_time'];
    }
    if (isset($submittedValues[$alias.'_min'])) {
      $return['min'] = $submittedValues[$alias.'_min'];
    }
    if (isset($submittedValues[$alias.'_max'])) {
      $return['max'] = $submittedValues[$alias.'_max'];
    }
    return $return;
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
    static $count = 1;
    $types = \CRM_Utils_Type::getValidTypes();
    $fieldSpec = $this->getFieldSpecification();
    $operations = $this->getOperatorOptions($fieldSpec);
    $type = \CRM_Utils_Type::T_STRING;
    $defaults = array();

    $title = $fieldSpec->title;
    $alias = $fieldSpec->alias;
    if ($this->isRequired()) {
      $title .= ' <span class="crm-marker">*</span>';
    }

    $sizeClass = 'huge';
    $minWidth = 'min-width: 250px;';
    if ($size =='compact') {
      $sizeClass = 'medium';
      $minWidth = '';
    }

    if (isset($types[$fieldSpec->type])) {
      $type = $types[$fieldSpec->type];
    }
    if ($fieldSpec->getOptions()) {
      $form->add('select', "{$alias}_op", E::ts('Operator:'), $operations, true, [
        'style' => $minWidth,
        'class' => 'crm-select2 '.$sizeClass,
        'multiple' => FALSE,
        'placeholder' => E::ts('- select -'),
      ]);
      $form->add('select', "{$alias}_value", null, $fieldSpec->getOptions(), false, [
        'style' => $minWidth,
        'class' => 'crm-select2 '.$sizeClass,
        'multiple' => TRUE,
        'placeholder' => E::ts('- Select -'),
      ]);
      if (isset($defaultFilterValue['op'])) {
        $defaults[$alias . '_op'] = $defaultFilterValue['op'];
      } else {
        $defaults[$alias . '_op'] = key($operations);
      }
      if (isset($defaultFilterValue['value'])) {
        $defaults[$alias.'_value'] = $defaultFilterValue['value'];
      }
    }
    else {
      switch ($type) {
        case \CRM_Utils_Type::T_DATE:
        case \CRM_Utils_Type::T_TIMESTAMP:
          $additionalOptions['null'] = E::ts('Not set');
          \CRM_Dataprocessor_Utils_Form::addDatePickerRange($form, $alias, $title, FALSE, FALSE, E::ts('From'), E::ts('To'), $additionalOptions, '_high', '_low');
          if (isset($defaultFilterValue['op'])) {
            $defaults[$alias . '_op'] = $defaultFilterValue['op'];
          }
          if (isset($defaultFilterValue['value'])) {
            $defaults[$alias.'_value'] = $defaultFilterValue['value'];
          }
          if (isset($defaultFilterValue['relative'])) {
            $defaults[$alias.'_relative'] = $defaultFilterValue['relative'];
          }
          if (isset($defaultFilterValue['from'])) {
            $defaults[$alias.'_from'] = $defaultFilterValue['from'];
          }
          if (isset($defaultFilterValue['to'])) {
            $defaults[$alias.'_to'] = $defaultFilterValue['to'];
          }
          if (isset($defaultFilterValue['from_time'])) {
            $defaults[$alias.'_from_time'] = $defaultFilterValue['from_time'];
          }
          if (isset($defaultFilterValue['to_time'])) {
            $defaults[$alias.'_to_time'] = $defaultFilterValue['to_time'];
          }

          $count ++;
          break;
        case \CRM_Utils_Type::T_INT:
        case \CRM_Utils_Type::T_FLOAT:
        case \CRM_Utils_Type::T_MONEY:
          $form->add('select', "{$alias}_op", E::ts('Operator:'), $operations, true, [
            'onchange' => "return showHideMaxMinVal( '$alias', this.value );",
            'style' => $minWidth,
            'class' => 'crm-select2 '.$sizeClass,
            'multiple' => FALSE,
            'placeholder' => E::ts('- select -'),
          ]);
          // we need text box for value input
          $form->add('text', "{$alias}_value", NULL, ['class' => $sizeClass]);
          if (isset($defaultFilterValue['op']) && $defaultFilterValue['op']) {
            $defaults[$alias . '_op'] = $defaultFilterValue['op'];
          } else {
            $defaults[$alias . '_op'] = key($operations);
          }
          if (isset($defaultFilterValue['value'])) {
            $defaults[$alias.'_value'] = $defaultFilterValue['value'];
          }

          // and a min value input box
          $form->add('text', "{$alias}_min", E::ts('Min'), ['class' => $sizeClass]);
          // and a max value input box
          $form->add('text', "{$alias}_max", E::ts('Max'), ['class' => $sizeClass]);

          if (isset($defaultFilterValue['min'])) {
            $defaults[$alias.'_min'] = $defaultFilterValue['min'];
          }
          if (isset($defaultFilterValue['max'])) {
            $defaults[$alias.'_max'] = $defaultFilterValue['max'];
          }
          break;
        default:
          // default type is string
          $form->add('select', "{$alias}_op", E::ts('Operator:'), $operations, true, [
            'style' => $minWidth,
            'class' => 'crm-select2 '.$sizeClass,
            'multiple' => FALSE,
            'placeholder' => E::ts('- select -'),
          ]);
          // we need text box for value input
          $form->add('text', "{$alias}_value", NULL, ['class' => $sizeClass]);
          if (isset($defaultFilterValue['op']) && $defaultFilterValue['op']) {
            $defaults[$alias . '_op'] = $defaultFilterValue['op'];
          } else {
            $defaults[$alias . '_op'] = key($operations);
          }
          if (isset($defaultFilterValue['value'])) {
            $defaults[$alias.'_value'] = $defaultFilterValue['value'];
          }
          break;
      }
    }

    $filter['type'] = $fieldSpec->type;
    $filter['alias'] = $fieldSpec->alias;
    $filter['title'] = $title;
    $filter['size'] = $size;

    if (count($defaults)) {
      $form->setDefaults($defaults);
    }

    return $filter;
  }

  /**
   * Get the default operator
   * @return string
   */
  public function getDefaultOperator() {
    $operators = $this->getOperatorOptions($this->getFieldSpecification());
    return key($operators);
  }

  /**
   * Get a list of possible operator options.
   *
   * @param \Civi\DataProcessor\DataSpecification\FieldSpecification $fieldSpec
   *
   * @return array
   */
  protected function getOperatorOptions(\Civi\DataProcessor\DataSpecification\FieldSpecification $fieldSpec) {
    if ($fieldSpec->getOptions()) {
      return array(
        'IN' => E::ts('Is one of'),
        'NOT IN' => E::ts('Is not one of'),
        'null' => E::ts('Is empty'),
        'not null' => E::ts('Is not empty'),
      );
    }
    $types = \CRM_Utils_Type::getValidTypes();
    $type = \CRM_Utils_Type::T_STRING;
    if (isset($types[$fieldSpec->type])) {
      $type = $types[$fieldSpec->type];
    }
    switch ($type) {
      case \CRM_Utils_Type::T_DATE:
      case \CRM_Utils_Type::T_TIMESTAMP:
        return array();
        break;
      case \CRM_Utils_Type::T_INT:
      case \CRM_Utils_Type::T_FLOAT:
      case \CRM_Utils_Type::T_MONEY:
        return array(
          '=' => E::ts('Is equal to'),
          '<=' => E::ts('Is less than or equal to'),
          '>=' => E::ts('Is greater than or equal to'),
          '<' => E::ts('Is less than'),
          '>' => E::ts('Is greater than'),
          '!=' => E::ts('Is not equal to'),
          'null' => E::ts('Is empty'),
          'not null' => E::ts('Is not empty'),
          'bw' => E::ts('Is between'),
          'nbw' => E::ts('Is not between'),
        );
        break;
    }
    return array(
      'has' => E::ts('Contains'),
      '=' => E::ts('Is equal to'),
      '!=' => E::ts('Is not equal to'),
      'sw' => E::ts('Starts with'),
      'ew' => E::ts('Ends with'),
      'nhas' => E::ts('Does not contain'),
      'null' => E::ts('Is empty'),
      'not null' => E::ts('Is not empty'),
    );
  }

  /**
   * @param array $submittedValues
   * @return string|null
   */
  protected function applyDateFilter($submittedValues) {
    $type = $this->getFieldSpecification()->type;
    $op = \CRM_Utils_Array::value("op", $submittedValues);
    $relative = \CRM_Utils_Array::value("relative", $submittedValues);
    $from = \CRM_Utils_Array::value("from", $submittedValues);
    $to = \CRM_Utils_Array::value("to", $submittedValues);
    $fromTime = \CRM_Utils_Array::value("from_time", $submittedValues);
    $toTime = \CRM_Utils_Array::value("to_time", $submittedValues);
    if (!$toTime) {
      $toTime = '235959';
    }

    if ($relative == 'null') {
      $filterParams = [
        'op' => 'IS NULL',
        'value' => '',
      ];
      $this->setFilter($filterParams);
      return TRUE;
    } else {
      list($from, $to) = \CRM_Utils_Date::getFromTo($relative, $from, $to, $fromTime, $toTime);
    }
    if ($from && $to) {
      $from = ($type == "Date") ? substr($from, 0, 8) : $from;
      $to = ($type == "Date") ? substr($to, 0, 8) : $to;
      $this->setFilter(array(
        'op' => 'BETWEEN',
        'value' => array($from, $to),
      ));
      return TRUE;
    } elseif ($from) {
      $from = ($type == "Date") ? substr($from, 0, 8) : $from;
      $this->setFilter(array(
        'op' => '>=',
        'value' => $from,
      ));
      return TRUE;
    } elseif ($to) {
      $to = ($type == "Date") ? substr($to, 0, 8) : $to;
      $this->setFilter(array(
        'op' => '<=',
        'value' => $to,
      ));
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Returns the table alias of a sql data flow.
   *
   * @param \Civi\DataProcessor\DataFlow\SqlDataFlow $dataFlow
   * @return string|null
   */
  protected function getTableAlias(SqlDataFlow $dataFlow) {
    $tableAlias = $dataFlow->getName();
    if ($dataFlow instanceof SqlTableDataFlow) {
      $tableAlias = $dataFlow->getTableAlias();
    } elseif ($dataFlow instanceof CombinedSqlDataFlow) {
      $tableAlias = $dataFlow->getPrimaryTableAlias();
    }
    return $tableAlias;
  }

}
