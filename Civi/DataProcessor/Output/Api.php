<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

namespace Civi\DataProcessor\Output;

use Civi\API\Event\ResolveEvent;
use Civi\API\Event\RespondEvent;
use Civi\API\Events;
use Civi\API\Provider\ProviderInterface as API_ProviderInterface;
use Civi\DataProcessor\FieldOutputHandler\arrayFieldOutput;
use Civi\DataProcessor\ProcessorType\AbstractProcessorType;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use \CRM_Dataprocessor_ExtensionUtil as E;

class Api implements OutputInterface, API_ProviderInterface, EventSubscriberInterface {

  /**
   * @var \CRM_Utils_Cache_Interface
   */
  protected $cache;

  public function __construct() {
    $this->cache = \CRM_Utils_Cache::create([
      'name' => 'dataprocessor_api',
      'type' => ['*memory*', 'SqlGroup', 'ArrayCache'],
      'prefetch' => true,
    ]);
  }

  /**
   * Flushes the caches
   */
  public static function clearCache() {
    $api = new Api();
    $api->cache->clear();
  }

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

  /**
   * @return array
   */
  public static function getSubscribedEvents() {
    // Some remarks on the solution to implement the getFields method.
    // We would like to have implemented it as a normal api call. Meaning
    // it would been processed in the invoke method.
    //
    // However the reflection provider has a stop propegation so we cannot use
    // getfields here unles we are earlier then the reflection provider.
    // We should then use a weight lower than Events::W_EARLY and do a
    // stop propegation in our event. But setting an early flag is not a neat way to do stuff.
    // So instead we use the Respond event and check in the respond event whether the action is getFields and
    // if so do our getfields stuff there.
    return array(
      Events::RESOLVE => array(
        array('onApiResolve', Events::W_EARLY),
      ),
    );
  }

  public function onApiResolve(ResolveEvent $event) {
    $entities = $this->getEntityNames();
    $apiRequest = $event->getApiRequest();
    $params = array();
    if (isset($apiRequest['params'])) {
      $params = $apiRequest['params'];
    }
    foreach($entities as $entity) {
      $actions = $this->getActionNames(3, $entity);
      $actions = array_map('strtolower', $actions);
      if (strtolower($apiRequest['entity']) == strtolower($entity)) {
        if (strtolower($apiRequest['action']) == 'getfields' || strtolower($apiRequest['action']) == 'getoptions') {
          if (isset($params['api_action']) && in_array(strtolower($params['api_action']), $actions)) {
            $event->setApiProvider($this);
            $event->stopPropagation();
          }
        } elseif (in_array(strtolower($apiRequest['action']), $actions)) {
          $event->setApiProvider($this);
        }
      }
    }
  }

  /**
   * Invoke the GetFields action
   *
   * @param $apiRequest
   *
   * @return array
   */
  protected function invokeGetFields($apiRequest) {
    $params = $apiRequest['params'];
    $result = array();

    if (isset($params['action'])) {
      try {
        $fields = $this->getFields($apiRequest['entity'], $params);
        if (strtolower($params['action']) != 'getoptions') {
          $result['values'] = $fields;
        } else {
          $actions = array();
          foreach($this->getActionNames(3, $apiRequest['entity']) as $action) {
            $actions[$action] = $action;
          }
          $fieldNames = array();
          foreach($fields as $field) {
            $fieldNames[$field['name']] = $field['title'];
          }
          $result['values']['field']['title'] = E::ts('Field');
          $result['values']['field']['name'] = 'field';
          $result['values']['field']['api.required'] = TRUE;
          $result['values']['field']['options'] = $fieldNames;
          $result['values']['field']['type'] = \CRM_Utils_Type::T_STRING;

          $result['values']['context']['title'] = E::ts('Context');
          $result['values']['context']['name'] = 'context';
          $result['values']['context']['api.required'] = FALSE;
          $result['values']['context']['options'] = \CRM_Core_DAO::buildOptionsContext();
          $result['values']['context']['type'] = \CRM_Utils_Type::T_STRING;

          $result['values']['api_action']['title'] = E::ts('Action');
          $result['values']['api_action']['name'] = 'api_action';
          $result['values']['api_action']['api.required'] = FALSE;
          $result['values']['api_action']['options'] = $actions;
          $result['values']['api_action']['type'] = \CRM_Utils_Type::T_STRING;

        }
      } catch(\Exception $e) {
        // Do nothing.
      }

      $result['count'] = count($result['values']);
    }

    $result = $this->checkForErrors($result);

    return $result;
  }

  protected function getFields($entity, $params) {
    $debug = isset($params['debug']) && $params['debug'] ? true : false;
    $doNotUseCache = $debug ? true : false;

    $cacheKey = 'getfields_'.strtolower($entity);
    if (isset($params['action'])) {
      $cacheKey .= '_'.strtolower($params['action']);
    }
    if (!$doNotUseCache && $cache = $this->cache->get($cacheKey)) {
      return $cache;
    }
    $types = \CRM_Utils_Type::getValidTypes();
    $types['Memo'] = \CRM_Utils_Type::T_TEXT;
    $fields = array();

    // Find the data processor
    $dataProcessorIdSql = "
          SELECT *
          FROM civicrm_data_processor_output o
          INNER JOIN civicrm_data_processor p ON o.data_processor_id = p.id
          WHERE p.is_active = 1 AND LOWER(o.api_entity) = LOWER(%1)
        ";

    $dataProcessorIdParams[1] = array($entity, 'String');
    if (isset($params['action']) && strtolower($params['action']) != 'getoptions') {
      $dataProcessorIdSql .= " AND (LOWER(o.api_action) = LOWER(%2) OR LOWER(o.api_count_action) = LOWER(%2))";
      $dataProcessorIdParams[2] = array($params['action'], 'String');
    }
    $dao = \CRM_Core_DAO::executeQuery($dataProcessorIdSql, $dataProcessorIdParams);
    if (!$dao->fetch()) {
      throw new \API_Exception("Could not find a data processor");
    }
    $dataProcessor = civicrm_api3('DataProcessor', 'getsingle', array('id' => $dao->data_processor_id));
    $dataProcessorClass = \CRM_Dataprocessor_BAO_DataProcessor::dataProcessorToClass($dataProcessor, $doNotUseCache);

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
        'api.required' => FALSE,
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
        'description' => '',
        'type' => $type,
        'api.required' => $filterHandler->isRequired(),
        'api.aliases' => [],
        'api.filter' => TRUE,
        'api.return' => isset($fields[$fieldSpec->alias]) ? $fields[$fieldSpec->alias]['api.return'] : FALSE,
      ];
      if ($fieldSpec->getOptions()) {
        $field['options'] = $fieldSpec->getOptions();
      }

      if (!isset($fields[$fieldSpec->alias])) {
        $fields[$fieldSpec->alias] = $field;
      } else {
        $fields[$fieldSpec->alias] = array_merge($fields[$fieldSpec->alias], $field);
      }
    }
    $this->cache->set($cacheKey, $fields);
    return $fields;
  }

  /**
   * Invoke the GetOptions api call
   *
   * @param $apiRequest
   * @return array
   */
  protected function invokeGetOptions($apiRequest) {
    $params = $apiRequest['params'];
    $result = array();
    $result['values'] = array();
    // Now check whether the action param is set. With the action param we can find the data processor.
    if (isset($params['field'])) {
      try {
        $fieldName = $params['field'];
        $fields = $this->getFields($apiRequest['entity'], $params);
        if (isset($fields[$fieldName]) && isset($fields[$fieldName]['options'])) {
          $result['values'] = $fields[$fieldName]['options'];
        }
      } catch(\Exception $e) {
        // Do nothing.
      }

      $result['count'] = count($result['values']);
    }

    $result = $this->checkForErrors($result);

    return $result;
  }


  /**
   * @param array $apiRequest
   *   The full description of the API request.
   * @return array
   *   structured response data (per civicrm_api3_create_success)
   * @see civicrm_api3_create_success
   * @throws \Exception
   */
  public function invoke($apiRequest) {
    switch (strtolower($apiRequest['action'])) {
      case 'getfields':
        // Do get fields
        return $this->invokeGetFields($apiRequest);
        break;
      case 'getoptions':
        // Do get options
        return $this->invokeGetOptions($apiRequest);
        break;
      default:
        return $this->invokeDataProcessor($apiRequest);
        break;
    }
  }

  protected function invokeDataProcessor($apiRequest) {
    $params = $apiRequest['params'];
    $debug = isset($params['debug']) && $params['debug'] ? true : false;
    $doNotUseCache = $debug ? true : false;

    $isCountAction = FALSE;
    $dataProcessorIdSql = "
      SELECT *
      FROM civicrm_data_processor_output o
      INNER JOIN civicrm_data_processor p ON o.data_processor_id = p.id
      WHERE p.is_active = 1 AND LOWER(o.api_entity) = LOWER(%1)
      AND (LOWER(o.api_action) = LOWER(%2) OR LOWER(o.api_count_action) = LOWER(%2))
    ";
    $dataProcessorIdParams[1] = array($apiRequest['entity'], 'String');
    $dataProcessorIdParams[2] = array($apiRequest['action'], 'String');
    $dao = \CRM_Core_DAO::executeQuery($dataProcessorIdSql, $dataProcessorIdParams);
    if (!$dao->fetch()) {
      throw new \API_Exception("Could not find a data processor");
    }
    if (strtolower($dao->api_count_action) == $apiRequest['action']) {
      $isCountAction = TRUE;
    }

    $cache_key = 'data_processor_id_'.$dao->data_processor_id;
    if ($doNotUseCache || !($dataProcessor = $this->cache->get($cache_key)) ){
      $dataProcessor = civicrm_api3('DataProcessor', 'getsingle', ['id' => $dao->data_processor_id]);
      $this->cache->set($cache_key, $dataProcessor);
    }
    $dataProcessorClass = \CRM_Dataprocessor_BAO_DataProcessor::dataProcessorToClass($dataProcessor, $doNotUseCache);
    return $this->runDataProcessor($dataProcessorClass, $params, $isCountAction);
  }

  protected function runDataProcessor(AbstractProcessorType $dataProcessorClass, $params, $isCount) {
    foreach($dataProcessorClass->getFilterHandlers() as $filter) {
      if (!$filter->isExposed()) {
        continue;
      }
      $filterSpec = $filter->getFieldSpecification();
      if ($filter->isRequired() && !isset($params[$filterSpec->alias])) {
        throw new \API_Exception('Field '.$filterSpec->alias.' is required');
      }
      if (isset($params[$filterSpec->alias])) {
        if (!is_array($params[$filterSpec->alias])) {
          $filterParams = [
            'op' => '=',
            'value' => $params[$filterSpec->alias],
          ];
        } else {
          $value = reset($params[$filterSpec->alias]);
          $filterParams = [
            'op' => key($params[$filterSpec->alias]),
            'value' => $value,
          ];
        }
        $filter->setFilter($filterParams);
      }
    }

    if ($isCount) {
      $count = $dataProcessorClass->getDataFlow()->recordCount();
      return array('result' => $count, 'is_error' => 0);
    } else {
      $options = _civicrm_api3_get_options_from_params($params);

      if (isset($options['limit']) && $options['limit'] > 0) {
        $dataProcessorClass->getDataFlow()->setLimit($options['limit']);
      }
      if (isset($options['offset'])) {
        $dataProcessorClass->getDataFlow()->setOffset($options['offset']);
      }
      if (isset($options['sort'])) {
        $sort = explode(', ', $options['sort']);
        $dataProcessorClass->getDataFlow()->resetSort();
        foreach ($sort as $index => &$sortString) {
          // Get sort field and direction
          list($sortField, $dir) = array_pad(explode(' ', $sortString), 2, 'ASC');
          $dataProcessorClass->getDataFlow()->addSort($sortField, $dir);
        }
      }

      $records = $dataProcessorClass->getDataFlow()->allRecords();
      $values = array();
      foreach($records as $idx => $record) {
        foreach($record as $fieldname => $field) {
          if ($field instanceof arrayFieldOutput) {
            $values[$idx][$fieldname] = $field->getArrayData();
          } else {
            $values[$idx][$fieldname] = $field->formattedValue;
          }
        }
      }
      $return = array(
        'values' => $values,
        'count' => count($values),
        'is_error' => 0,
      );

      if (isset($params['debug']) && $params['debug']) {
        $return['debug_info'] = $dataProcessorClass->getDataFlow()->getDebugInformation();
      }

      $return = $this->checkForErrors($return);

      return $return;
    }
  }

  /**
   * Check for errors in the CiviCRM Status messages list
   * and if errors are present create a civicrm api return error with the messages in
   * the status list
   *
   * @param $return
   *
   * @return mixed
   */
  protected function checkForErrors($return) {
    $session = \CRM_Core_Session::singleton();
    $statuses = $session->getStatus(true);
    if (!is_array($statuses)) {
      return $return;
    }
    foreach($statuses as $status) {
      if ($status['type'] == 'error') {
        $return['is_error'] = 1;
        unset($return['values']);
        unset($return['count']);
        if (!isset($return['error_message'])) {
          $return['error_message'] = "";
        }
        $return['error_message'] .= " ".$status['text'];
      }
    }
    if (isset($return['error_message'])) {
      $return['error_message'] = trim($return['error_message']);
    }
    return $return;
  }

  /**
   * @param int $version
   *   API version.
   * @return array<string>
   */
  public function getEntityNames($version=null) {
    $dao = \CRM_Core_DAO::executeQuery("
      SELECT DISTINCT o.api_entity
      FROM civicrm_data_processor_output o
      INNER JOIN civicrm_data_processor p ON o.data_processor_id = p.id
      WHERE p.is_active = 1
    ");
    $entities = array();

    while($dao->fetch()) {
      if ($dao->api_entity) {
        $entities[] = $dao->api_entity;
      }
    }
    return $entities;
  }

  /**
   * @param int $version
   *   API version.
   * @param string $entity
   *   API entity.
   * @return array<string>
   */
  public function getActionNames($version, $entity) {
    $actions = array();
    $dao = \CRM_Core_DAO::executeQuery("
      SELECT api_action, api_count_action
      FROM civicrm_data_processor_output o
      INNER JOIN civicrm_data_processor p ON o.data_processor_id = p.id
      WHERE p.is_active = 1 AND LOWER(o.api_entity) = LOWER(%1)",
      array(1=>array($entity, 'String'))
    );
    while($dao->fetch()) {
      $actions[] = $dao->api_action;
      $actions[] = $dao->api_count_action;
    }
    $actions[] = 'getfields';
    $actions[] = 'getoptions';

    return $actions;
  }


}
