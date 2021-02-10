<?php

use Civi\DataProcessor\FieldOutputHandler\OutputHandlerAggregate;
use CRM_Dataprocessor_ExtensionUtil as E;

class CRM_Dataprocessor_BAO_DataProcessor extends CRM_Dataprocessor_DAO_DataProcessor {

  static $importingDataProcessors = array();

  public static function checkName($title, $id=null,$name=null) {
    if (!$name) {
      $name = preg_replace('@[^a-z0-9_]+@','_',strtolower($title));
    }

    $name = preg_replace('@[^a-z0-9_]+@','_',strtolower($name));
    $name_part = $name;

    $sql = "SELECT COUNT(*) FROM `civicrm_data_processor` WHERE `name` = %1";
    $sqlParams[1] = array($name, 'String');
    if ($id) {
      $sql .= " AND `id` != %2";
      $sqlParams[2] = array($id, 'Integer');
    }

    $i = 1;
    while(CRM_Core_DAO::singleValueQuery($sql, $sqlParams) > 0) {
      $i++;
      $name = $name_part .'_'.$i;
      $sqlParams[1] = array($name, 'String');
    }
    return $name;
  }

  /**
   * Returns whether the name is valid or not
   *
   * @param string $name
   * @param int $id optional
   * @return bool
   * @static
   */
  public static function isNameValid($name, $id=null) {
    $sql = "SELECT COUNT(*) FROM `civicrm_data_processor` WHERE `name` = %1";
    $params[1] = array($name, 'String');
    if ($id) {
      $sql .= " AND `id` != %2";
      $params[2] = array($id, 'Integer');
    }
    $count = CRM_Core_DAO::singleValueQuery($sql, $params);
    return ($count > 0) ? false : true;
  }

  /**
   * Returns a configured data processor instance.
   *
   * @param array $dataProcessor
   * @param bool $force
   *   If set reload the data processor in the cache.
   * @return \Civi\DataProcessor\ProcessorType\AbstractProcessorType
   * @throws \Exception
   */
  public static function dataProcessorToClass($dataProcessor, $force=false) {
    $cache_key = 'dataprocessor_'.$dataProcessor['id'];
    $cache = CRM_Dataprocessor_Utils_Cache::singleton();
    if (!$force && $dataProcessorClass = $cache->get($cache_key)) {
      // Reset the default filter values as they might have been changed.
      $dataProcessorClass->loadedFromCache();
      return $dataProcessorClass;
    }
    $factory = dataprocessor_get_factory();
    $dataProcessorClass = $factory->getDataProcessorTypeByName($dataProcessor['type']);
    $sources = civicrm_api3('DataProcessorSource', 'get', array('data_processor_id' => $dataProcessor['id'], 'options' => array('limit' => 0)));
    foreach($sources['values'] as $sourceDao) {
      try {
        CRM_Dataprocessor_BAO_DataProcessorSource::addSourceToDataProcessor($sourceDao, $dataProcessorClass);
      } catch (\Exception $e) {
        CRM_Core_Session::setStatus($e->getMessage(), E::ts("Could not add data source"), 'error');
      }
    }

    $filters = civicrm_api3('DataProcessorFilter', 'get', array('data_processor_id' => $dataProcessor['id'], 'options' => array('limit' => 0)));
    foreach($filters['values'] as $filter) {
      $filterHandler = $factory->getFilterByName($filter['type']);
      if ($filterHandler) {
        $filterHandler->setDataProcessor($dataProcessorClass);
        try {
          $filterHandler->initialize($filter);
          $dataProcessorClass->addFilterHandler($filterHandler);
        } catch (\Exception $e) {
          CRM_Core_Session::setStatus($e->getMessage(), E::ts("Invalid filter"), 'error');
        }
      }
    }

    $fields = civicrm_api3('DataProcessorField', 'get', array('data_processor_id' => $dataProcessor['id'], 'options' => array('limit' => 0)));
    foreach($fields['values'] as $field) {
      $outputHandler = $factory->getOutputHandlerByName($field['type']);
      if ($outputHandler) {
        $outputHandler->setDataProcessor($dataProcessorClass);
        try {
          $outputHandler->initialize($field['name'], $field['title'], $field['configuration']);
          $dataProcessorClass->addOutputFieldHandlers($outputHandler);
        } catch (\Exception $e) {
          CRM_Core_Session::setStatus($e->getMessage(), E::ts("Invalid field"), 'error');
        }
      }
    }

    if (isset($dataProcessor['configuration']['default_sort'])) {
      foreach($dataProcessor['configuration']['default_sort'] as $sort) {
        if (stripos($sort, 'asc_by_') === 0) {
          $field = substr($sort, 7);
          $dataProcessorClass->getDataFlow()->addSort($field, 'ASC');
        } elseif (stripos($sort, 'desc_by_') === 0) {
          $field = substr($sort, 8);
          $dataProcessorClass->getDataFlow()->addSort($field, 'DESC');
        }
      }
    }

    $cache->set($cache_key, $dataProcessorClass);
    return $dataProcessorClass;
  }

  /**
   * Revert a data processor to the state in code.
   */
  public static function revert($data_processor_id) {
    $dao = \CRM_Core_DAO::executeQuery("SELECT status, source_file FROM civicrm_data_processor WHERE id = %1", array(1=>array($data_processor_id, 'Integer')));
    if (!$dao->fetch()) {
      return false;
    }
    if ($dao->status != CRM_Dataprocessor_Status::STATUS_OVERRIDDEN) {
      return false;
    }
    $key = substr($dao->source_file, 0, stripos($dao->source_file, "/"));
    $extension = civicrm_api3('Extension', 'getsingle', array('key' => $key));
    $filename = $extension['path'].substr($dao->source_file, stripos($dao->source_file, "/"));
    $data = file_get_contents($filename);
    $data = json_decode($data, true);

    CRM_Dataprocessor_Utils_Importer::importDataProcessor($data, $dao->source_file, $data_processor_id, CRM_Dataprocessor_Status::STATUS_IN_CODE);
    return true;
  }

  /**
   * Update the status from in code to overriden when a data processor has been changed
   *
   * @param $dataProcessorId
   */
  public static function updateAndChekStatus($dataProcessorId) {
    $sql = "SELECT `status`, `name` FROM `civicrm_data_processor` WHERE `id` = %1";
    $params[1] = array($dataProcessorId, 'Integer');
    $dao = CRM_Core_DAO::executeQuery($sql, $params);
    if ($dao->fetch()) {
      if (!in_array($dao->name, self::$importingDataProcessors) && $dao->status == CRM_Dataprocessor_Status::STATUS_IN_CODE) {
        $sql = "UPDATE `civicrm_data_processor` SET `status` = %2 WHERE `id` = %1";
        $params[1] = array($dataProcessorId, 'String');
        $params[2] = array(CRM_Dataprocessor_Status::STATUS_OVERRIDDEN, 'Integer');
        CRM_Core_DAO::executeQuery($sql, $params);
      }
    }
  }

  /**
   * Store the data processor name so we know that we are importing this data processor
   * and should not update its status on the way.
   *
   * @param $dataProcessorName
   */
  public static function setDataProcessorToImportingState($dataProcessorName) {
    self::$importingDataProcessors[] = $dataProcessorName;
  }

  /**
   * Delete function so that the hook for deleting an output gets invoked.
   *
   * @param $id
   */
  public static function del($id) {
    CRM_Utils_Hook::pre('delete', 'DataProcessor', $id, CRM_Core_DAO::$_nullArray);

    $dao = new CRM_Dataprocessor_BAO_DataProcessor();
    $dao->id = $id;
    if ($dao->find(true)) {
      $dao->delete();
    }

    CRM_Utils_Hook::post('delete', 'DataProcessor', $id, CRM_Core_DAO::$_nullArray);
  }


}
