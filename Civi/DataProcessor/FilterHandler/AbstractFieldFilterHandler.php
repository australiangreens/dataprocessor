<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

namespace Civi\DataProcessor\FilterHandler;

use Civi\DataProcessor\DataFlow\InMemoryDataFlow;
use Civi\DataProcessor\DataFlow\SqlDataFlow;
use Civi\DataProcessor\Exception\DataSourceNotFoundException;
use Civi\DataProcessor\Exception\FieldNotFoundException;
use CRM_Dataprocessor_ExtensionUtil as E;

abstract class AbstractFieldFilterHandler extends AbstractFilterHandler {

  /**
   * @var \Civi\DataProcessor\DataSpecification\FieldSpecification
   */
  protected $fieldSpecification;

  /**
   * @var \Civi\DataProcessor\DataSpecification\FieldSpecification
   */
  protected $inputFieldSpecification;

  /**
   * @var \Civi\DataProcessor\Source\SourceInterface
   */
  protected $dataSource;

  /**
   * @var \Civi\DataProcessor\DataFlow\SqlDataFlow\WhereClauseInterface
   */
  protected $whereClause;


  /**
   * @var \Civi\DataProcessor\DataFlow\InMemoryDataFlow\SimpleFilter
   */
  protected $filterClass;

  /**
   * @param $datasource_name
   * @param $field_name
   *
   * @throws \Civi\DataProcessor\Exception\DataSourceNotFoundException
   * @throws \Civi\DataProcessor\Exception\FieldNotFoundException
   */
  protected function initializeField($datasource_name, $field_name) {
    $this->dataSource = $this->data_processor->getDataSourceByName($datasource_name);
    if (!$this->dataSource) {
      throw new DataSourceNotFoundException(E::ts("Filter %1 requires data source '%2' which could not be found. Did you rename or deleted the data source?", array(1=>$this->title, 2=>$datasource_name)));
    }
    $this->inputFieldSpecification = $this->dataSource->getAvailableFilterFields()->getFieldSpecificationByAlias($field_name);
    if (!$this->inputFieldSpecification) {
      $this->inputFieldSpecification = $this->dataSource->getAvailableFilterFields()->getFieldSpecificationByName($field_name);
    }
    if (!$this->inputFieldSpecification) {
      throw new FieldNotFoundException(E::ts("Filter %1 requires a field with the name '%2' in the data source '%3'. Did you change the data source type?", array(
        1 => $this->title,
        2 => $field_name,
        3 => $datasource_name
      )));
    }
    $this->fieldSpecification = clone $this->inputFieldSpecification;
    $this->fieldSpecification->alias = $this->alias;
    $this->fieldSpecification->title = $this->title;
  }


  /**
   * @return \Civi\DataProcessor\DataSpecification\FieldSpecification
   */
  public function getFieldSpecification() {
    return $this->fieldSpecification;
  }

  /**
   * Resets the filter
   *
   * @return void
   * @throws \Exception
   */
  public function resetFilter() {
    if (!$this->isInitialized()) {
      return;
    }
    $dataFlow  = $this->dataSource->ensureField($this->fieldSpecification);
    if ($dataFlow && $dataFlow instanceof SqlDataFlow && isset($this->whereClause)) {
      $dataFlow->removeWhereClause($this->whereClause);
      unset($this->whereClause);
    } elseif ($dataFlow && $dataFlow instanceof InMemoryDataFlow && $this->filterClass) {
      $dataFlow->removeFilter($this->filterClass);
      unset($this->filter);
    }
  }

  /**
   * @param array $filter
   *   The filter settings
   * @return mixed
   * @throws \Exception
   */
  public function setFilter($filter) {
    $this->resetFilter();
    $dataFlow  = $this->dataSource->ensureField($this->inputFieldSpecification);
    if ($dataFlow && $dataFlow instanceof SqlDataFlow) {
      $value = $filter['value'];
      if (!is_array($value)) {
        $value = explode(",", $value);
      }
      $tableAlias = $this->getTableAlias($dataFlow);
      $this->whereClause = new SqlDataFlow\SimpleWhereClause($tableAlias, $this->inputFieldSpecification->getName(), $filter['op'], $value, $this->inputFieldSpecification->type);
      $dataFlow->addWhereClause($this->whereClause);
    } elseif ($dataFlow && $dataFlow instanceof InMemoryDataFlow) {
      $this->filterClass = new InMemoryDataFlow\SimpleFilter($this->inputFieldSpecification->getName(), $filter['op'], $filter['value']);
      $dataFlow->addFilter($this->filterClass);
    }
  }

}
