<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

use Civi\DataProcessor\Source\SourceInterface;

class CRM_Dataprocessor_Utils_DataSourceFields {

  /**
   * Get the value for the selected field.
   *
   * @param $dataProcessorId
   * @param $dataSourceName
   * @param $fieldName
   *
   * @return string|null
   * @throws \CiviCRM_API3_Exception
   */
  public static function getSelectedFieldValue($dataProcessorId, $dataSourceName, $fieldName) {
    $dataProcessor = civicrm_api3('DataProcessor', 'getsingle', array('id' => $dataProcessorId));
    $dataProcessorClass = \CRM_Dataprocessor_BAO_DataProcessor::dataProcessorToClass($dataProcessor);
    $dataSource = $dataProcessorClass->getDataSourceByName($dataSourceName);
    if ($dataSource) {
      $inputFieldSpec = $dataSource->getAvailableFields()
        ->getFieldSpecificationByAlias($fieldName);
      if (!$inputFieldSpec) {
        $inputFieldSpec = $dataSource->getAvailableFields()
          ->getFieldSpecificationByName($fieldName);
      }
      if ($inputFieldSpec) {
        return $dataSourceName.'::'.$inputFieldSpec->alias;
      }
    }
    return null;
  }

  /**
   * Returns an array with the name of the field as the key and the label of the field as the value.
   *
   * @oaram int $dataProcessorId
   * @param callable $callback
   *   Function to filter certain fields.
   * @return array
   * @throws \Exception
   */
  public static function getAvailableFieldsInDataSources($dataProcessorId, $callback=null) {
    $dataProcessor = civicrm_api3('DataProcessor', 'getsingle', array('id' => $dataProcessorId));
    $dataProcessorClass = \CRM_Dataprocessor_BAO_DataProcessor::dataProcessorToClass($dataProcessor);
    $fieldSelect = array();
    foreach($dataProcessorClass->getDataSources() as $dataSource) {
      $fieldSelect = array_merge($fieldSelect, self::getAvailableFieldsInDataSource($dataSource, $dataSource->getSourceTitle().' :: ', $dataSource->getSourceName().'::', $callback));
    }
    return $fieldSelect;
  }

  /**
   * Returns an array with the name of the field as the key and the label of the field as the value.
   *
   * @oaram SourceInterface $dataSource
   * @param $titlePrefix
   * @param $namePrefix
   * @param callable $callback
   *   Function to filter certain fields.
   * @return array
   * @throws \Exception
   */
  public static function getAvailableFieldsInDataSource(SourceInterface $dataSource, $titlePrefix='', $namePrefix='', $callback=null) {
    $fieldSelect = array();
    foreach($dataSource->getAvailableFields()->getFields() as $fieldName => $field) {
      $isFieldValid = true;
      if ($callback) {
        $isFieldValid = call_user_func($callback, $field);
      }
      if ($isFieldValid) {
        $fieldSelect[$namePrefix . $field->alias] = $titlePrefix . $field->title;
      }
    }
    return $fieldSelect;
  }

  /**
   * Returns an array with the name of the field as the key and the label of the field as the value.
   *
   * @oaram int $dataProcessorId
   * @param callable $filterFieldsCallback
   *   Function to filter certain fields.
   * @return array
   * @throws \Exception
   */
  public static function getAvailableFilterFieldsInDataSources($dataProcessorId, $filterFieldsCallback=null) {
    $dataProcessor = civicrm_api3('DataProcessor', 'getsingle', array('id' => $dataProcessorId));
    $dataProcessorClass = \CRM_Dataprocessor_BAO_DataProcessor::dataProcessorToClass($dataProcessor);
    $fieldSelect = array();
    foreach($dataProcessorClass->getDataSources() as $dataSource) {
      $fieldSelect = array_merge($fieldSelect, self::getAvailableFilterFieldsInDataSource($dataSource, $dataSource->getSourceTitle() . ' :: ', $dataSource->getSourceName() . '::', $filterFieldsCallback));
    }
    return $fieldSelect;
  }

  /**
   * Returns an array with the name of the field as the key and the label of the field as the value.
   *
   * @oaram SourceInterface $dataSource
   * @param $titlePrefix
   * @param $namePrefix
   * @param callable $callback
   *   Function to filter certain fields.
   * @return array
   * @throws \Exception
   */
  public static function getAvailableFilterFieldsInDataSource(SourceInterface $dataSource, $titlePrefix='', $namePrefix='', $filterFieldsCallback=null) {
    $fieldSelect = array();
    foreach($dataSource->getAvailableFilterFields()->getFields() as $field) {
      $isFieldValid = true;
      if ($filterFieldsCallback) {
        $isFieldValid = call_user_func($filterFieldsCallback, $field, $dataSource);
      }
      if ($isFieldValid) {
        $fieldSelect[$namePrefix . $field->alias] = $titlePrefix . $field->title;
      }
    }
    return $fieldSelect;
  }

}
