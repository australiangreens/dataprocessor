<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

namespace Civi\DataProcessor\Output;

use Civi\API\Event\ResolveEvent;
use Civi\API\Events;
use Civi\DataProcessor\ConfigContainer;
use Civi\DataProcessor\FieldOutputHandler\arrayFieldOutput;
use Civi\DataProcessor\ProcessorType\AbstractProcessorType;

use \CRM_Dataprocessor_ExtensionUtil as E;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class Api extends AbstractApi implements OutputInterface {

  /**
   * Returns true when this filter has additional configuration
   *
   * @return bool
   */
  public function hasConfiguration() {
    return true;
  }

  /**
   * When this filter type has additional configuration you can add
   * the fields on the form with this function.
   *
   * @param \CRM_Core_Form $form
   * @param array $filter
   */
  public function buildConfigurationForm(\CRM_Core_Form $form, $output=array()) {
    $dataProcessor = civicrm_api3('DataProcessor', 'getsingle', array('id' => $output['data_processor_id']));
    $dataProcessorClass = \CRM_Dataprocessor_BAO_DataProcessor::dataProcessorToClass($dataProcessor);

    $form->add('select','permission', E::ts('Permission'), \CRM_Core_Permission::basicPermissions(), true, array(
      'style' => 'min-width:250px',
      'class' => 'crm-select2 huge',
      'placeholder' => E::ts('- select -'),
    ));
    $form->add('text', 'api_entity', E::ts('API Entity'), true);
    $form->add('text', 'api_action', E::ts('API Action Name'), true);
    $form->add('text', 'api_count_action', E::ts('API GetCount Action Name'), true);

    if (isset($output['id'])) {
      $doc = $this->generateMkDocs($dataProcessorClass, $dataProcessor, $output);
      $resources = \CRM_Core_Resources::singleton();
      $resources->addScriptFile('dataprocessor', 'packages/markdown-it/dist/markdown-it.min.js');
      $form->assign('doc', $doc);

    }

    if ($output && isset($output['id']) && $output['id']) {
      $defaults['permission'] = $output['permission'];
      $defaults['api_entity'] = $output['api_entity'];
      $defaults['api_action'] = $output['api_action'];
      $defaults['api_count_action'] = $output['api_count_action'];
    } else {
      $defaults['api_action'] = 'get';
      $defaults['api_count_action'] = 'getcount';
      $defaults['permission'] = 'access CiviCRM';
    }
    $form->setDefaults($defaults);
  }

  /**
   * When this filter type has configuration specify the template file name
   * for the configuration form.
   *
   * @return false|string
   */
  public function getConfigurationTemplateFileName() {
    return "CRM/Dataprocessor/Form/Output/API.tpl";
  }


  /**
   * Process the submitted values and create a configuration array
   *
   * @param $submittedValues
   * @param array $output
   * @return array
   */
  public function processConfiguration($submittedValues, &$output) {
    $output['permission'] = $submittedValues['permission'];
    $output['api_entity'] = $submittedValues['api_entity'];
    $output['api_action'] = $submittedValues['api_action'];
    $output['api_count_action'] = $submittedValues['api_count_action'];
    return array();
  }


  public function generateMkDocs(AbstractProcessorType $dataProcessorClass, $dataProcessor, $output) {
    $types = \CRM_Utils_Type::getValidTypes();
    $types['Memo'] = \CRM_Utils_Type::T_TEXT;
    $fields = array();
    $filters = array();
    foreach ($dataProcessorClass->getDataFlow()->getOutputFieldHandlers() as $outputFieldHandler) {
      $fieldSpec = $outputFieldHandler->getOutputFieldSpecification();
      $type = \CRM_Utils_Type::T_STRING;
      if (isset($types[$fieldSpec->type])) {
        $type = $types[$fieldSpec->type];
      }
      $field = [
        'name' => $fieldSpec->alias,
        'title' => $fieldSpec->title,
        'description' => '',
        'type' => $type,
        'data_type' => $fieldSpec->type,
        'api.aliases' => [],
        'api.filter' => FALSE,
        'api.return' => TRUE,
      ];
      if ($fieldSpec->getOptions()) {
        $field['options'] = $fieldSpec->getOptions();
      }
      $fields[$fieldSpec->alias] = $field;
    }
    foreach($dataProcessorClass->getFilterHandlers() as $filterHandler) {
      $fieldSpec = $filterHandler->getFieldSpecification();
      $type = \CRM_Utils_Type::T_STRING;
      if (isset($types[$fieldSpec->type])) {
        $type = $types[$fieldSpec->type];
      }
      if (!$fieldSpec || !$filterHandler->isExposed()) {
        continue;
      }
      $field = [
        'name' => $fieldSpec->alias,
        'title' => $fieldSpec->title,
        'data_type' => $fieldSpec->type,
        'description' => '',
        'type' => $type,
        'required' => $filterHandler->isRequired(),
      ];
      if ($fieldSpec->getOptions()) {
        $field['options'] = $fieldSpec->getOptions();
      }

      $filters[$fieldSpec->alias] = $field;
    }

    $template = \CRM_Core_Smarty::singleton();
    $config = \CRM_Core_Config::singleton();
    $oldTemplateVars = $template->get_template_vars();
    $template->clearTemplateVars();
    $template->assign('dataprocessor', $dataProcessor);
    $template->assign('output', $output);
    $template->assign('fields', $fields);
    $template->assign('filters', $filters);
    $template->assign('resourceBase', rtrim($config->userFrameworkBaseURL, "/").rtrim($config->resourceBase, "/"));
    $docs = $template->fetch('CRM/Dataprocessor/Docs/Output/API.tpl');
    $template->assignAll($oldTemplateVars);
    return $docs;
  }

  /**
   * This function is called prior to removing an output
   *
   * @param array $output
   * @return void
   */
  public function deleteOutput($output) {
    // Do nothing
  }

  public static function buildConfigContainer(ContainerBuilder $containerBuilder) {
    // Load the entities.
    $dao = \CRM_Core_DAO::executeQuery("
      SELECT DISTINCT o.api_entity, api_action, api_count_action
      FROM civicrm_data_processor_output o
      INNER JOIN civicrm_data_processor p ON o.data_processor_id = p.id
      WHERE p.is_active = 1
    ");
    $entities = array();
    $actions = array();

    while($dao->fetch()) {
      if ($dao->api_entity ) {
        if (!in_array($dao->api_entity, $entities)) {
          $entities[] = $dao->api_entity;
        }
        if (!isset($actions[$dao->api_entity])) {
          $actions[$dao->api_entity][] = 'getfields';
          $actions[$dao->api_entity][] = 'getoptions';
        }
        $actions[$dao->api_entity][] = $dao->api_action;
        $actions[$dao->api_entity][] = $dao->api_count_action;
      }
    }
    $containerBuilder->setParameter('entity_names', $entities);
    $containerBuilder->setParameter('action_names', $actions);
  }

  /**
   * @param int $version
   *   API version.
   * @return array<string>
   */
  public function getEntityNames($version=null) {
    $config = ConfigContainer::getInstance();
    return $config->getParameter('entity_names');
  }

  /**
   * @param int $version
   *   API version.
   * @param string $entity
   *   API entity.
   * @return array<string>
   */
  public function getActionNames($version, $entity) {
    $config = ConfigContainer::getInstance();
    $actions = $config->getParameter('action_names');
    return $actions[$entity];
  }

  /**
   * Returns the ID for this data processor.
   *
   * @param $entity
   * @param $action
   * @param $params
   *
   * @return int
   * @throws \API_Exception
   */
  protected function getDataProcessorId($entity, $action, $params) {
    // Find the data processor
    $dataProcessorIdSql = "
          SELECT *
          FROM civicrm_data_processor_output o
          INNER JOIN civicrm_data_processor p ON o.data_processor_id = p.id
          WHERE p.is_active = 1 AND LOWER(o.api_entity) = LOWER(%1)
        ";

    $dataProcessorIdParams[1] = array($entity, 'String');
    if (strtolower($action) == 'getfields') {
      if (isset($params['action']) && strtolower($params['action']) != 'getoptions') {
        $dataProcessorIdSql .= " AND (LOWER(o.api_action) = LOWER(%2) OR LOWER(o.api_count_action) = LOWER(%2))";
        $dataProcessorIdParams[2] = [$params['action'], 'String'];
      }
    } else {
      $dataProcessorIdSql .= " AND (LOWER(o.api_action) = LOWER(%2) OR LOWER(o.api_count_action) = LOWER(%2))";
      $dataProcessorIdParams[2] = [$action, 'String'];
    }
    $dao = \CRM_Core_DAO::executeQuery($dataProcessorIdSql, $dataProcessorIdParams);
    if (!$dao->fetch()) {
      throw new \API_Exception("Could not find a data processor");
    }
    return $dao->data_processor_id;
  }

  /**
   * Returns whether this action is a getcount action.
   *
   * @param $entity
   * @param $action
   * @param $params
   *
   * @return bool
   * @throws \API_Exception
   */
  protected function isCountAction($entity, $action, $params) {
    $dataProcessorIdSql = "
      SELECT *
      FROM civicrm_data_processor_output o
      INNER JOIN civicrm_data_processor p ON o.data_processor_id = p.id
      WHERE p.is_active = 1 AND LOWER(o.api_entity) = LOWER(%1)
      AND (LOWER(o.api_action) = LOWER(%2) OR LOWER(o.api_count_action) = LOWER(%2))
    ";
    $dataProcessorIdParams[1] = array($entity, 'String');
    $dataProcessorIdParams[2] = array($action, 'String');
    $dao = \CRM_Core_DAO::executeQuery($dataProcessorIdSql, $dataProcessorIdParams);
    if (!$dao->fetch()) {
      throw new \API_Exception("Could not find a data processor");
    }
    if (strtolower($dao->api_count_action) == $action) {
      return TRUE;
    }
    return FALSE;
  }


}
