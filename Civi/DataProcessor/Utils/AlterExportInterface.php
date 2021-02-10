<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

namespace Civi\DataProcessor\Utils;

interface AlterExportInterface {

  /**
   * Function to alter the export data.
   * E.g. use this to convert ids to names
   *
   * @param array $data
   *
   * @return array
   */
  public function alterExportData($data);

  /**
   * Function to alter the export data.
   * E.g. use this to convert names to ids
   *
   * @param array $data
   *
   * @return array
   */
  public function alterImportData($data);

}
