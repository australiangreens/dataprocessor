<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

namespace Civi\DataProcessor;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * This class holds dummy configurations which could be used when the
 * config class is compiled and during compilation we need the config class.
 *
 * @package Civi\DataProcessor
 */
class DummyConfig extends Config {

  public function __construct(ParameterBagInterface $parameterBag = NULL) {
    parent::__construct($parameterBag);
    $this->setParameter('entity_names', []);
  }

}
