<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

namespace Civi\DataProcessor\DataSpecification;

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
    $customGroups = self::getTree($entity);//, $customGroupToReturnParam, , NULL, NULL, NULL, TRUE, FALSE, FALSE);
    foreach ($customGroups as $cgId => $customGroup) {
      if ($cgId == 'info') {
        continue;
      }
      foreach ($customGroup['fields'] as $field) {
        if (!$onlySearchAbleFields || (isset($field['is_searchable']) && $field['is_searchable'])) {
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

  /**
   * Get custom groups/fields data for type of entity in a tree structure representing group->field hierarchy
   * This may also include entity specific data values.
   *
   * An array containing all custom groups and their custom fields is returned.
   *
   * @param string $entityType
   *   Of the contact whose contact type is needed.
   * @param array $toReturn
   *   What data should be returned. ['custom_group' => ['id', 'name', etc.], 'custom_field' => ['id', 'label', etc.]]
   * @param int $entityID
   * @param int $groupID
   * @param array $subTypes
   * @param string $subName
   * @param bool $fromCache
   * @param bool $onlySubType
   *   Only return specified subtype or return specified subtype + unrestricted fields.
   * @param bool $returnAll
   *   Do not restrict by subtype at all. (The parameter feels a bit cludgey but is only used from the
   *   api - through which it is properly tested - so can be refactored with some comfort.)
   *
   * @param bool $checkPermission
   * @param string|int $singleRecord
   *   holds 'new' or id if view/edit/copy form for a single record is being loaded.
   * @param bool $showPublicOnly
   *
   * @return array
   *   Custom field 'tree'.
   *
   *   The returned array is keyed by group id and has the custom group table fields
   *   and a subkey 'fields' holding the specific custom fields.
   *   If entityId is passed in the fields keys have a subkey 'customValue' which holds custom data
   *   if set for the given entity. This is structured as an array of values with each one having the keys 'id', 'data'
   *
   * @todo - review this  - It also returns an array called 'info' with tables, select, from, where keys
   *   The reason for the info array in unclear and it could be determined from parsing the group tree after creation
   *   With caching the performance impact would be small & the function would be cleaner
   *
   * @throws \CRM_Core_Exception
   */
  protected static function getTree($entityType) {
    // legacy hardcoded list of data to return
    $toReturn = [
      'custom_field' => [
        'id',
        'name',
        'label',
        'column_name',
        'data_type',
        'html_type',
        'default_value',
        'attributes',
        'is_searchable',
        'is_required',
        'is_view',
        'help_pre',
        'help_post',
        'options_per_line',
        'start_date_years',
        'end_date_years',
        'date_format',
        'time_format',
        'option_group_id',
        'in_selector'
      ],
      'custom_group' => [
        'id',
        'name',
        'table_name',
        'title',
        'help_pre',
        'help_post',
        'collapse_display',
        'style',
        'is_multiple',
        'extends',
        'extends_entity_column_id',
        'extends_entity_column_value',
        'max_multiple',
      ],
    ];
    $civiVersion = \CRM_Core_BAO_Domain::version();
    if (version_compare($civiVersion, '5.27', '>=')) {
      $toReturn['custom_field'][] = 'serialize';
    }

    // create select
    $select = [];
    foreach ($toReturn as $tableName => $tableColumn) {
      foreach ($tableColumn as $columnName) {
        $select[] = "civicrm_{$tableName}.{$columnName} as civicrm_{$tableName}_{$columnName}";
      }
    }
    $strSelect = "SELECT " . implode(', ', $select);

    // from, where, order by
    $strFrom = "
FROM     civicrm_custom_group
LEFT JOIN civicrm_custom_field ON (civicrm_custom_field.custom_group_id = civicrm_custom_group.id)
";

    // if entity is either individual, organization or household pls get custom groups for 'contact' too.
    if ($entityType == "Individual" || $entityType == 'Organization' || $entityType == 'Household') {
      $in = "'$entityType', 'Contact'";
    }
    elseif (strpos($entityType, "'") !== FALSE) {
      // this allows the calling function to send in multiple entity types
      $in = $entityType;
    }
    else {
      // quote it
      $in = "'$entityType'";
    }
    $params = [];
    $strWhere = "WHERE civicrm_custom_group.is_active = 1 AND civicrm_custom_field.is_active = 1 AND civicrm_custom_group.extends IN ($in) AND civicrm_custom_group.is_multiple = 0";
    $orderBy = "ORDER BY civicrm_custom_group.weight, civicrm_custom_group.title, civicrm_custom_field.weight, civicrm_custom_field.label";
    // final query string
    $queryString = "$strSelect $strFrom $strWhere $orderBy";

    // lets see if we can retrieve the groupTree from cache
    $cacheString = $queryString."_DataProcessor";

    $cacheKey = "CRM_Core_DAO_CustomGroup_Query " . md5($cacheString);
    $multipleFieldGroupCacheKey = "CRM_Core_DAO_CustomGroup_QueryMultipleFields " . md5($cacheString);
    $cache = \CRM_Utils_Cache::singleton();
    $groupTree = $cache->get($cacheKey);
    $multipleFieldGroups = $cache->get($multipleFieldGroupCacheKey);

    if (empty($groupTree)) {
      $groupTree = $multipleFieldGroups = [];
      $crmDAO = \CRM_Core_DAO::executeQuery($queryString, $params);
      $customValueTables = [];

      // process records
      while ($crmDAO->fetch()) {
        // get the id's
        $groupID = $crmDAO->civicrm_custom_group_id;
        $fieldId = $crmDAO->civicrm_custom_field_id;
        if ($crmDAO->civicrm_custom_group_is_multiple) {
          $multipleFieldGroups[$groupID] = $crmDAO->civicrm_custom_group_table_name;
        }
        // create an array for groups if it does not exist
        if (!array_key_exists($groupID, $groupTree)) {
          $groupTree[$groupID] = [];
          $groupTree[$groupID]['id'] = $groupID;

          // populate the group information
          foreach ($toReturn['custom_group'] as $fieldName) {
            $fullFieldName = "civicrm_custom_group_$fieldName";
            if ($fieldName == 'id' ||
              is_null($crmDAO->$fullFieldName)
            ) {
              continue;
            }
            $groupTree[$groupID][$fieldName] = $crmDAO->$fullFieldName;
          }
          $groupTree[$groupID]['fields'] = [];

          $customValueTables[$crmDAO->civicrm_custom_group_table_name] = [];
        }

        // add the fields now (note - the query row will always contain a field)
        // we only reset this once, since multiple values come is as multiple rows
        if (!array_key_exists($fieldId, $groupTree[$groupID]['fields'])) {
          $groupTree[$groupID]['fields'][$fieldId] = [];
        }

        $customValueTables[$crmDAO->civicrm_custom_group_table_name][$crmDAO->civicrm_custom_field_column_name] = 1;
        $groupTree[$groupID]['fields'][$fieldId]['id'] = $fieldId;
        // populate information for a custom field
        foreach ($toReturn['custom_field'] as $fieldName) {
          $fullFieldName = "civicrm_custom_field_$fieldName";
          if ($fieldName == 'id' ||
            is_null($crmDAO->$fullFieldName)
          ) {
            continue;
          }
          $groupTree[$groupID]['fields'][$fieldId][$fieldName] = $crmDAO->$fullFieldName;
        }
      }

      if (!empty($customValueTables)) {
        $groupTree['info'] = ['tables' => $customValueTables];
      }

      $cache->set($cacheKey, $groupTree);
      $cache->set($multipleFieldGroupCacheKey, $multipleFieldGroups);
    }
    // entitySelectClauses is an array of select clauses for custom value tables which are not multiple
    // and have data for the given entities. $entityMultipleSelectClauses is the same for ones with multiple
    $entitySingleSelectClauses = $entityMultipleSelectClauses = $groupTree['info']['select'] = [];
    $singleFieldTables = [];
    // now that we have all the groups and fields, lets get the values
    // since we need to know the table and field names
    // add info to groupTree

    if (isset($groupTree['info']) && !empty($groupTree['info']) && !empty($groupTree['info']['tables'])) {
      $select = $from = $where = [];
      $groupTree['info']['where'] = NULL;

      foreach ($groupTree['info']['tables'] as $table => $fields) {
        $groupTree['info']['from'][] = $table;
        $select = [
          "{$table}.id as {$table}_id",
          "{$table}.entity_id as {$table}_entity_id",
        ];
        foreach ($fields as $column => $dontCare) {
          $select[] = "{$table}.{$column} as {$table}_{$column}";
        }
        $groupTree['info']['select'] = array_merge($groupTree['info']['select'], $select);
      }
      $multipleFieldTablesWithEntityData = array_keys($entityMultipleSelectClauses);
      if (!empty($multipleFieldTablesWithEntityData)) {
        \CRM_Core_BAO_CustomGroup::buildEntityTreeMultipleFields($groupTree, null, $entityMultipleSelectClauses, $multipleFieldTablesWithEntityData, $singleRecord);
      }
    }
    return $groupTree;
  }

}
