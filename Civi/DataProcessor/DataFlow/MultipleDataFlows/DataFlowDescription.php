<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

namespace Civi\DataProcessor\DataFlow\MultipleDataFlows;

class DataFlowDescription {

  /**
   * @var \Civi\DataProcessor\DataFlow\AbstractDataFlow;
   */
  protected $dataFlow;

  /**
   * @var \Civi\DataProcessor\DataFlow\MultipleDataFlows\JoinInterface
   */
  protected $joinSpecification = null;

  public function __construct($datFlow, $joinSpecification = null) {
    $this->dataFlow = $datFlow;
    $this->joinSpecification = $joinSpecification;
    $this->dataFlow->setDataFlowDescription($this);
  }

  /**
   * @return \Civi\DataProcessor\DataFlow\AbstractDataFlow
   */
  public function getDataFlow() {
    return $this->dataFlow;
  }

  /**
   * @return \Civi\DataProcessor\DataFlow\MultipleDataFlows\JoinInterface
   */
  public function getJoinSpecification() {
    return $this->joinSpecification;
  }

  /**
   * When an object is cloned, PHP 5 will perform a shallow copy of all of the
   * object's properties. Any properties that are references to other
   * variables, will remain references. Once the cloning is complete, if a
   * __clone() method is defined, then the newly created object's __clone()
   * method will be called, to allow any necessary properties that need to be
   * changed. NOT CALLABLE DIRECTLY.
   *
   * @return void
   * @link https://php.net/manual/en/language.oop5.cloning.php
   */
  public function __clone() {
    if ($this->dataFlow) {
      $this->dataFlow = clone $this->dataFlow;
    }
    if ($this->joinSpecification) {
      $this->joinSpecification = clone $this->joinSpecification;
    }
  }

}
