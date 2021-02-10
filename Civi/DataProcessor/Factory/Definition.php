<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

namespace Civi\DataProcessor\Factory;

class Definition {

  protected $class;

  protected $arguments = null;

  /**
   * Definition constructor.
   *
   * @param String $class
   * @param array $arguments
   *  Optional
   */
  public function __construct($class, $arguments=null) {
    $this->class = $class;
    if (is_array($arguments)) {
      $this->arguments = $arguments;
    }
  }

  /**
   * @return object
   * @throws \ReflectionException
   */
  public function get() {
    $reflectionClass = new \ReflectionClass($this->class);
    if ($this->arguments && $reflectionClass->getConstructor()) {
      return $reflectionClass->newInstanceArgs($this->arguments);
    } elseif ($reflectionClass->getConstructor()) {
      return $reflectionClass->newInstance();
    } else {
      return $reflectionClass->newInstanceWithoutConstructor();
    }
  }



}
