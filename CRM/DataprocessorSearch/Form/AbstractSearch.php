<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

use Civi\DataProcessor\FieldOutputHandler\FieldOutput;
use Civi\DataProcessor\FieldOutputHandler\Markupable;
use Civi\DataProcessor\ProcessorType\AbstractProcessorType;
use CRM_Dataprocessor_ExtensionUtil as E;

abstract class CRM_DataprocessorSearch_Form_AbstractSearch extends CRM_Dataprocessor_Form_Output_AbstractUIOutputForm {

  /**
   * @var String
   */
  protected $title;

  /**
   * @var int
   */
  protected $limit;

  /**
   * @var int
   */
  protected $pageId;

  /**
   * @var bool
   */
  protected $_debug = FALSE;

  /**
   * @var \CRM_Utils_Sort
   */
  protected $sort;

  /**
   * @var array
   */
  protected $_appliedFilters;

  /**
   * @var string
   */
  protected $currentUrl;

  /**
   * The params that are sent to the query.
   *
   * @var array
   */
  protected $_queryParams;

  /**
   * The array of entity IDs from the form
   *
   * @var array
   */
  protected $entityIDs;

  /**
   * Name of action button
   *
   * @var string
   */
  protected $_searchButtonName;

  /**
   * Returns the name of the ID field in the dataset.
   *
   * @return string
   */
  abstract protected function getIdFieldName();

  /**
   * @return false|string
   */
  abstract protected function getEntityTable();

  /**
   * Builds the list of tasks or actions that a searcher can perform on a result set.
   *
   * @return array
   */
  public function buildTaskList() {
    return $this->_taskList;
  }

  /**
   * Returns whether we want to use the prevnext cache.
   * @return bool
   */
  protected function usePrevNextCache() {
    return false;
  }

  /**
   * Returns whether the ID field is Visible
   *
   * @return bool
   */
  protected function isIdFieldVisible() {
    if (isset($this->dataProcessorOutput['configuration']['hide_id_field']) && $this->dataProcessorOutput['configuration']['hide_id_field']) {
      return false;
    }
    return true;
  }

  /**
   * Returns an array with hidden columns
   *
   * @return array
   */
  protected function getHiddenFields() {
    $hiddenFields = array();
    if (!$this->isIdFieldVisible()) {
      $hiddenFields[] = $this->getIdFieldName();
    }
    if (isset($this->dataProcessorOutput['configuration']['hidden_fields']) && is_array($this->dataProcessorOutput['configuration']['hidden_fields'])) {
      $hiddenFields = array_merge($hiddenFields, $this->dataProcessorOutput['configuration']['hidden_fields']);
    }
    return $hiddenFields;
  }

  /**
   * Retrieve the text for no results.
   *
   * @return string
   */
  protected function getNoResultText() {
    if (isset($this->dataProcessorOutput['configuration']['no_result_text'])) {
      return $this->dataProcessorOutput['configuration']['no_result_text'];
    }
    return E::ts('No results');
  }

  /**
   * Returns the url for view of the record action
   *
   * @param $row
   *
   * @return false|string
   */
  abstract protected function link($row);

  /**
   * Returns the link text for view of the record action
   *
   * @param $row
   *
   * @return false|string
   */
  abstract protected function linkText($row);

  /**
   * Return altered rows
   *
   * @param array $rows
   * @param array $ids
   *
   */
  protected function alterRows(&$rows, $ids) {

  }

  public function preProcess() {
    parent::preProcess();

    $qfKey = CRM_Utils_Request::retrieveValue('qfKey', 'String');
    $urlPath = CRM_Utils_System::currentPath();
    $urlParams = 'force=1';
    if ($qfKey) {
      $urlParams .= "&qfKey=$qfKey";
    }
    $this->currentUrl = CRM_Utils_System::url($urlPath, $urlParams);
    $session = CRM_Core_Session::singleton();
    $session->replaceUserContext($this->currentUrl);

    if (!empty($_POST) && !$this->controller->isModal()) {
      $this->_formValues = $this->controller->exportValues($this->_name);
    } elseif (CRM_Utils_Request::retrieve('ssID', 'Integer')) {
      $savedSearchDao = new CRM_Contact_DAO_SavedSearch();
      $savedSearchDao->id = CRM_Utils_Request::retrieve('ssID', 'Integer');
      if ($savedSearchDao->find(TRUE) && !empty($savedSearchDao->form_values)) {
        $this->_formValues = unserialize($savedSearchDao->form_values);
        $this->_submitValues = $this->_formValues;
        $this->controller->set('formValue', $this->_formValues);
      }
    } else {
      $this->_formValues = $this->getSubmitValues();
    }

    $this->_searchButtonName = $this->getButtonName('refresh');
    $this->_actionButtonName = $this->getButtonName('next', 'action');
    $this->_done = FALSE;
    $this->defaults = [];
    // we allow the controller to set force/reset externally, useful when we are being
    // driven by the wizard framework
    $this->_debug = $this->isDebug();
    $this->_reset = CRM_Utils_Request::retrieveValue('reset', 'Boolean');
    $this->_force = CRM_Utils_Request::retrieveValue('force', 'Boolean');
    $this->_context = CRM_Utils_Request::retrieveValue('context', 'String', 'search');
    $this->set('context', $this->_context);
    $this->assign("context", $this->_context);
    $this->assign('debug', $this->_debug);

    if ($this->isCriteriaFormCollapsed()) {
      $sortFields = $this->addColumnHeaders();
      $this->sort = new CRM_Utils_Sort($sortFields);
      if (isset($this->_formValues[CRM_Utils_Sort::SORT_ID])) {
        $this->sort->initSortID($this->_formValues[CRM_Utils_Sort::SORT_ID]);
      }
      $this->assign_by_ref('sort', $this->sort);

      $export_id = CRM_Utils_Request::retrieveValue('export_id', 'Positive');
      if ($export_id) {
        $this->runExport($export_id);
      }

      $limit = CRM_Utils_Request::retrieveValue('crmRowCount', 'Positive', $this->getDefaultLimit());
      $pageId = CRM_Utils_Request::retrieveValue('crmPID', 'Positive', 1);
      $this->buildRows($pageId, $limit);
      $this->addExportOutputs();
    }

  }

  /**
   * @return bool
   */
  protected function isCriteriaFormCollapsed() {
    $initialExpanded = false;
    if (isset($this->dataProcessorOutput['configuration']['expanded_search'])) {
      $initialExpanded = $this->dataProcessorOutput['configuration']['expanded_search'];
    }
    if(!$this->hasRequiredFilters() && !$initialExpanded) {
      return  true;
    }
    if ((!empty($this->_formValues) && count($this->validateFilters()) == 0)) {
      return true;
    }
    return false;
  }

  /**
   * Returns the default row limit.
   *
   * @return int
   */
  protected function getDefaultLimit() {
    return CRM_Utils_Pager::ROWCOUNT;
  }

  protected function runExport($export_id) {
    $factory = dataprocessor_get_factory();
    self::applyFilters($this->dataProcessorClass, $this->_formValues);

    // Set the sort
    $sortDirection = 'ASC';
    $sortFieldName = null;
    if ($this->sort->getCurrentSortID() > 1) {
      $sortField = $this->sort->_vars[$this->sort->getCurrentSortID()];
      if ($this->sort->getCurrentSortDirection() == CRM_Utils_Sort::DESCENDING) {
        $sortDirection = 'DESC';
      }
      $sortFieldName = $sortField['name'];
    }

    $this->alterDataProcessor($this->dataProcessorClass);

    $output = civicrm_api3("DataProcessorOutput", "getsingle", array('id' => $export_id));
    $outputClass = $factory->getOutputByName($output['type']);
    if ($outputClass instanceof \Civi\DataProcessor\Output\ExportOutputInterface) {
      $outputClass->downloadExport($this->dataProcessorClass, $this->dataProcessor, $output, $this->_formValues, $sortFieldName, $sortDirection, $this->getIdFieldName(), $this->getSelectedIds());
    }
  }

  /**
   * Retrieve the records from the data processor
   *
   * @param $pageId
   * @param $limit
   *
   * @throws \Civi\DataProcessor\DataFlow\InvalidFlowException
   */
  protected function buildRows($pageId, $limit) {
    $rows = [];
    $ids = array();
    $prevnextData = array();

    $id_field = $this->getIdFieldName();
    $this->assign('id_field', $id_field);

    $offset = ($pageId - 1) * $limit;
    $this->dataProcessorClass->getDataFlow()->setLimit($limit);
    $this->dataProcessorClass->getDataFlow()->setOffset($offset);
    self::applyFilters($this->dataProcessorClass, $this->_formValues);

    // Set the sort
    if ($this->sort->getCurrentSortID() > 1) {
      $sortDirection = 'ASC';
      $sortField = $this->sort->_vars[$this->sort->getCurrentSortID()];
      if ($this->sort->getCurrentSortDirection() == CRM_Utils_Sort::DESCENDING) {
        $sortDirection = 'DESC';
      }
      $this->dataProcessorClass->getDataFlow()->resetSort();
      $this->dataProcessorClass->getDataFlow()->addSort($sortField['name'], $sortDirection);
    }

    $this->alterDataProcessor($this->dataProcessorClass);

    try {
      $pagerParams = $this->getPagerParams();
      $pagerParams['total'] = $this->dataProcessorClass->getDataFlow()->recordCount();
      $pagerParams['pageID'] = $pageId;
      $this->pager = new CRM_Utils_Pager($pagerParams);
      $this->assign('pager', $this->pager);
      $this->controller->set('rowCount', $this->dataProcessorClass->getDataFlow()->recordCount());
      $showLink = false;

      $i=0;
      while($record = $this->dataProcessorClass->getDataFlow()->nextRecord()) {
        $i ++;
        $row = array();

        $row['id'] = null;
        if ($id_field && isset($record[$id_field])) {
          $row['id'] = $record[$id_field]->rawValue;
        }
        if ($id_field) {
          $row['checkbox'] = CRM_Core_Form::CB_PREFIX . $row['id'];
        }
        $row['record'] = array();
        foreach($record as $column => $value) {
          if ($value instanceof Markupable) {
            $row['record'][$column] = $value->getMarkupOut();
          } elseif ($value instanceof FieldOutput) {
            $row['record'][$column] = htmlspecialchars($value->formattedValue);
          }
        }

        $link = $this->link($row);
        if ($link) {
          $row['url'] = $link;
          $row['link_text'] = $this->linkText($row);
          $showLink = true;
        }

        if (isset($row['checkbox'])) {
          $this->addElement('checkbox', $row['checkbox'], NULL, NULL, ['class' => 'select-row']);
        }

        if ($row['id'] && $this->usePrevNextCache()) {
          $prevnextData[] = array(
            'entity_id1' => $row['id'],
            'entity_table' => $this->getEntityTable(),
            'data' => $record,
          );
          $ids[] = $row['id'];
        } else {
          $ids[] = $row['id'];
        }

        $rows[] = $row;
      }
    } catch (\Civi\DataProcessor\DataFlow\EndOfFlowException $e) {
      // Do nothing
    } catch (\Civi\DataProcessor\Exception\DataFlowException $e) {
      \CRM_Core_Session::setStatus(E::ts('Error in data processor'), E::ts('Error'), 'error');
    }

    $this->alterRows($rows, $ids);

    $this->addElement('checkbox', 'toggleSelect', NULL, NULL, ['class' => 'select-rows']);
    $this->assign('rows', $rows);
    $this->assign('no_result_text', $this->getNoResultText());
    $this->assign('showLink', $showLink);
    $this->assign('debug_info', $this->dataProcessorClass->getDataFlow()->getDebugInformation());

    if ($this->usePrevNextCache()) {
      $cacheKey = "civicrm search {$this->controller->_key}";
      CRM_DataprocessorSearch_Utils_PrevNextCache::fillWithArray($cacheKey, $prevnextData);
    } else {
      $this->retrieveEntityIds();
    }
  }

  /**
   * Function to retrieve the entity ids
   */
  protected function retrieveEntityIds() {
    // Could be overriden in child classes.
  }

  /**
   * Add the headers for the columns
   *
   * @return array
   *   Array with all possible sort fields.
   *
   * @throws \Civi\DataProcessor\DataFlow\InvalidFlowException
   */
  protected function addColumnHeaders() {
    $sortFields = array();
    $hiddenFields = $this->getHiddenFields();
    $columnHeaders = array();
    $sortColumnNr = 2; // Start at two as 1 is the default sort.
    foreach($this->dataProcessorClass->getDataFlow()->getOutputFieldHandlers() as $outputFieldHandler) {
      $field = $outputFieldHandler->getOutputFieldSpecification();
      if (!in_array($field->alias, $hiddenFields)) {
        $columnHeaders[$field->alias] = $field->title;
        if ($outputFieldHandler instanceof \Civi\DataProcessor\FieldOutputHandler\OutputHandlerSortable) {
          $sortFields[$sortColumnNr] = array(
            'name' => $field->title,
            'sort' => $field->alias,
            'direction' => CRM_Utils_Sort::DONTCARE,
          );
          $sortColumnNr++;
        }
      }
    }
    $this->assign('columnHeaders', $columnHeaders);
    return $sortFields;
  }

  /**
   * @return array
   */
  protected function getPagerParams() {
    $params = [];
    $params['total'] = 0;
    $params['status'] =E::ts('%%StatusMessage%%');
    $params['csvString'] = NULL;
    $params['rowCount'] =  $this->getDefaultLimit();
    $params['buttonTop'] = 'PagerTopButton';
    $params['buttonBottom'] = 'PagerBottomButton';
    return $params;
  }

  /**
   * Add buttons for other outputs of this data processor
   */
  protected function addExportOutputs() {
    $factory = dataprocessor_get_factory();
    $outputs = civicrm_api3('DataProcessorOutput', 'get', array('data_processor_id' => $this->dataProcessorId, 'options' => array('limit' => 0)));
    $otherOutputs = array();
    foreach($outputs['values'] as $output) {
      if ($output['id'] == $this->dataProcessorOutput['id']) {
        continue;
      }
      $outputClass = $factory->getOutputByName($output['type']);
      if ($outputClass instanceof \Civi\DataProcessor\Output\ExportOutputInterface) {
        $otherOutput = array();
        $otherOutput['title'] = $outputClass->getTitleForExport($output, $this->dataProcessor);
        $otherOutput['id'] = $output['id'];
        $otherOutput['icon'] = $outputClass->getExportFileIcon($output, $this->dataProcessor);
        $otherOutputs[] = $otherOutput;
      }
    }
    $this->add('hidden', 'export_id');
    $this->assign('other_outputs', $otherOutputs);
  }

  public function buildQuickForm() {
    parent::buildQuickForm();

    $this->buildCriteriaForm();

    $selectedIds = $this->getSelectedIds();
    $this->assign_by_ref('selectedIds', $selectedIds);
    $this->add('hidden', 'context');
    $this->add('hidden', CRM_Utils_Sort::SORT_ID);
  }

  public function setDefaultValues() {
    $defaults = parent::setDefaultValues();
    $defaults['context'] = 'search';
    if ($this->sort && $this->sort->getCurrentSortID()) {
      $defaults[CRM_Utils_Sort::SORT_ID] = CRM_Utils_Sort::sortIDValue($this->sort->getCurrentSortID(), $this->sort->getCurrentSortDirection());
    }
    return $defaults;
  }

  public function validate() {
    $this->_errors = $this->validateFilters();
    return parent::validate();
  }

  public function postProcess() {
    if ($this->_done) {
      return;
    }
    $this->_done = TRUE;

    //for prev/next pagination
    $crmPID = CRM_Utils_Request::retrieve('crmPID', 'Integer');

    if (($this->_searchButtonName && array_key_exists($this->_searchButtonName, $_POST)) ||
      ($this->_force && !$crmPID)
    ) {
      //reset the cache table for new search
      $cacheKey = "civicrm search {$this->controller->_key}";
      CRM_DataprocessorSearch_Utils_PrevNextCache::deleteItem(NULL, $cacheKey);
    }

    if (!empty($_POST)) {
      $this->_formValues = $this->controller->exportValues($this->_name);
    }
    $this->set('formValues', $this->_formValues);
    $buttonName = $this->controller->getButtonName();
    if ($buttonName && $buttonName == $this->_actionButtonName) {
      // check actionName and if next, then do not repeat a search, since we are going to the next page
      // hack, make sure we reset the task values
      $formName = $this->controller->getStateMachine()->getTaskFormName();
      $this->controller->resetPage($formName);
      return;
    }
  }

  /**
   * Return a descriptive name for the page, used in wizard header
   *
   * @return string
   */
  public function getTitle() {
    $this->loadDataProcessor();
    return $this->dataProcessor['title'];
  }

  /**
   * Alter the data processor.
   *
   * Use this function in child classes to add for example additional filters.
   *
   * E.g. The contact summary tab uses this to add additional filtering on the contact id of
   * the displayed contact.
   *
   * @param \Civi\DataProcessor\ProcessorType\AbstractProcessorType $dataProcessorClass
   */
  protected function alterDataProcessor(AbstractProcessorType $dataProcessorClass) {

  }

  /**
   * Returns the selected IDs.
   *
   * @return array
   */
  protected function getSelectedIds() {
    $selectedIds = [];
    $qfKeyParam = CRM_Utils_Array::value('qfKey', $this->_formValues);
    if (empty($qfKeyParam) && $this->controller->_key) {
      $qfKeyParam = $this->controller->_key;
    }
    // We use ajax to handle selections only if the search results component_mode is set to "contacts"
    if ($this->usePrevNextCache()) {
      $this->addClass('crm-ajax-selection-form');
      if ($qfKeyParam) {
        $qfKeyParam = "civicrm search {$qfKeyParam}";
        $selectedIdsArr = CRM_DataprocessorSearch_Utils_PrevNextCache::getSelection($qfKeyParam);
        if (isset($selectedIdsArr[$qfKeyParam]) && is_array($selectedIdsArr[$qfKeyParam])) {
          $selectedIds = array_keys($selectedIdsArr[$qfKeyParam]);
        }
      }
    } else {
      if (isset($this->_formValues['radio_ts']) && $this->_formValues['radio_ts'] == 'ts_sel') {
        foreach ($this->_formValues as $name => $value) {
          if (substr($name, 0, CRM_Core_Form::CB_PREFIX_LEN) == CRM_Core_Form::CB_PREFIX) {
            $selectedIds[] = substr($name, CRM_Core_Form::CB_PREFIX_LEN);
          }
        }
      }
    }
    return $selectedIds;
  }

}
