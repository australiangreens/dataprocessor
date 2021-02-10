<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

namespace Civi\DataProcessor\DataFlow\MultipleDataFlows;

interface MultipleSourceDataFlows {

  /**
   * Adds a source data flow
   *
   * @param \Civi\DataProcessor\DataFlow\MultipleDataFlows\DataFlowDescription $dataFlowDescription
   * @return void
   */
  public function addSourceDataFlow(DataFlowDescription $dataFlowDescription);

  /**
   * Removes a source data flow
   *
   * @param \Civi\DataProcessor\DataFlow\MultipleDataFlows\DataFlowDescription $dataFlowDescription
   * @return void
   */
  public function removeSourceDataFlow(DataFlowDescription $dataFlowDescription);

}
