<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

class CRM_Dataprocessor_Utils_Importer {

  /**
   * Exports a data processor
   *
   * Returns the array with the whole configuration.
   *
   * @param $data_processor_id
   * @return array
   * @throws \Exception
   */
  public static function export($data_processor_id) {
    $factory = dataprocessor_get_factory();

    $dataProcessor = civicrm_api3('DataProcessor', 'getsingle', array('id' => $data_processor_id));
    unset($dataProcessor['id']);
    unset($dataProcessor['status']);
    unset($dataProcessor['source_file']);

    $sources = civicrm_api3('DataProcessorSource', 'get', array('data_processor_id' => $data_processor_id, 'options' => array('limit' => 0)));
    $dataProcessor['data_sources'] = array();
    foreach($sources['values'] as $i => $datasource) {
      unset($datasource['id']);
      unset($datasource['data_processor_id']);
      $sourceClass = $factory->getDataSourceByName($datasource['type']);
      if ($sourceClass instanceof \Civi\DataProcessor\Utils\AlterExportInterface) {
        $datasource = $sourceClass->alterExportData($datasource);
      }
      $dataProcessor['data_sources'][] = $datasource;
    }
    $filters = civicrm_api3('DataProcessorFilter', 'get', array('data_processor_id' => $data_processor_id, 'options' => array('limit' => 0)));
    $dataProcessor['filters']  = array();
    foreach($filters['values'] as $i => $filter) {
      unset($filter['id']);
      unset($filter['data_processor_id']);
      $filterClass = $factory->getFilterByName($filter['type']);
      if ($filterClass instanceof \Civi\DataProcessor\Utils\AlterExportInterface) {
        $filter = $filterClass->alterExportData($filter);
      }
      $dataProcessor['filters'][] = $filter;
    }
    $fields = civicrm_api3('DataProcessorField', 'get', array('data_processor_id' => $data_processor_id, 'options' => array('limit' => 0)));
    $dataProcessor['fields'] = array();
    foreach($fields['values'] as $i => $field) {
      unset($field['id']);
      unset($field['data_processor_id']);
      $fieldClass = $factory->getOutputHandlerByName($field['type']);
      if ($fieldClass instanceof \Civi\DataProcessor\Utils\AlterExportInterface) {
        $field = $fieldClass->alterExportData($field);
      }
      $dataProcessor['fields'][] = $field;
    }
    $outputs = $outputs = civicrm_api3('DataProcessorOutput', 'get', array('data_processor_id' => $data_processor_id, 'options' => array('limit' => 0)));
    $dataProcessor['outputs'] = array();
    foreach($outputs['values'] as $i => $output) {
      unset($output['id']);
      unset($output['data_processor_id']);
      $outputClass = $factory->getOutputByName($output['type']);
      if ($outputClass instanceof \Civi\DataProcessor\Utils\AlterExportInterface) {
        $output = $outputClass->alterExportData($output);
      }
      $dataProcessor['outputs'][] = $output;
    }

    $eventData['data_processor'] = &$dataProcessor;
    $event = \Civi\Core\Event\GenericHookEvent::create($eventData);
    \Civi::dispatcher()->dispatch('hook_civicrm_dataprocessor_export', $event);

    return $dataProcessor;
  }

  public static function import($data, $filename, $overWriteInDatabase=false) {
    $new_status = null;
    $new_id = null;
    $data_processor_id = null;
    $status = null;
    try {
      $dataProcessor = civicrm_api3('DataProcessor', 'getsingle', ['name' => $data['name']]);
      $data_processor_id = $dataProcessor['id'];
      $status = $dataProcessor['status'] ? $dataProcessor['status'] : CRM_Dataprocessor_Status::STATUS_IN_DATABASE;
    } catch (Exception $e) {
      // Do nothing
    }

    CRM_Dataprocessor_BAO_DataProcessor::setDataProcessorToImportingState($data['name']);
    try {
      switch ($status) {
        case CRM_Dataprocessor_Status::STATUS_IN_DATABASE:
          // Update to overriden
          if (!$overWriteInDatabase) {
            civicrm_api3('DataProcessor', 'create', [
              'id' => $data_processor_id,
              'status' => CRM_Dataprocessor_Status::STATUS_OVERRIDDEN,
              'source_file' => $filename,
            ]);
            $new_id = $data_processor_id;
            $new_status = CRM_Dataprocessor_Status::STATUS_OVERRIDDEN;
          }
          else {
            $new_id = self::importDataProcessor($data, $filename, $data_processor_id, CRM_Dataprocessor_Status::STATUS_IN_DATABASE);
            $new_status = CRM_Dataprocessor_Status::STATUS_IN_DATABASE;
          }
          break;
        case CRM_Dataprocessor_Status::STATUS_OVERRIDDEN:
          if (!$overWriteInDatabase) {
            $new_id = $data_processor_id;
            $new_status = CRM_Dataprocessor_Status::STATUS_OVERRIDDEN;
          }
          else {
            $new_id = self::importDataProcessor($data, $filename, $data_processor_id, CRM_Dataprocessor_Status::STATUS_OVERRIDDEN);
            $new_status = CRM_Dataprocessor_Status::STATUS_OVERRIDDEN;
          }
          break;
        default:
          if (!$overWriteInDatabase) {
            $new_id = self::importDataProcessor($data, $filename, $data_processor_id, CRM_Dataprocessor_Status::STATUS_IN_CODE);
            $new_status = CRM_Dataprocessor_Status::STATUS_IN_CODE;
          }
          elseif ($filename) {
            $new_id = self::importDataProcessor($data, $filename, $data_processor_id, CRM_Dataprocessor_Status::STATUS_OVERRIDDEN);
            $new_status = CRM_Dataprocessor_Status::STATUS_OVERRIDDEN;
          } else {
            $new_id = self::importDataProcessor($data, $filename, $data_processor_id, CRM_Dataprocessor_Status::STATUS_IN_DATABASE);
            $new_status = CRM_Dataprocessor_Status::STATUS_IN_DATABASE;
          }
          break;
      }
    } catch (\Exception $e) {
      $return = array(
        'original_id' => $data_processor_id,
        'new_id' => $new_id,
        'original_status' => $status,
        'new_status' => $new_status,
        'file' => $filename,
        'error' => $e->getMessage(),
      );

      return $return;
    }

    $return = array(
      'original_id' => $data_processor_id,
      'new_id' => $new_id,
      'original_status' => $status,
      'new_status' => $new_status,
      'file' => $filename,
    );

    return $return;
  }

  /**
   * Import a data processor
   *
   * @param $data
   * @param string|null $filename
   * @param int|null $data_processor_id
   * @param int
   *
   * @return mixed
   * @throws \Exception
   */
  public static function importDataProcessor($data, $filename, $data_processor_id, $status) {
    $factory = dataprocessor_get_factory();
    $params = $data;
    unset($params['data_sources']);
    unset($params['outputs']);
    unset($params['fields']);
    unset($params['filters']);
    if ($data_processor_id) {
      $params['id'] = $data_processor_id;
    }
    if (!isset($params['configuration'])) {
      $params['configuration'] = array();
    }
    if (!isset($params['storage_configuration'])) {
      $params['storage_configuration'] = array();
    }
    $params['status'] = $status;
    $params['source_file'] = $filename ? $filename : null;
    $result = civicrm_api3('DataProcessor', 'create', $params);
    $id = $result['id'];

    // Clear all existing data sources and outputs
    CRM_Dataprocessor_BAO_DataProcessorSource::deleteWithDataProcessorId($id);
    CRM_Dataprocessor_BAO_DataProcessorFilter::deleteWithDataProcessorId($id);
    CRM_Dataprocessor_BAO_DataProcessorField::deleteWithDataProcessorId($id);
    CRM_Dataprocessor_BAO_DataProcessorOutput::deleteWithDataProcessorId($id);

    foreach($data['data_sources'] as $data_source) {
      $sourceClass = $factory->getDataSourceByName($data_source['type']);
      if ($sourceClass instanceof \Civi\DataProcessor\Utils\AlterExportInterface) {
        $data_source = $sourceClass->alterImportData($data_source);
      }
      $params = $data_source;
      $params['data_processor_id'] = $id;
      $params['debug'] = 1;
      try {
        civicrm_api3('DataProcessorSource', 'create', $params);
      } catch (\CiviCRM_API3_Exception $e) {
        echo $e->getTraceAsString(); exit();
      }
    }
    foreach($data['filters'] as $filter) {
      $filterClass = $factory->getFilterByName($filter['type']);
      if ($filterClass instanceof \Civi\DataProcessor\Utils\AlterExportInterface) {
        $filter = $filterClass->alterImportData($filter);
      }
      $params = $filter;
      $params['data_processor_id'] = $id;
      civicrm_api3('DataProcessorFilter', 'create', $params);
    }
    foreach($data['fields'] as $field) {
      $fieldClass = $factory->getOutputHandlerByName($field['type']);
      if ($fieldClass instanceof \Civi\DataProcessor\Utils\AlterExportInterface) {
        $field = $fieldClass->alterImportData($field);
      }
      $params = $field;
      $params['data_processor_id'] = $id;
      civicrm_api3('DataProcessorField', 'create', $params);
    }
    foreach($data['outputs'] as $output) {
      $outputClass = $factory->getOutputByName($output['type']);
      if ($outputClass instanceof \Civi\DataProcessor\Utils\AlterExportInterface) {
        $output = $outputClass->alterImportData($output);
      }
      $params = $output;
      $params['data_processor_id'] = $id;
      civicrm_api3('DataProcessorOutput', 'create', $params);
    }

    return $id;
  }


  /**
   * Imports data processor from files in an extension directory.
   *
   * This scans the extension directory data-processors/ for json files.
   */
  public static function importFromExtensions($extension=null) {
    $return = array();
    $importedIds = array();
    $extensions = self::getExtensionFileListWithDataProcessors($extension);
    \Civi\DataProcessor\Output\UIOutputHelper::disableRebuildingOfMenuAndNavigation();
    foreach($extensions as $ext_file) {
      $data = json_decode($ext_file['data'], true);
      $return[$ext_file['file']] = self::import($data, $ext_file['file']);
      if ($return[$ext_file['file']]['new_id']) {
        $importedIds[] = $return[$ext_file['file']]['new_id'];
      }
    }

    // Remove all data processors which are in code or overridden but not imported
    $removeSql = "
        SELECT id, name, status
        FROM civicrm_data_processor
        WHERE  status IN (".CRM_Dataprocessor_Status::STATUS_IN_CODE.", ".CRM_Dataprocessor_Status::STATUS_OVERRIDDEN.")
        AND source_file IS NOT NULL";
    if (count($importedIds)) {
      $removeSql .= " AND id NOT IN (".implode($importedIds, ",").")";
    }
    if ($extension) {
      $removeSql .= " AND source_file LIKE '".CRM_Utils_Type::escape($extension, 'String')."/data-processors/%'";
    }
    $dao = CRM_Core_DAO::executeQuery($removeSql);
    while ($dao->fetch()) {
      try {
        if ($dao->status == CRM_Dataprocessor_Status::STATUS_OVERRIDDEN) {
          civicrm_api3('DataProcessor', 'create', array('id' => $dao->id, 'status' => CRM_Dataprocessor_Status::STATUS_IN_DATABASE, 'source_file' => 'null'));
          $return['kept data processors'][] = $dao->id.": ".$dao->name;
        } else {
          civicrm_api3('DataProcessor', 'delete', ['id' => $dao->id]);
          $return['deleted data processors'][] = $dao->id . ": " . $dao->name;
        }
      } catch (\Exception $e) {
        $return['deleted data processors'][] = 'Error: '. $dao->id.": ".$dao->name;
      }
    }
    \Civi\DataProcessor\Output\UIOutputHelper::rebuildMenuAndNavigation();
    return $return;
  }

  /**
   * Returns a list with data-processor files within an extension folder.
   *
   * @return array
   */
  private static function getExtensionFileListWithDataProcessors($extension=null) {
    $return = array();
    $extensions = civicrm_api3('Extension', 'get', array('options' => array('limit' => 0)));
    foreach($extensions['values'] as $ext) {
      if ($ext['status'] != 'installed') {
        continue;
      }
      if ($extension && $extension != $ext['key']) {
        continue;
      }

      $path = $ext['path'].'/data-processors';
      if (!is_dir($path)) {
        continue;
      }

      foreach (glob($path."/*.json") as $file) {
        $return[] = array(
          'file' => $ext['key']. '/data-processors/'.basename($file),
          'data' => file_get_contents($file),
        );
      }
    }
    return $return;
  }

}
