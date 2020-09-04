<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

namespace Civi\DataProcessor\ProcessorType;

use Civi\DataProcessor\DataFlow\CombinedDataFlow\CombinedDataFlow;
use Civi\DataProcessor\DataFlow\CombinedDataFlow\CombinedSqlDataFlow;
use Civi\DataProcessor\DataFlow\CombinedDataFlow\SqlCombineSpecification;
use Civi\DataProcessor\DataFlow\MultipleDataFlows\DataFlowDescription;
use Civi\DataProcessor\DataFlow\MultipleDataFlows\JoinInterface;
use Civi\DataProcessor\DataFlow\MultipleDataFlows\JoinSpecification;
use Civi\DataProcessor\DataFlow\SqlDataFlow;
use Civi\DataProcessor\DataSpecification\FieldSpecification;
use Civi\DataProcessor\Event\OutputHandlerEvent;
use Civi\DataProcessor\FieldOutputHandler\AbstractFieldOutputHandler;
use Civi\DataProcessor\FilterHandler\AbstractFilterHandler;
use Civi\DataProcessor\Storage\StorageInterface;

abstract class AbstractProcessorType {

  /**
   * @var array
   */
  protected $dataSources = array();

  /**
   * @var bool
   */
  protected $allSqlDataFlows = true;

  /**
   * @var \Civi\DataProcessor\Storage\StorageInterface|null
   */
  protected $storage = null;

  /**
   * @var \Civi\DataProcessor\DataFlow\AbstractDataFlow|null
   */
  protected $dataflow = null;

  /**
   * @var \Civi\DataProcessor\FieldOutputHandler\AbstractFieldOutputHandler[]
   */
  protected $outputFieldHandlers;

  /**
   * @var \Civi\DataProcessor\FilterHandler\AbstractFilterHandler[]
   */
  protected $filterHandlers;

  /**
   * Add a data source to the processor
   * @param \Civi\DataProcessor\Source\SourceInterface $datasource
   * @param \Civi\DataProcessor\DataFlow\MultipleDataFlows\JoinInterface|NULL $combineSpecification
   */
  public function addDataSource(\Civi\DataProcessor\Source\SourceInterface $datasource, JoinInterface $combineSpecification=null) {
    $d['datasource'] = $datasource;
    $d['combine_specification'] = $combineSpecification;
    $this->dataSources[] = $d;
    if (!$datasource->getDataFlow() instanceof SqlDataFlow) {
      $this->allSqlDataFlows = false;
    }
  }

  /**
   * @return \Civi\DataProcessor\Source\SourceInterface[]
   */
  public function getDataSources() {
    $return = array();
    foreach($this->dataSources as $d) {
      $return[] = $d['datasource'];
    }
    return $return;
  }

  /**
   * @param $alias
   *
   * @return null|\Civi\DataProcessor\Source\SourceInterface
   */
  public function getDataSourceByFieldAlias($alias) {
    foreach($this->dataSources as $dataSource) {
      foreach($dataSource['datasource']->getAvailableFields()->getFields() as $field) {
        if ($field->alias == $alias) {
          return $dataSource['datasource'];
        }
      }
    }
    return null;
  }

  /**
   * Returns the data source
   *
   * @param $source_name
   * @return null|\Civi\DataProcessor\Source\SourceInterface
   */
  public function getDataSourceByName($source_name) {
    foreach($this->dataSources as $dataSource) {
      if ($dataSource['datasource']->getSourceName() == $source_name) {
        return $dataSource['datasource'];
      }
    }
    return null;
  }

  /**
   * @param \Civi\DataProcessor\FieldOutputHandler\AbstractFieldOutputHandler $outputFieldHandler
   */
  public function addOutputFieldHandlers(AbstractFieldOutputHandler $outputFieldHandler) {
    $this->outputFieldHandlers[] = $outputFieldHandler;
    if ($this->dataflow) {
      $this->dataflow->setOutputFieldHandlers($this->outputFieldHandlers);
    }
  }

  /**
   * @param \Civi\DataProcessor\FilterHandler\AbstractFilterHandler $filterHandler
   */
  public function addFilterHandler(AbstractFilterHandler $filterHandler) {
    $this->filterHandlers[] = $filterHandler;
  }

  /**
   * @return \Civi\DataProcessor\FilterHandler\AbstractFilterHandler[]
   */
  public function getFilterHandlers() {
    if (!$this->filterHandlers || !is_array($this->filterHandlers)) {
      $this->filterHandlers = array();
    }
    return $this->filterHandlers;
  }

  public function ensureFieldInDataSource(FieldSpecification $fieldSpecification) {
    foreach($this->dataSources as $dataSource) {
      $dataSource['datasource']->ensureFieldInSource($fieldSpecification);
    }
  }

  /**
   * Sets the storage of the data processor.
   *
   * @param \Civi\DataProcessor\Storage\StorageInterface $storage
   */
  public function setStorage(StorageInterface $storage) {
    $this->storage = $storage;
  }

  /**
   * @return \Civi\DataProcessor\DataFlow\AbstractDataFlow
   * @throws \Civi\DataProcessor\DataFlow\InvalidFlowException
   */
  public function getDataFlow() {
    if (!$this->dataflow) {
      if (count($this->dataSources) === 1) {
        $dataflow = $this->dataSources[0]['datasource']->getDataFlow();
      }
      else {
        if ($this->allSqlDataFlows) {
          $dataflow = new CombinedSqlDataFlow();
        }
        else {
          $dataflow = new CombinedDataFlow();
        }
        foreach ($this->dataSources as $datasource) {
          $dataFlowDescription = new DataFlowDescription($datasource['datasource']->getDataFlow(), $datasource['combine_specification']);
          $dataflow->addSourceDataFlow($dataFlowDescription);
        }
      }

      if ($this->storage) {
        $this->storage->setSourceDataFlow($dataflow);
        $this->dataflow = $this->storage->getDataFlow();
      } else {
        $this->dataflow = $dataflow;
      }

      $this->dataflow->setOutputFieldHandlers($this->outputFieldHandlers);
    }
    return $this->dataflow;
  }

  /**
   * Sets the default filter values for all filters and calls loadFromCache on a source.
   *
   * @throws \Exception
   */
  public function loadedFromCache() {
    if ($this->dataSources && is_array($this->dataSources)) {
      foreach ($this->dataSources as $dataSource) {
        $dataSource['datasource']->sourceLoadedFromCache();
      }
    }

    if ($this->filterHandlers && is_array($this->filterHandlers)) {
      foreach ($this->filterHandlers as $filterHandler) {
        $filterHandler->setDefaultFilterValues();
      }
    }
  }

}
