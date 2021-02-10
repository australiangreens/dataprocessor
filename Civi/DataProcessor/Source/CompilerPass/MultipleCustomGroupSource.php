<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

namespace Civi\DataProcessor\Source\CompilerPass;

use CRM_Dataprocessor_ExtensionUtil as E;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class MultipleCustomGroupSource implements CompilerPassInterface {

  /**
   * You can modify the container here before it is dumped to PHP code.
   */
  public function process(ContainerBuilder $container) {
    if (!$container->hasDefinition('data_processor_factory')) {
      return;
    }
    $factoryDefinition = $container->getDefinition('data_processor_factory');

    try {
      $dao = \CRM_Core_DAO::executeQuery("SELECT * FROM civicrm_custom_group WHERE is_multiple = '1' and is_active = '1'");
      while($dao->fetch()) {
        $arguments = [
          '\Civi\DataProcessor\Source\Contact\MultipleCustomGroupSource',
          [$dao->name, $dao->title, $dao->table_name],
        ];
        $definition = new Definition('Civi\DataProcessor\Factory\Definition', $arguments);
        $factoryDefinition->addMethodCall('addDataSource', array('multi_custom_group_'.$dao->name, $definition, E::ts('Custom group: %1', [1=>$dao->title])));
      }
    } catch (\CiviCRM_API3_Exception $e) {
      // Do nothing
    }
  }

}
