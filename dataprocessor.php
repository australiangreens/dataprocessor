<?php

require_once 'dataprocessor.civix.php';
use CRM_Dataprocessor_ExtensionUtil as E;

use \Symfony\Component\DependencyInjection\ContainerBuilder;
use \Symfony\Component\DependencyInjection\Definition;

/**
 * @return \Civi\DataProcessor\Factory
 */
function dataprocessor_get_factory() {
  $container = \Civi::container();
  if ($container->has('data_processor_factory')) {
    return \Civi::service('data_processor_factory');
  }
  return null;
}

/**
 * Implements hook_civicrm_container()
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_container/
 */
function dataprocessor_civicrm_container(ContainerBuilder $container) {
  // Register the TypeFactory
  $container->setDefinition('data_processor_factory', new Definition('Civi\DataProcessor\Factory'));

  $apiKernelDefinition = $container->getDefinition('civi_api_kernel');
  $apiProviderDefinition = new Definition('Civi\DataProcessor\Output\Api');
  $apiKernelDefinition->addMethodCall('registerApiProvider', array($apiProviderDefinition));

  // Add the data source for custom groups with multiple set.
  // This will add a data source for each custom group.
  $container->addCompilerPass(new \Civi\DataProcessor\Source\CompilerPass\MultipleCustomGroupSource());

  // Add event listeners so we can integrate a data processor search with smart groups.
  // Insert event listener for altering saved search so we can save smart groups.
  $container->findDefinition('dispatcher')
    ->addMethodCall('addListener', array('civi.dao.postUpdate', ['CRM_DataprocessorSearch_Form_Search_Custom_DataprocessorSmartGroupIntegration', 'alterSavedSearch']))
    ->addMethodCall('addListener', array('civi.dao.postInsert', ['CRM_DataprocessorSearch_Form_Search_Custom_DataprocessorSmartGroupIntegration', 'alterSavedSearch']))
  ;
}

/**
 * Implements hook_civicrm_alterAPIPermissions()
 *
 * All Data Processor api outputs have their own permission.
 * Except for the FormProcessorExecutor api.
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_alterAPIPermissions/
 */
function dataprocessor_civicrm_alterAPIPermissions($entity, $action, &$params, &$permissions) {
  // We have to check the camelcase and the non camel case names
  $entityNonCamelCase = _civicrm_api_get_entity_name_from_camel($entity);
  $entityCamelCase = _civicrm_api_get_camel_name($entity);
  $api_action = $action;
  if ($action == 'getfields' && isset($params['api_action'])) {
    $api_action = $params['api_action'];
  }
  $actionNonCamelCase = _civicrm_api_get_entity_name_from_camel($api_action);
  $actionCamelCase = _civicrm_api_get_camel_name($api_action);
  $dao = CRM_Core_DAO::executeQuery("
    SELECT *
    FROM civicrm_data_processor_output o
    INNER JOIN civicrm_data_processor p ON o.data_processor_id = p.id
    WHERE p.is_active = 1
    AND (LOWER(api_entity) = LOWER(%1) OR LOWER(api_entity) = LOWER(%2))
    AND (
      LOWER(api_action) = LOWER(%3) OR LOWER(api_count_action) = LOWER(%3)
      OR LOWER(api_action) = LOWER(%4) OR LOWER(api_count_action) = LOWER(%4)
    )",
    array(
      1 => array($entityCamelCase, 'String'),
      2 => array($entityNonCamelCase, 'String'),
      3 => array($actionNonCamelCase, 'String'),
      4 => array($actionCamelCase, 'String'))
  );
  while ($dao->fetch()) {
    $permissions[$entity][$action] = array($dao->permission);
  }
}

/**
 * Implements hook_civicrm_alterMenu().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_alterMenu/
 */
function dataprocessor_civicrm_alterMenu(&$items) {
  // This hook is called BEFORE civicrm_entityTypes so DataProcessor API calls will fail with missing BAO in
  //   some cases (eg. running cv flush from cli)
  // So we manually run it here before calling alterMenu function
  $entityTypes = [];
  _dataprocessor_civix_civicrm_entityTypes($entityTypes);
  foreach ($entityTypes as $entityType) {
    CRM_Core_DAO_AllCoreTables::registerEntityType(
      $entityType['name'],
      $entityType['class'],
      $entityType['table'],
      $entityType['fields_callback'] ?? NULL,
      $entityType['links_callback'] ?? NULL
    );
  }
  \Civi\DataProcessor\Output\UIOutputHelper::alterMenu($items);
}

/**
 * Implements hook_civicrm_pre().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_pre/
 *
 * @param $op
 * @param $objectName
 * @param $objectId
 * @param $params
 */
function dataprocessor_civicrm_pre($op, $objectName, $objectId, &$params) {

}

/**
 * Implements hook_civicrm_post().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_post/
 *
 * @param $op
 * @param $objectName
 * @param $objectId
 * @param $objectRef
 */
function dataprocessor_civicrm_post($op, $objectName, $objectId, &$objectRef) {
  \Civi\DataProcessor\Output\UIOutputHelper::postHook($op, $objectName, $objectId, $objectRef);
  \Civi\DataProcessor\Config\ConfigContainer::postHook($op, $objectName, $objectId, $objectRef);
}

function dataprocessor_civicrm_dataprocessor_export(&$dataProcessor) {
  \Civi\DataProcessor\Output\UIOutputHelper::hookExport($dataProcessor);
}

function dataprocessor_search_action_designer_types(&$types) {
  CRM_DataprocessorSearch_Task::searchActionDesignerTypes($types);
}

/**
 * Implements hook_civicrm_tabset().
 *
 * Adds the data processor out to the contact summary tabs.
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_tabset/
 *
 * @param $tabsetName
 * @param $tabs
 * @param $context
 */
function dataprocessor_civicrm_tabset($tabsetName, &$tabs, $context) {
  CRM_Contact_DataProcessorContactSummaryTab::hookTabset($tabsetName, $tabs, $context);
}

/**
 * Implements hook_civicrm_config().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function dataprocessor_civicrm_config(&$config) {
  _dataprocessor_civix_civicrm_config($config);
  CRM_DataprocessorSearch_Form_Search_Custom_DataprocessorSmartGroupIntegration::redirectCustomSearchToDataProcessorSearch(CRM_Utils_System::currentPath());
}

/**
 * Implements hook_civicrm_xmlMenu().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_xmlMenu
 */
function dataprocessor_civicrm_xmlMenu(&$files) {
  _dataprocessor_civix_civicrm_xmlMenu($files);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function dataprocessor_civicrm_install() {
  _dataprocessor_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_postInstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_postInstall
 */
function dataprocessor_civicrm_postInstall() {
  _dataprocessor_civix_civicrm_postInstall();
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_uninstall
 */
function dataprocessor_civicrm_uninstall() {
  _dataprocessor_civix_civicrm_uninstall();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function dataprocessor_civicrm_enable() {
  _dataprocessor_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_disable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_disable
 */
function dataprocessor_civicrm_disable() {
  _dataprocessor_civix_civicrm_disable();
}

/**
 * Implements hook_civicrm_upgrade().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_upgrade
 */
function dataprocessor_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _dataprocessor_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implements hook_civicrm_managed().
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_managed
 */
function dataprocessor_civicrm_managed(&$entities) {
  _dataprocessor_civix_civicrm_managed($entities);
}

/**
 * Implements hook_civicrm_caseTypes().
 *
 * Generate a list of case-types.
 *
 * Note: This hook only runs in CiviCRM 4.4+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function dataprocessor_civicrm_caseTypes(&$caseTypes) {
  _dataprocessor_civix_civicrm_caseTypes($caseTypes);
}

/**
 * Implements hook_civicrm_angularModules().
 *
 * Generate a list of Angular modules.
 *
 * Note: This hook only runs in CiviCRM 4.5+. It may
 * use features only available in v4.6+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_angularModules
 */
function dataprocessor_civicrm_angularModules(&$angularModules) {
  _dataprocessor_civix_civicrm_angularModules($angularModules);
}

/**
 * Implements hook_civicrm_alterSettingsFolders().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_alterSettingsFolders
 */
function dataprocessor_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _dataprocessor_civix_civicrm_alterSettingsFolders($metaDataFolders);
}

/**
 * Implements hook_civicrm_entityTypes().
 *
 * Declare entity types provided by this module.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_entityTypes
 */
function dataprocessor_civicrm_entityTypes(&$entityTypes) {
  _dataprocessor_civix_civicrm_entityTypes($entityTypes);
}

/**
 * Implements hook_civicrm_navigationMenu().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_navigationMenu
 */
function dataprocessor_civicrm_navigationMenu(&$menu) {
  _dataprocessor_civix_insert_navigation_menu($menu, 'Administer', array(
    'label' => E::ts('Data Processor'),
    'name' => 'data_processor',
    'url' => '',
    'permission' => 'administer CiviCRM',
    'operator' => 'OR',
    'separator' => 0
  ));
  _dataprocessor_civix_insert_navigation_menu($menu, 'Administer/data_processor', array(
    'label' => E::ts('Manage Data Processors'),
    'name' => 'manage_data_processors',
    'url' => CRM_Utils_System::url('civicrm/dataprocessor/manage', 'reset=1', true),
    'permission' => 'administer CiviCRM',
    'operator' => 'OR',
    'separator' => 0,
  ));
  _dataprocessor_civix_insert_navigation_menu($menu, 'Administer/data_processor', array(
    'label' => E::ts('Add Data Processor'),
    'name' => 'add_data_processor',
    'url' => CRM_Utils_System::url('civicrm/dataprocessor/form/edit', 'reset=1&action=add', true),
    'permission' => 'administer CiviCRM',
    'operator' => 'OR',
    'separator' => 0,
  ));
  \Civi\DataProcessor\Output\UIOutputHelper::navigationMenuHook($menu);
  _dataprocessor_civix_navigationMenu($menu);
}
