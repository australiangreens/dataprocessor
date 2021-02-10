<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

namespace Civi\DataProcessor\DataFlow\Sort;

use Civi\DataProcessor\DataFlow\Sort\SortComparer;

class SortCompareFactory {

  private $compareres = array(
    'String' => '\Civi\DataProcessor\DataFlow\Sort\StringSortComparer'
  );

  private $genericComparer = '\Civi\DataProcessor\DataFlow\Sort\SortComparer';

  /**
   * @param string $type
   * @return SortComparer
   */
  public function getComparer($type) {
    $class = $this->genericComparer;
    if (isset($this->compareres[$type])) {
      $class = $this->compareres[$type];
    }
    return new $class;
  }

  /**
   * @param string $type
   * @param string $className
   */
  public function addSortComparer($type, $className) {
    $this->compareres[$type] = $className;
  }

}
