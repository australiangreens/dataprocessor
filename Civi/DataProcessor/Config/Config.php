<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

namespace Civi\DataProcessor\Config;

use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class Config extends Container {

  /**
   * Returns with custom fields of a certain group.
   *
   * @param $custom_group_id
   * @return array
   */
  public function getCustomFieldsOfCustomGroup($custom_group_id) {
    $customFieldsPerGroup = $this->getParameter('custom_fields_per_group');
    return $customFieldsPerGroup[$custom_group_id];
  }

  /**
   * Returns an array of custom groups for an entity.
   *
   * @param $entity
   * @return array
   */
  public function getCustomGroupsForEntity($entity) {
    $customGroupExtends = $this->getParameter('custom_groups_per_extends');
    $customGroups = isset($customGroupExtends[$entity]) ? $customGroupExtends[$entity] : [];
    switch($entity) {
      case 'Individual':
      case 'Household':
      case 'Organization':
        $customGroups = array_merge($customGroups, isset($customGroupExtends['Contact']) ? $customGroupExtends['Contact'] : []);
        break;
    }
    return $customGroups;
  }

  public static function buildConfigContainer(ContainerBuilder $containerBuilder) {
    $customGroupPerExtends = array();
    $customGroups = array();
    $customFields = array();
    $customFieldsPerGroup = array();
    $customGroupApi = civicrm_api3('CustomGroup', 'get', ['options' => ['limit' => 0]]);
    foreach($customGroupApi['values'] as $customGroup) {
      $customGroups[$customGroup['id']] = $customGroup;
      $customGroupPerExtends[$customGroup['extends']][] = $customGroup;
    }
    $customFieldsApi = civicrm_api3('CustomField', 'get', ['options' => ['limit' => 0]]);
    foreach($customFieldsApi['values'] as $customField) {
      $customFields[$customField['id']] = $customField;
      $customFieldsPerGroup[$customField['custom_group_id']][] = $customField;
    }

    $containerBuilder->setParameter('custom_groups', $customGroups);
    $containerBuilder->setParameter('custom_groups_per_extends', $customGroupPerExtends);
    $containerBuilder->setParameter('custom_fields_per_group', $customFieldsPerGroup);
    $containerBuilder->setParameter('custom_fields', $customFields);

  }

}
