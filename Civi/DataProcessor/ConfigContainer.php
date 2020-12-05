<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

namespace Civi\DataProcessor;

use Civi\DataProcessor\Event\ConfigContainerBuilderEvent;
use Civi\DataProcessor\Output\Api;
use Symfony\Component\Config\ConfigCache;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;

class ConfigContainer {

  /**
   * @var \Symfony\Component\DependencyInjection\Container
   */
  public static $configContainer;

  private function __construct() {
  }

  /**
   * @return \Symfony\Component\DependencyInjection\Container
   */
  public static function getInstance() {
    if (!self::$configContainer) {
      $file = \Civi::paths()->getPath("[civicrm.compile]/CachedDataProcessorConfigContainer.php");
      $containerConfigCache = new ConfigCache($file, false);
      if (!$containerConfigCache->isFresh()) {
        $containerBuilder = self::createContainer();
        $containerBuilder->compile();
        $dumper = new PhpDumper($containerBuilder);
        $containerConfigCache->write(
          $dumper->dump(['class' => 'CachedDataProcessorConfigContainer']),
          $containerBuilder->getResources()
        );
      }
      require_once $file;
      self::$configContainer = new \CachedDataProcessorConfigContainer();
    }
    return self::$configContainer;
  }

  /**
   * Clear the cache.
   */
  public static function clearCache() {
    $file = \Civi::paths()->getPath("[civicrm.compile]/CachedDataProcessorConfigContainer.php");
    if (file_exists($file)) {
      unlink($file);
    }
  }

  /**
   * Create the containerBuilder
   *
   * @return \Symfony\Component\DependencyInjection\ContainerBuilder
   */
  protected static function createContainer() {
    $containerBuilder = new ContainerBuilder();

    Api::buildConfigContainer($containerBuilder);

    // Dipsatch an symfony event so that extensions could listen to this event
    // and hook int the building of the config container.
    $event = new ConfigContainerBuilderEvent($containerBuilder);
    \Civi::dispatcher()->dispatch(ConfigContainerBuilderEvent::EVENT_NAME, $event);
    return $containerBuilder;
  }

}
