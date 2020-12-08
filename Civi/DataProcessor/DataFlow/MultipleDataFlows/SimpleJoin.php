<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

namespace Civi\DataProcessor\DataFlow\MultipleDataFlows;

use Civi\DataProcessor\DataFlow\AbstractDataFlow;
use Civi\DataProcessor\DataFlow\CombinedDataFlow\CombinedSqlDataFlow;
use Civi\DataProcessor\DataFlow\SqlDataFlow;
use Civi\DataProcessor\DataFlow\SqlTableDataFlow;
use Civi\DataProcessor\DataSpecification\FieldExistsException;
use Civi\DataProcessor\ProcessorType\AbstractProcessorType;
use Civi\DataProcessor\Source\SourceInterface;

use CRM_Dataprocessor_ExtensionUtil as E;

class SimpleJoin implements JoinInterface, SqlJoinInterface {

  private $isInitialized = false;

  /**
   * @var string
   *   The name of the left field
   */
  protected $left_field;

  /**
   * @var string
   *   The name of the right field
   */
  protected $right_field;

  /**
   * @var string
   *   The alias of the left field
   */
  protected $left_field_alias;

  /**
   * @var string
   *   The alias of the right field
   */
  protected $right_field_alias;

  /**
   * @var \Civi\DataProcessor\DataSpecification\FieldSpecification
   *   The alias of the left field
   */
  protected $leftFieldSpec;

  /**
   * @var \Civi\DataProcessor\DataSpecification\FieldSpecification
   *   The alias of the right field
   */
  protected $rightFieldSpec;

  /**
   * @var string
   *   The prefix for the left field, or in SQL join mode the left table
   */
  protected $left_prefix;

  /**
   * @var string
   *   The prefix for the right field, or in SQL join mode the right table
   */
  protected $right_prefix;

  /**
   * @var String
   */
  protected $right_table;

  /**
   * @var String
   */
  protected $left_table;

  /**
   * @var \Civi\DataProcessor\Source\SourceInterface
   */
  protected $left_source;

  /**
   * @var \Civi\DataProcessor\Source\SourceInterface
   */
  protected $right_source;

  /**
   * @var String
   *   The join type, e.g. INNER, LEFT, OUT etc..
   */
  protected  $type = "INNER";

  /**
   * @var AbstractProcessorType
   */
  private $dataProcessor;

  /**
   * @var \Civi\DataProcessor\DataFlow\SqlDataFlow\OrClause
   */
  private $rightClause = null;

  public function __construct($left_prefix = null, $left_field = null, $right_prefix = null, $right_field = null, $type = "INNER") {
    $this->left_prefix = $left_prefix;
    $this->left_field = $left_field;
    $this->right_prefix = $right_prefix;
    $this->right_field = $right_field;
    $this->type = $type;
  }

  /**
   * Returns true when this join has additional configuration
   *
   * @return bool
   */
  public function hasConfiguration() {
    return true;
  }

  /**
   * When this join has additional configuration you can add
   * the fields on the form with this function.
   *
   * @param \CRM_Core_Form $form
   * @param SourceInterface $joinFromSource
   * @param SourceInterface[] $joinableToSources
   * @param array $joinConfiguration
   *   The current join configuration
   */
  public function buildConfigurationForm(\CRM_Core_Form $form, SourceInterface $joinFromSource, $joinableToSources, $joinConfiguration=array()) {
    $leftFields = \CRM_Dataprocessor_Utils_DataSourceFields::getAvailableFieldsInDataSource($joinFromSource, '', '');
    $form->add('select', 'left_field', ts('Select field'), $leftFields, true, array(
      'style' => 'min-width:250px',
      'class' => 'crm-select2 huge',
      'placeholder' => E::ts('- select -'),
    ));

    $rightFields = array();
    foreach($joinableToSources as $joinToSource) {
      $rightFields = array_merge($rightFields, \CRM_Dataprocessor_Utils_DataSourceFields::getAvailableFieldsInDataSource($joinToSource, $joinToSource->getSourceTitle().' :: ', $joinToSource->getSourceName().'::'));
    }

    $form->add('select', 'right_field', ts('Select field'), $rightFields, true, array(
      'style' => 'min-width:250px',
      'class' => 'crm-select2 huge',
      'placeholder' => E::ts('- select -'),
    ));

    // Backwords compatability
    if (isset($joinConfiguration['right_prefix']) && $joinConfiguration['right_prefix'] == $joinFromSource->getSourceName()) {
      $joinConfigurationBackwardsCompatibility = $joinConfiguration;
      $joinConfiguration['left_prefix'] = '';
      $joinConfiguration['left_field'] = $joinConfigurationBackwardsCompatibility['right_field'];
      $joinConfiguration['right_prefix'] = $joinConfigurationBackwardsCompatibility['left_prefix'];
      $joinConfiguration['right_field'] = $joinConfigurationBackwardsCompatibility['left_field'];
    }

    $defaults = array();
    if (isset($joinConfiguration['left_field'])) {
      $defaults['left_field'] = $joinConfiguration['left_field'];
    }
    if (isset($joinConfiguration['right_prefix'])) {
      $defaults['right_field'] = $joinConfiguration['right_prefix']."::".$joinConfiguration['right_field'];
    }

    $form->setDefaults($defaults);
  }

  /**
   * When this join has configuration specify the template file name
   * for the configuration form.
   *
   * @return false|string
   */
  public function getConfigurationTemplateFileName() {
    return "CRM/Dataprocessor/Form/MultipleDataFlows/SimpleJoin.tpl";
  }


  /**
   * Process the submitted values and create a configuration array
   *
   * @param $submittedValues
   * @return array
   */
  public function processConfiguration($submittedValues, SourceInterface $joinFromSource) {
    $left_prefix = $joinFromSource->getSourceName();
    $left_field = $this->correctFieldName($submittedValues['left_field'], $joinFromSource);
    list($right_prefix, $right_field) = explode("::",$submittedValues['right_field'], 2);

    $configuration = array(
      'left_prefix' => $left_prefix,
      'left_field' => $left_field,
      'right_prefix' => $right_prefix,
      'right_field' => $right_field
    );
    return $configuration;
  }

  /**
   * This function corrects the field name.
   * Basically when we add a join the name of the source is empty but as soon as we save it
   * the name is set. And the fields have the name of the source in their alias.
   *
   * For example if we join on contribution with the left table campaign
   * our join field is called id (the id of the campaign). The alias of this field
   * is upon adding _id
   * but after the source name is set to for example my_campaign. Then the alias becomes my_campaign_id
   * so we should correct this name.
   *
   * @param String $fieldAlias
   * @param \Civi\DataProcessor\Source\SourceInterface $joinFromSource
   * @return String
   */
  private function correctFieldName($fieldAlias, SourceInterface $joinFromSource) {
    try {
      $fields = \CRM_Dataprocessor_Utils_DataSourceFields::getAvailableFieldsInDataSource($joinFromSource, '', '');
      if (isset($fields[$fieldAlias])) {
        // No need for correction as the field exists.
        return $fieldAlias;
      }
      $sourceWithEmptyName = clone $joinFromSource;
      $sourceWithEmptyName->setSourceName('');
      $fieldsWithoutPrefix = \CRM_Dataprocessor_Utils_DataSourceFields::getAvailableFieldsInDataSource($sourceWithEmptyName, '', '');
      $fieldKeys = array_keys($fields);
      $fieldsWithoutPrefixKeys = array_keys($fieldsWithoutPrefix);
      $key = array_search($fieldAlias, $fieldsWithoutPrefixKeys);
      if ($key!== false && isset($fieldKeys[$key])) {
        return $fieldKeys[$key];
      }
    } catch (\Exception $e) {
      // Do nothing.
    }
    // We could not convert the field alias so return it as it is.
    return $fieldAlias;
  }

  /**
   * @param array $configuration
   *
   * @return \Civi\DataProcessor\DataFlow\MultipleDataFlows\JoinInterface
   */
  public function setConfiguration($configuration) {
    if (isset($configuration['left_field'])) {
      $this->left_field = $configuration['left_field'];
    }
    if (isset($configuration['left_prefix'])) {
      $this->left_prefix = $configuration['left_prefix'];
    }
    if (isset($configuration['right_field'])) {
      $this->right_field = $configuration['right_field'];
    }
    if (isset($configuration['right_prefix'])) {
      $this->right_prefix = $configuration['right_prefix'];
    }
    if (isset($configuration['type'])) {
      $this->type = $configuration['type'];
    }
    return $this;
  }

  /**
   * @param AbstractProcessorType $dataProcessor
   * @return \Civi\DataProcessor\DataFlow\MultipleDataFlows\JoinInterface
   * @throws \Exception
   */
  public function setDataProcessor(AbstractProcessorType $dataProcessor) {
    $this->dataProcessor = $dataProcessor;
  }

  /**
   * Returns true when this join is compatible with this data flow
   *
   * @param \Civi\DataProcessor\DataFlow\AbstractDataFlow $
   * @return bool
   */
  public function worksWithDataFlow(AbstractDataFlow $dataFlow) {
    if (!$dataFlow instanceof SqlDataFlow) {
      return false;
    }
    $this->initialize();
    if ($dataFlow->getTableAlias() == $this->left_table) {
      return true;
    }
    if ($dataFlow->getTableAlias() == $this->right_table) {
      return true;
    }
    return false;
  }

  public function initialize() {
    if ($this->isInitialized) {
      return $this;
    }
    if ($this->left_prefix && $this->left_field) {
      $this->left_table = $this->left_prefix;
      $this->left_source = $this->dataProcessor->getDataSourceByName($this->left_prefix);
      if ($this->left_source) {
        $this->leftFieldSpec = $this->left_source->getAvailableFields()->getFieldSpecificationByAlias($this->left_field);
        if (!$this->leftFieldSpec) {
          $this->leftFieldSpec = $this->left_source->getAvailableFields()->getFieldSpecificationByName($this->left_field);
        }
        if ($this->leftFieldSpec) {
          $leftTable = $this->left_source->ensureFieldForJoin($this->leftFieldSpec);
          if ($leftTable && $leftTable instanceof SqlTableDataFlow) {
            $this->left_table = $leftTable->getTableAlias();
          }
          $this->left_field_alias = $this->leftFieldSpec->alias;
        }
      }
    }
    if ($this->right_prefix && $this->right_field) {
      $this->right_table = $this->right_prefix;
      $this->right_source = $this->dataProcessor->getDataSourceByName($this->right_prefix);
      if ($this->right_source) {
        $this->rightFieldSpec = $this->right_source->getAvailableFields()->getFieldSpecificationByAlias($this->right_field);
        if (!$this->rightFieldSpec) {
          $this->rightFieldSpec = $this->right_source->getAvailableFields()->getFieldSpecificationByName($this->right_field);
        }
        if ($this->rightFieldSpec) {
          $rightTable = $this->right_source->ensureFieldForJoin($this->rightFieldSpec);
          if ($rightTable && $rightTable instanceof SqlTableDataFlow) {
            $this->right_table = $rightTable->getTableAlias();
          }
          $this->right_field_alias = $this->rightFieldSpec->alias;
        }
      }
    }

    $this->isInitialized = true;
    return $this;
  }

  /**
   * Joins the records sets and return the new created set.
   *
   * @param $left_record_set
   * @param $right_record_set
   *
   * @return array
   */
  public function join($left_record_set, $right_record_set) {
    $joined_record_set = array();
    if ($this->type == 'INNER' || $this->type == 'LEFT') {
      foreach ($left_record_set as $left_index => $left_record) {
        $is_record_present_in_right_set = FALSE;
        foreach ($right_record_set as $right_index => $right_record) {
          if (isset($left_record[$this->right_field_alias]) && isset($right_record[$this->left_field_alias])) {
            if ($left_record[$this->right_field_alias] == $right_record[$this->left_field_alias]) {
              $joined_record_set[] = array_merge($left_record, $right_record);
              $is_record_present_in_right_set = TRUE;
            }
          }
        }
        if (!$is_record_present_in_right_set && $this->type == 'LEFT') {
          $joined_record_set[] = $left_record;
        }
      }
    } elseif ($this->type == 'RIGHT') {
      foreach ($right_record_set as $right_index => $right_record) {
        $is_record_present_in_left_set = FALSE;
        foreach ($left_record_set as $left_index => $left_record) {
          if (isset($left_record[$this->right_field_alias]) && isset($right_record[$this->left_field_alias])) {
            if ($left_record[$this->right_field_alias] == $right_record[$this->left_field_alias]) {
              $joined_record_set[] = array_merge($left_record, $right_record);
              $is_record_present_in_left_set = TRUE;
            }
          }
        }
        if (!$is_record_present_in_left_set && $this->type == 'RIGHT') {
          $joined_record_set[] = $right_record;
        }
      }
    }
    return $joined_record_set;
  }

  /**
   * Prepares the right data flow based on the data in the left record set.
   *
   * @param $left_record_set
   * @param \Civi\DataProcessor\DataFlow\AbstractDataFlow $rightDataFlow
   *
   * @return AbstractDataFlow
   * @throws \Exception
   */
  public function prepareRightDataFlow($left_record_set, AbstractDataFlow $rightDataFlow) {
    if ($rightDataFlow instanceof SqlTableDataFlow) {
      if ($this->rightClause) {
        $rightDataFlow->removeWhereClause($this->rightClause);
      }
      $table = $rightDataFlow->getTableAlias();
      $this->rightClause = new SqlDataFlow\OrClause();
      foreach ($left_record_set as $left_record) {
        if (isset($left_record[$this->right_field_alias])) {
          $value = $left_record[$this->right_field_alias];
          $this->rightClause->addWhereClause(new SqlDataFlow\SimpleWhereClause($table, $this->leftFieldSpec->name, '=', $value));

          // Make sure the join field is also available in the select statement of the query.
          try {
            $rightDataFlow->getDataSpecification()->addFieldSpecification($this->left_field_alias, $this->leftFieldSpec);
          } catch (FieldExistsException $e) {
            // Do nothing.
          }
        }
      }
      $rightDataFlow->addWhereClause($this->rightClause);
    }
    return $rightDataFlow;
  }

  /**
   * Returns the SQL join statement
   *
   * For example:
   *  INNER JOIN civicrm_contact source_3 ON source_3.id = source_2.contact_id
   * OR
   *  LEFT JOIN civicrm_contact source_3 ON source3.id = source_2.contact_id
   *
   * @param \Civi\DataProcessor\DataFlow\MultipleDataFlows\DataFlowDescription $sourceDataFlowDescription
   *   The source data flow description used to genereate the join stament.
   *
   * @return string
   */
  public function getJoinClause(DataFlowDescription $sourceDataFlowDescription) {
    $this->initialize();
    $joinClause = "";
    if ($sourceDataFlowDescription->getJoinSpecification()) {
      $leftColumnName = "`{$this->left_table}`.`{$this->left_field}`";
      if ($this->leftFieldSpec) {
        $leftColumnName = $this->leftFieldSpec->getSqlColumnName($this->left_table);
      }
      $rightColumnName = "`{$this->right_table}`.`{$this->right_field}`";
      if ($this->rightFieldSpec) {
        $rightColumnName = $this->rightFieldSpec->getSqlColumnName($this->right_table);
      }
      $joinClause = "ON {$leftColumnName}  = {$rightColumnName}";
    }
    if ($sourceDataFlowDescription->getDataFlow() instanceof SqlDataFlow) {
      $tablePart = $sourceDataFlowDescription->getDataFlow()->getTableStatement();
    }

    return "{$this->type} JOIN {$tablePart} {$joinClause} ";
  }

  public function getLeftTable() {
    return $this->left_table;
  }

  public function getLeftPrefix() {
    return $this->left_prefix;
  }

  public function setLeftTable($table) {
    $this->left_table = $table;
  }

  public function setLeftPrefix($prefix) {
    $this->left_prefix = $prefix;
  }

  public function getRightTable() {
    return $this->right_table;
  }

  public function getRightPrefix() {
    return $this->right_prefix;
  }

  public function setRightTable($table) {
    $this->right_table = $table;
  }

  public function setRightPrefix($prefix) {
    $this->right_prefix = $prefix;
  }


}
