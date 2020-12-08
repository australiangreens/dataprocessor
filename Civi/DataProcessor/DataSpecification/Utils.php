<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

namespace Civi\DataProcessor\DataSpecification;

use Civi\DataProcessor\Config\ConfigContainer;

class Utils {

  /**
   * Add fields from a DAO class to a data specification object
   *
   * @param $daoClass
   * @param \Civi\DataProcessor\DataSpecification\DataSpecification $dataSpecification
   * @param array $fieldsToSkip
   * @param string $namePrefix
   * @param string $aliasPrefix
   * @param string $titlePrefix
   *
   * @throws \Civi\DataProcessor\DataSpecification\FieldExistsException
   */
  public static function addDAOFieldsToDataSpecification($daoClass, DataSpecification $dataSpecification, $fieldsToSkip=array(), $namePrefix='', $aliasPrefix='', $titlePrefix='') {
    $fields = $daoClass::fields();
    foreach($fields as $field) {
      if (in_array($field['name'], $fieldsToSkip)) {
        continue;
      }

      $type = \CRM_Utils_Type::typeToString($field['type']);
      $options = $daoClass::buildOptions($field['name']);
      $alias = $aliasPrefix.$field['name'];
      $name = $namePrefix.$field['name'];
      $title = $titlePrefix.$field['title'];
      $fieldSpec = new FieldSpecification($name, $type, $title, $options, $alias);
      $dataSpecification->addFieldSpecification($fieldSpec->name, $fieldSpec);
    }
  }

  /**
   * Add custom fields to a data specification object
   *
   * @param $entity
   * @param DataSpecification $dataSpecification
   * @param bool $onlySearchAbleFields
   * @param $aliasPrefix
   * @param $titlePrefix
   * @throws \Civi\DataProcessor\DataSpecification\FieldExistsException
   * @throws \Exception
   */
  public static function addCustomFieldsToDataSpecification($entity, DataSpecification $dataSpecification, $onlySearchAbleFields, $aliasPrefix = '') {
    $customGroups = ConfigContainer::getInstance()->getCustomGroupsForEntity($entity);
    foreach ($customGroups as $customGroup) {
      if ($customGroup['is_active']) {
        $customFields = ConfigContainer::getInstance()->getCustomFieldsOfCustomGroup($customGroup['id']);
        foreach ($customFields as $field) {
          if (!empty($field['is_active']) && (!$onlySearchAbleFields || (isset($field['is_searchable']) && $field['is_searchable']))) {
            $alias = $aliasPrefix . $customGroup['name'] . '_' . $field['name'];
            $customFieldSpec = new CustomFieldSpecification(
              $customGroup['name'], $customGroup['table_name'], $customGroup['title'],
              $field,
              $alias
            );
            try {
              $dataSpecification->addFieldSpecification($customFieldSpec->name, $customFieldSpec);
            } catch (FieldExistsException $ex) {
              // Do nothing.
            }
          }
        }
      }
    }
  }

}
