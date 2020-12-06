<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

namespace Civi\DataProcessor\Output;

/**
 * Class UIOutputHelper
 *
 * An helper class for the UIOutput
 *
 * @package Civi\DataProcessor\Output
 */
class UIOutputHelper {

  private static $rebuildMenu = true;

  /**
   * Delegation of the alter menu hook. Add the search outputs to the menu system.
   *
   * @param $items
   */
  public static function alterMenu(&$items) {
    $factory = dataprocessor_get_factory();
    // Check whether the factory exists. Usually just after
    // installation the factory does not exists but then no
    // outputs exists either. So we can safely return this function.
    if (!$factory) {
      return;
    }

    $sql = "
    SELECT o.permission, p.id, p.title, o.configuration, o.type, o.id as output_id
    FROM civicrm_data_processor_output o
    INNER JOIN civicrm_data_processor p ON o.data_processor_id = p.id
    WHERE p.is_active = 1";
    $dao = \CRM_Core_DAO::executeQuery($sql);
    while ($dao->fetch()) {
      $outputClass = $factory->getOutputByName($dao->type);
      if ($outputClass instanceof \Civi\DataProcessor\Output\UIFormOutputInterface) {
        $output = civicrm_api3('DataProcessorOutput', 'getsingle', array('id' => $dao->output_id));
        $dataprocessor = civicrm_api3('DataProcessor', 'getsingle', array('id' => $dao->id));
        $url = $outputClass->getUrlToUi($output, $dataprocessor);

        $configuration = json_decode($dao->configuration, TRUE);
        $title = $outputClass->getTitleForUiLink($output, $dataprocessor);
        $item = [
          'title' => $title,
          'page_callback' => $outputClass->getCallbackForUi(),
          'access_arguments' => [[$dao->permission], 'and'],
        ];
        $items[$url] = $item;
      }
    }
  }

  public static function navigationMenuHook(&$menu) {
    $factory = dataprocessor_get_factory();
    $dao = \CRM_Core_DAO::executeQuery("
      SELECT DISTINCT o.id as output_id, o.type, p.id as dataprocessor_id
      FROM civicrm_data_processor_output o
      INNER JOIN civicrm_data_processor p ON o.data_processor_id = p.id
      WHERE p.is_active = 1 and p.id = 9
    ");
    while ($dao->fetch()) {
      $outputClass = $factory->getOutputByName($dao->type);
      if ($outputClass instanceof UIOutputInterface) {
        $output = civicrm_api3('DataProcessorOutput', 'getsingle', ['id' => $dao->output_id]);
        self::fixBackwardsCompatibility($output);
        if (isset($output['configuration']['navigation_parent_path'])) {
          $dataProcessor = civicrm_api3('DataProcessor', 'getsingle', ['id' => $dao->dataprocessor_id]);
          $title = $dataProcessor['title'];
          if ($outputClass && $outputClass instanceof \Civi\DataProcessor\Output\UIFormOutputInterface) {
            $url = $outputClass->getUrlToUi($output, $dataProcessor);
            $title = $outputClass->getTitleForUiLink($output, $dataProcessor);
          }
          $item = [
            'label' => $title,
            'name' => $dataProcessor['name'].'_'.$output['type'],
            'url' => \CRM_Utils_System::url($url, 'reset=1', true),
            'operator' => 'OR',
            'separator' => 0,
          ];
          if (isset($output['permission'])) {
            $item['permission'] = $output['permission'];
          }
          _dataprocessor_civix_insert_navigation_menu($menu, $output['configuration']['navigation_parent_path'], $item);
        }
      }
    }
  }

  /**
   * Fix backwards compatibility. Previously we stored the navigation id in the database.
   * Now we have the parent path.
   *
   * @param $output
   */
  public static function fixBackwardsCompatibility($output) {
    $navigation = \CRM_Dataprocessor_Utils_Navigation::singleton();
    if (isset($output['configuration']['navigation_id'])) {
      // Backwards compatibility.
      $output['configuration']['navigation_parent_path'] = $navigation->getNavigationParentPathById($output['configuration']['navigation_id']);
      \CRM_Core_BAO_Navigation::processDelete($output['configuration']['navigation_id']);
      unset($output['configuration']['navigation_id']);
      \CRM_Core_DAO::executeQuery("UPDATE civicrm_data_processor_output SET configuration = %1 WHERE id = %2", [
        1 => [json_encode($output['configuration']), 'String'],
        2 => [$output['id'],'Integer'],
      ]);
    }
  }

  /**
   * Update the navigation data when an output is saved/deleted from the database.
   *
   * @param $op
   * @param $objectName
   * @param $objectId
   * @param $objectRef
   */
  public static function postHook($op, $objectName, $id, &$objectRef) {
    // Disable this hook in unit tests because the menu rebuild it causes breaks transactions.
    if (CIVICRM_UF === 'UnitTests') {
      return;
    }

    if (self::$rebuildMenu && ($objectName == 'DataProcessorOutput' || $objectName == 'DataProcessor')) {
      self::rebuildMenuAndNavigation();
    }
  }

  /**
   * Convert the navigation_id to the parent path of the navigation
   * @param $dataProcessor
   */
  public static function hookExport(&$dataProcessor) {
    $navigation = \CRM_Dataprocessor_Utils_Navigation::singleton();
    foreach($dataProcessor['outputs'] as $idx => $output) {
      if (isset($output['configuration']['navigation_id'])) {
        $dataProcessor['outputs'][$idx]['configuration']['navigation_parent_path'] = $navigation->getNavigationParentPathById($output['configuration']['navigation_id']);
        unset($dataProcessor['outputs'][$idx]['configuration']['navigation_id']);
      }
    }
  }

  /**
   * Disable the rebuilding of the menu and navigation.
   * Usefull during an batch import.
   */
  public static function disableRebuildingOfMenuAndNavigation() {
    self::$rebuildMenu = false;
  }

  /**
   * Rebuild the menu and navigation.
   */
  public static function rebuildMenuAndNavigation() {
    // Rebuild the CiviCRM Menu (which has the url and routing information of the pages).
    \CRM_Core_Menu::store();
    // Also reset navigation
    \CRM_Core_BAO_Navigation::resetNavigation();
  }

}
