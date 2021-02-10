<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

use CRM_Dataprocessor_ExtensionUtil as E;

class CRM_DataprocessorOutputExport_CSV extends CRM_DataprocessorOutputExport_AbstractOutputExport {

  /**
   * Returns the directory name for storing temporary files.
   *
   * @return String
   */
  public function getDirectory() {
    return 'dataprocessor_export_csv';
  }

  /**
   * Returns the file extension.
   *
   * @return String
   */
  public function getExtension() {
    return 'csv';
  }

  /**
   * When this filter type has additional configuration you can add
   * the fields on the form with this function.
   *
   * @param \CRM_Core_Form $form
   * @param array $filter
   */
  public function buildConfigurationForm(\CRM_Core_Form $form, $output=array()) {
    parent::buildConfigurationForm($form, $output);
    $form->add('text', 'delimiter', E::ts('Delimiter'), array(), true);
    $form->add('text', 'enclosure', E::ts('Enclosure'), array(), true);
    $form->add('text', 'escape_char', E::ts('Escape char'), array(), true);

    $configuration = false;
    if ($output && isset($output['configuration'])) {
      $configuration = $output['configuration'];
    }
    if ($configuration && isset($configuration['delimiter']) && $configuration['delimiter']) {
      $defaults['delimiter'] = $configuration['delimiter'];
    } else {
      $defaults['delimiter'] = ';';
    }
    if ($configuration && isset($configuration['enclosure']) && $configuration['enclosure']) {
      $defaults['enclosure'] = $configuration['enclosure'];
    } else {
      $defaults['enclosure'] = '"';
    }
    if ($configuration && isset($configuration['escape_char']) && $configuration['escape_char']) {
      $defaults['escape_char'] = $configuration['escape_char'];
    } else {
      $defaults['escape_char'] = '\\';
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
    return "CRM/DataprocessorOutputExport/Form/Configuration/CSV.tpl";
  }


  /**
   * Process the submitted values and create a configuration array
   *
   * @param $submittedValues
   * @param array $output
   * @return array
   */
  public function processConfiguration($submittedValues, &$output) {
    $configuration = parent::processConfiguration($submittedValues, $output);
    $configuration['delimiter'] = $submittedValues['delimiter'];
    $configuration['enclosure'] = $submittedValues['enclosure'];
    $configuration['escape_char'] = $submittedValues['escape_char'];
    return $configuration;
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
   * Returns the mime type of the export file.
   *
   * @return string
   */
  public function mimeType() {
    return 'text/csv';
  }

  /**
   * Returns the url for the page/form this output will show to the user
   *
   * @param array $output
   * @param array $dataProcessor
   * @return string
   */
  public function getTitleForExport($output, $dataProcessor) {
    return E::ts('Download as CSV');
  }

  /**
   * Returns the url for the page/form this output will show to the user
   *
   * @param array $output
   * @param array $dataProcessor
   * @return string|false
   */
  public function getExportFileIcon($output, $dataProcessor) {
    return '<i class="fa fa-file-excel-o">&nbsp;</i>';
  }

  protected function createHeader($filename, \Civi\DataProcessor\ProcessorType\AbstractProcessorType $dataProcessorClass, $configuration, $dataProcessor, $idField=null, $selectedIds=array()) {
    $file = fopen($filename. '.'.$this->getExtension(), 'a');
    fwrite($file, "\xEF\xBB\xBF"); // BOF this will make sure excel opens the file correctly.
    $headerLine = array();
    foreach($dataProcessorClass->getDataFlow()->getOutputFieldHandlers() as $outputHandler) {
      $headerLine[] = self::encodeValue($outputHandler->getOutputFieldSpecification()->title, $configuration['escape_char'], $configuration['enclosure']);
    }
    fwrite($file, implode($configuration['delimiter'], $headerLine)."\r\n");
    fclose($file);
  }

  protected function exportDataProcessor($filename, \Civi\DataProcessor\ProcessorType\AbstractProcessorType $dataProcessor, $configuration, $idField=null, $selectedIds=array()) {
    $file = fopen($filename. '.'.$this->getExtension(), 'a');
    try {
      while($record = $dataProcessor->getDataFlow()->nextRecord()) {
        $row = array();
        $rowIsSelected = true;
        if (isset($idField) && is_array($selectedIds) && count($selectedIds)) {
          $rowIsSelected = false;
          $id = $record[$idField]->rawValue;
          if (in_array($id, $selectedIds)) {
            $rowIsSelected = true;
          }
        }
        if ($rowIsSelected) {
          foreach ($record as $field => $value) {
            $row[] = self::encodeValue($value->formattedValue, $configuration['escape_char'], $configuration['enclosure']);
          }
          fwrite($file, implode($configuration['delimiter'], $row) . "\r\n");
        }
      }
    } catch (\Civi\DataProcessor\DataFlow\EndOfFlowException $e) {
      // Do nothing
    }
    fclose($file);
  }

  protected static function encodeValue($value, $escape, $enclosure) {
    ///remove any ESCAPED double quotes within string.
    $value = str_replace("{$escape}{$enclosure}","{$enclosure}",$value);
    //then force escape these same double quotes And Any UNESCAPED Ones.
    $value = str_replace("{$enclosure}","{$escape}{$enclosure}",$value);
    //force wrap value in quotes and return
    return "{$enclosure}{$value}{$enclosure}";
  }


}
