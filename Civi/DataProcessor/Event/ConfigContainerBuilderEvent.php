<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

namespace Civi\DataProcessor\Event;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\Event;

class ConfigContainerBuilderEvent extends Event {

  const EVENT_NAME = 'DataProcessorConfigContainerBuilderEvent';

  /**
   * @var \Symfony\Component\DependencyInjection\ContainerBuilder
   */
  public $containerBuilder;

  public function __construct(ContainerBuilder $containerBuilder) {
    $this->containerBuilder = $containerBuilder;
  }

}
