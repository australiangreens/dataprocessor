<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

class CRM_Dataprocessor_Utils_Cache {

  /**
   * @var CRM_DataProcessor_Utils_Cache
   */
  private static $singleton;

  /**
   * @var \CRM_Utils_Cache_Interface
   */
  protected $cache;

  private function __construct() {
    $this->cache = \CRM_Utils_Cache::create([
      'name' => 'dataprocessor',
      'type' => ['*memory*', 'SqlGroup', 'ArrayCache'],
      'prefetch' => FALSE,
    ]);
  }

  /**
   * Clear all data processor caches.
   *
   * This is a helper function which contains a clear cache for all caches.
   *
   */
  public static function clearAllDataProcessorCaches() {
      $cache = CRM_Dataprocessor_Utils_Cache::singleton();
      $cache->cache->clear();
      Civi\DataProcessor\ConfigContainer::clearCache();
      Civi\DataProcessor\Output\Api::clearCache();
  }

  /**
   * @return \CRM_DataProcessor_Utils_Cache
   */
  public static function singleton() {
    if (!self::$singleton) {
      self::$singleton = new CRM_DataProcessor_Utils_Cache();
    }
    return self::$singleton;
  }

  /**
   * @param $key
   * @param null $default
   *
   * @return mixed
   */
  public function get($key, $default=NULL) {
    $environment = \Civi::settings()->get('environment');
    if ($environment == 'Production') {
      return $this->cache->get($key, $default);
    }
    return $default;
  }

  /**
   * @param $key
   * @param $value
   * @param null $ttl
   *
   * @return bool
   */
  public function set($key, $value, $ttl=NULL) {
    $environment = \Civi::settings()->get('environment');
    if ($environment == 'Production') {
      return $this->cache->set($key, $value, $ttl);
    }
    return true;
  }

}
