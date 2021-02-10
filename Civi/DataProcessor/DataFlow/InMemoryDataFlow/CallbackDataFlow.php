<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

namespace Civi\DataProcessor\DataFlow\InMemoryDataFlow;

use Civi\DataProcessor\DataFlow\InMemoryDataFlow;

class CallbackDataFlow extends InMemoryDataFlow {

  protected $callback;

  public function __construct(callable $callback) {
    parent::__construct();
    $this->callback = $callback;
  }

  public function initialize() {
    parent::initialize();
    if (!is_array($this->data)) {
      $this->data = call_user_func($this->callback, $this);
    }
  }

  public function resetInitializeState() {
    parent::resetInitializeState();
    $this->data = null;
  }

}
