<?php

/**
 * @package CRM
 * @copyright CiviCRM LLC (c) 2004-2019
 *
 * Generated from /buildkit/build/search/sites/default/files/civicrm/ext/dataprocessor/xml/schema/CRM/Dataprocessor/DataProcessor.xml
 * DO NOT EDIT.  Generated by CRM_Core_CodeGen
 * (GenCodeChecksum:28cee8d17fc4e4fca4b21a0b4dbf17ff)
 */

/**
 * Database access object for the DataProcessor entity.
 */
class CRM_Dataprocessor_DAO_DataProcessor extends CRM_Core_DAO {

  /**
   * Static instance to hold the table name.
   *
   * @var string
   */
  static $_tableName = 'civicrm_data_processor';

  /**
   * Should CiviCRM log any modifications to this table in the civicrm_log table.
   *
   * @var bool
   */
  static $_log = FALSE;

  /**
   * Unique DataProcessor ID
   *
   * @var int unsigned
   */
  public $id;

  /**
   * @var string
   */
  public $name;

  /**
   * @var string
   */
  public $title;

  /**
   * @var string
   */
  public $type;

  /**
   * @var text
   */
  public $configuration;

  /**
   * @var text
   */
  public $aggregation;

  /**
   * @var boolean
   */
  public $is_active;

  /**
   * @var text
   */
  public $description;

  /**
   * @var string
   */
  public $storage_type;

  /**
   * @var text
   */
  public $storage_configuration;

  /**
   * @var int unsigned
   */
  public $status;

  /**
   * @var string
   */
  public $source_file;

  /**
   * Class constructor.
   */
  public function __construct() {
    $this->__table = 'civicrm_data_processor';
    parent::__construct();
  }

  /**
   * Returns all the column names of this table
   *
   * @return array
   */
  public static function &fields() {
    if (!isset(Civi::$statics[__CLASS__]['fields'])) {
      Civi::$statics[__CLASS__]['fields'] = [
        'id' => [
          'name' => 'id',
          'type' => CRM_Utils_Type::T_INT,
          'description' => CRM_Dataprocessor_ExtensionUtil::ts('Unique DataProcessor ID'),
          'required' => TRUE,
          'table_name' => 'civicrm_data_processor',
          'entity' => 'DataProcessor',
          'bao' => 'CRM_Dataprocessor_DAO_DataProcessor',
          'localizable' => 0,
        ],
        'name' => [
          'name' => 'name',
          'type' => CRM_Utils_Type::T_STRING,
          'title' => CRM_Dataprocessor_ExtensionUtil::ts('Name'),
          'required' => FALSE,
          'maxlength' => 255,
          'size' => CRM_Utils_Type::HUGE,
          'table_name' => 'civicrm_data_processor',
          'entity' => 'DataProcessor',
          'bao' => 'CRM_Dataprocessor_DAO_DataProcessor',
          'localizable' => 0,
        ],
        'title' => [
          'name' => 'title',
          'type' => CRM_Utils_Type::T_STRING,
          'title' => CRM_Dataprocessor_ExtensionUtil::ts('Title'),
          'required' => TRUE,
          'maxlength' => 255,
          'size' => CRM_Utils_Type::HUGE,
          'table_name' => 'civicrm_data_processor',
          'entity' => 'DataProcessor',
          'bao' => 'CRM_Dataprocessor_DAO_DataProcessor',
          'localizable' => 0,
        ],
        'type' => [
          'name' => 'type',
          'type' => CRM_Utils_Type::T_STRING,
          'title' => CRM_Dataprocessor_ExtensionUtil::ts('Type'),
          'required' => TRUE,
          'maxlength' => 255,
          'size' => CRM_Utils_Type::HUGE,
          'table_name' => 'civicrm_data_processor',
          'entity' => 'DataProcessor',
          'bao' => 'CRM_Dataprocessor_DAO_DataProcessor',
          'localizable' => 0,
        ],
        'configuration' => [
          'name' => 'configuration',
          'type' => CRM_Utils_Type::T_TEXT,
          'title' => CRM_Dataprocessor_ExtensionUtil::ts('Configuration'),
          'required' => FALSE,
          'table_name' => 'civicrm_data_processor',
          'entity' => 'DataProcessor',
          'bao' => 'CRM_Dataprocessor_DAO_DataProcessor',
          'localizable' => 0,
          'serialize' => self::SERIALIZE_JSON,
        ],
        'aggregation' => [
          'name' => 'aggregation',
          'type' => CRM_Utils_Type::T_TEXT,
          'title' => CRM_Dataprocessor_ExtensionUtil::ts('Aggregation Fields'),
          'required' => FALSE,
          'table_name' => 'civicrm_data_processor',
          'entity' => 'DataProcessor',
          'bao' => 'CRM_Dataprocessor_DAO_DataProcessor',
          'localizable' => 0,
          'serialize' => self::SERIALIZE_JSON,
        ],
        'is_active' => [
          'name' => 'is_active',
          'type' => CRM_Utils_Type::T_BOOLEAN,
          'title' => CRM_Dataprocessor_ExtensionUtil::ts('Is active'),
          'required' => TRUE,
          'table_name' => 'civicrm_data_processor',
          'entity' => 'DataProcessor',
          'bao' => 'CRM_Dataprocessor_DAO_DataProcessor',
          'localizable' => 0,
        ],
        'description' => [
          'name' => 'description',
          'type' => CRM_Utils_Type::T_TEXT,
          'title' => CRM_Dataprocessor_ExtensionUtil::ts('Description'),
          'required' => FALSE,
          'table_name' => 'civicrm_data_processor',
          'entity' => 'DataProcessor',
          'bao' => 'CRM_Dataprocessor_DAO_DataProcessor',
          'localizable' => 0,
        ],
        'storage_type' => [
          'name' => 'storage_type',
          'type' => CRM_Utils_Type::T_STRING,
          'title' => CRM_Dataprocessor_ExtensionUtil::ts('Storage Type'),
          'required' => FALSE,
          'maxlength' => 255,
          'size' => CRM_Utils_Type::HUGE,
          'table_name' => 'civicrm_data_processor',
          'entity' => 'DataProcessor',
          'bao' => 'CRM_Dataprocessor_DAO_DataProcessor',
          'localizable' => 0,
        ],
        'storage_configuration' => [
          'name' => 'storage_configuration',
          'type' => CRM_Utils_Type::T_TEXT,
          'title' => CRM_Dataprocessor_ExtensionUtil::ts('Storage Configuration'),
          'required' => FALSE,
          'table_name' => 'civicrm_data_processor',
          'entity' => 'DataProcessor',
          'bao' => 'CRM_Dataprocessor_DAO_DataProcessor',
          'localizable' => 0,
          'serialize' => self::SERIALIZE_JSON,
        ],
        'status' => [
          'name' => 'status',
          'type' => CRM_Utils_Type::T_INT,
          'title' => CRM_Dataprocessor_ExtensionUtil::ts('Status'),
          'required' => FALSE,
          'default' => '0',
          'table_name' => 'civicrm_data_processor',
          'entity' => 'DataProcessor',
          'bao' => 'CRM_Dataprocessor_DAO_DataProcessor',
          'localizable' => 0,
        ],
        'source_file' => [
          'name' => 'source_file',
          'type' => CRM_Utils_Type::T_STRING,
          'title' => CRM_Dataprocessor_ExtensionUtil::ts('Source File'),
          'required' => FALSE,
          'maxlength' => 255,
          'size' => CRM_Utils_Type::HUGE,
          'table_name' => 'civicrm_data_processor',
          'entity' => 'DataProcessor',
          'bao' => 'CRM_Dataprocessor_DAO_DataProcessor',
          'localizable' => 0,
        ],
      ];
      CRM_Core_DAO_AllCoreTables::invoke(__CLASS__, 'fields_callback', Civi::$statics[__CLASS__]['fields']);
    }
    return Civi::$statics[__CLASS__]['fields'];
  }

  /**
   * Return a mapping from field-name to the corresponding key (as used in fields()).
   *
   * @return array
   *   Array(string $name => string $uniqueName).
   */
  public static function &fieldKeys() {
    if (!isset(Civi::$statics[__CLASS__]['fieldKeys'])) {
      Civi::$statics[__CLASS__]['fieldKeys'] = array_flip(CRM_Utils_Array::collect('name', self::fields()));
    }
    return Civi::$statics[__CLASS__]['fieldKeys'];
  }

  /**
   * Returns the names of this table
   *
   * @return string
   */
  public static function getTableName() {
    return self::$_tableName;
  }

  /**
   * Returns if this table needs to be logged
   *
   * @return bool
   */
  public function getLog() {
    return self::$_log;
  }

  /**
   * Returns the list of fields that can be imported
   *
   * @param bool $prefix
   *
   * @return array
   */
  public static function &import($prefix = FALSE) {
    $r = CRM_Core_DAO_AllCoreTables::getImports(__CLASS__, 'data_processor', $prefix, []);
    return $r;
  }

  /**
   * Returns the list of fields that can be exported
   *
   * @param bool $prefix
   *
   * @return array
   */
  public static function &export($prefix = FALSE) {
    $r = CRM_Core_DAO_AllCoreTables::getExports(__CLASS__, 'data_processor', $prefix, []);
    return $r;
  }

  /**
   * Returns the list of indices
   *
   * @param bool $localize
   *
   * @return array
   */
  public static function indices($localize = TRUE) {
    $indices = [];
    return ($localize && !empty($indices)) ? CRM_Core_DAO_AllCoreTables::multilingualize(__CLASS__, $indices) : $indices;
  }

}
