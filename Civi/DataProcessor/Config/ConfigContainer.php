<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

namespace Civi\DataProcessor\Config;

use Civi\DataProcessor\Event\ConfigContainerBuilderEvent;
use Civi\DataProcessor\Output\Api;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;

class ConfigContainer {

  /**
   * @var \Civi\DataProcessor\Config\Config
   */
  public static $configContainer;

  private function __construct() {
  }

  /**
   * @return \Civi\DataProcessor\Config\Config
   */
  public static function getInstance() {
    static $isRunning = false;
    if ($isRunning) {
      // The function getInstance is already running
      // and somewhere in the code a config class is needed
      // so we return an empty Config class.
      return new Config();
    }
    if (!self::$configContainer) {
      $isRunning = true;
      $file = self::getCacheFile();
      if (!file_exists($file)) {
        $containerBuilder = self::createContainer();
        $containerBuilder->compile();
        $dumper = new PhpDumper($containerBuilder);
        file_put_contents($file, $dumper->dump([
          'class' => 'CachedDataProcessorConfig',
          'base_class' => '\Civi\DataProcessor\Config\Config',
        ]));
        $isRunning = false;
      }
      require_once $file;
      self::$configContainer = new \CachedDataProcessorConfig();
    }
    return self::$configContainer;
  }

  /**
   * Clear the cache.
   */
  public static function clearCache() {
    $file = self::getCacheFile();
    if (file_exists($file)) {
      unlink($file);
    }
  }

  /**
   * The name of the cache file.
   *
   * @return string
   */
  public static function getCacheFile() {
    // The envId is build based on the domain and database settings.
    // So we cater for multisite installations and installations with one code base
    // and multiple databases.
    $envId = \CRM_Core_Config_Runtime::getId();
    return CIVICRM_TEMPLATE_COMPILEDIR."/CachedDataProcessorConfig.{$envId}.php";
  }

  /**
   * Clears the cached configuration file ony when custom field or custom group has been saved.
   *
   * @param $op
   * @param $objectName
   * @param $objectId
   * @param $objectRef
   */
  public static function postHook($op, $objectName, $id, &$objectRef) {
    $clearCacheObjects = ['CustomGroup', 'CustomField'];
    if (in_array($objectName, $clearCacheObjects)) {
      self::clearCache();
    }
  }

  /**
   * Create the containerBuilder
   *
   * @return \Symfony\Component\DependencyInjection\ContainerBuilder
   */
  protected static function createContainer() {
    $containerBuilder = new ContainerBuilder();

    Config::buildConfigContainer($containerBuilder);
    Api::buildConfigContainer($containerBuilder);

    // Dipsatch an symfony event so that extensions could listen to this event
    // and hook int the building of the config container.
    $event = new ConfigContainerBuilderEvent($containerBuilder);
    \Civi::dispatcher()->dispatch(ConfigContainerBuilderEvent::EVENT_NAME, $event);
    return $containerBuilder;
  }

}
