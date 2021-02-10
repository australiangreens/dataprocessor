<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

use CRM_Dataprocessor_ExtensionUtil as E;

class CRM_DataprocessorOutputExport_PDF extends CRM_DataprocessorOutputExport_AbstractOutputExport {

  /**
   * Returns the directory name for storing temporary files.
   *
   * @return String
   */
  public function getDirectory() {
    return 'dataprocessor_export_pdf';
  }

  /**
   * Returns the file extension.
   *
   * @return String
   */
  public function getExtension() {
    return 'pdf';
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
    $defaults = [];
    $dataProcessor = civicrm_api3('DataProcessor', 'getsingle', array('id' => $output['data_processor_id']));
    $dataProcessorClass = \CRM_Dataprocessor_BAO_DataProcessor::dataProcessorToClass($dataProcessor);
    $fields = array();
    foreach($dataProcessorClass->getDataFlow()->getOutputFieldHandlers() as $outputFieldHandler) {
      $field = $outputFieldHandler->getOutputFieldSpecification();
      $fields[$field->alias] = $field->title;
    }

    $pdfFormats = array();
    $pdfFormatsApi = civicrm_api3('OptionValue', 'get', ['option_group_id' => 'pdf_format', 'options' => ['limit' => 0]]);
    foreach($pdfFormatsApi['values'] as $pdfFormat) {
      $pdfFormats[$pdfFormat['id']] = $pdfFormat['label'];
    }

    $smarty = \CRM_Core_Smarty::singleton();
    $templates = [];
    $template_dirs = $smarty->template_dir;
    if (!is_array($template_dirs)) {
      $template_dirs = [$template_dirs];
    }
    foreach($template_dirs as $template_dir) {
      foreach(glob($template_dir."/CRM/DataprocessorOutputExport/PDF/*") as $fileName) {
        if (is_dir($fileName)) {
          $template = basename($fileName);
          $title = $template;
          if (file_exists($fileName."/title.txt")) {
            $title = E::ts(file_get_contents($fileName."/title.txt"));
          }
          $templates[$template] = $title;
        }
      }
    }
    $defaults['template'] = key($templates);

    $form->add('select', 'template', E::ts('Template'), $templates, false, array(
      'style' => 'min-width:250px',
      'class' => 'crm-select2 huge',
      'multiple' => false,
      'placeholder' => E::ts('- select -'),
    ));

    $form->add('select', 'header_fields', E::ts('Header fields'), $fields, false, array(
      'style' => 'min-width:250px',
      'class' => 'crm-select2 huge',
      'multiple' => true,
      'placeholder' => E::ts('- select -'),
    ));
    $form->add('checkbox', 'header_after_section', E::ts('Show header after each section'), array(), false);

    $form->add('select', 'hidden_fields', E::ts('Hidden fields'), $fields, false, array(
      'style' => 'min-width:250px',
      'class' => 'crm-select2 huge',
      'multiple' => true,
      'placeholder' => E::ts('- select -'),
    ));

    $form->add('select', 'section_titles', E::ts('Section Titles'), $fields, false, array(
      'style' => 'min-width:250px',
      'class' => 'crm-select2 huge',
      'multiple' => true,
      'placeholder' => E::ts('- no section titles -'),
    ));

    $form->add('select', 'pdf_format', E::ts('PDF Format'), $pdfFormats, false, array(
      'style' => 'min-width:250px',
      'class' => 'crm-select2 huge',
      'multiple' => false,
      'placeholder' => E::ts('- Default PDF Format -'),
    ));
    $form->assign('ManagePdfFormatUrl', CRM_Utils_System::url('civicrm/admin/pdfFormats', ['reset'=>1]));

    $form->add('wysiwyg', 'header', E::ts('Header'), array('rows' => 6, 'cols' => 80));

    $form->add('checkbox', 'additional_column', E::ts('Add an additional column'), array(), false);
    $form->add('text', 'additional_column_title', E::ts('Additional column title'));
    $form->add('text', 'additional_column_width', E::ts('Additional column width'));
    $form->add('text', 'additional_column_height', E::ts('Additional column height'));

    $configuration = false;
    if ($output && isset($output['configuration'])) {
      $configuration = $output['configuration'];
    }
    if ($configuration && isset($configuration['header_fields'])) {
      $defaults['header_fields'] = $configuration['header_fields'];
    }
    if ($configuration && isset($configuration['header_after_section'])) {
      $defaults['header_after_section'] = $configuration['header_after_section'];
    }
    if ($configuration && isset($configuration['hidden_fields'])) {
      $defaults['hidden_fields'] = $configuration['hidden_fields'];
    }
    if ($configuration && isset($configuration['section_titles'])) {
      $defaults['section_titles'] = $configuration['section_titles'];
    }
    if ($configuration && isset($configuration['pdf_format'])) {
      $defaults['pdf_format'] = $configuration['pdf_format'];
    }
    if ($configuration && isset($configuration['header'])) {
      $defaults['header'] = $configuration['header'];
    }
    if ($configuration && isset($configuration['additional_column'])) {
      $defaults['additional_column'] = $configuration['additional_column'];
    }
    if ($configuration && isset($configuration['additional_column_title'])) {
      $defaults['additional_column_title'] = $configuration['additional_column_title'];
    }
    if ($configuration && isset($configuration['additional_column_width'])) {
      $defaults['additional_column_width'] = $configuration['additional_column_width'];
    }
    if ($configuration && isset($configuration['additional_column_height'])) {
      $defaults['additional_column_height'] = $configuration['additional_column_height'];
    }
    if ($configuration && isset($configuration['template'])) {
      $defaults['template'] = $configuration['template'];
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
    return "CRM/DataprocessorOutputExport/Form/Configuration/PDF.tpl";
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
    $configuration['header_fields'] = $submittedValues['header_fields'];
    $configuration['header_after_section'] = isset($submittedValues['header_after_section']) ? $submittedValues['header_after_section'] : false;
    $configuration['hidden_fields'] = $submittedValues['hidden_fields'];
    $configuration['section_titles'] = $submittedValues['section_titles'];
    $configuration['pdf_format'] = $submittedValues['pdf_format'];
    $configuration['header'] = $submittedValues['header'];
    $configuration['additional_column'] = isset($submittedValues['additional_column']) ? $submittedValues['additional_column'] : false;
    $configuration['additional_column_title'] = $submittedValues['additional_column_title'];
    $configuration['additional_column_width'] = $submittedValues['additional_column_width'];
    $configuration['additional_column_height'] = $submittedValues['additional_column_height'];
    $configuration['template'] = $submittedValues['template'];
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
    return 'application/pdf';
  }

  /**
   * Returns the url for the page/form this output will show to the user
   *
   * @param array $output
   * @param array $dataProcessor
   * @return string
   */
  public function getTitleForExport($output, $dataProcessor) {
    return E::ts('Download as PDF');
  }

  /**
   * Returns the url for the page/form this output will show to the user
   *
   * @param array $output
   * @param array $dataProcessor
   * @return string|false
   */
  public function getExportFileIcon($output, $dataProcessor) {
    return '<i class="fa fa-file-pdf-o">&nbsp;</i>';
  }

  protected function createFooter($filename, \Civi\DataProcessor\ProcessorType\AbstractProcessorType $dataProcessorClass, $configuration, $dataProcessor, $idField=null, $selectedIds=array()) {
    $content = "";
    $headerContent = "";
    $showHeaderAfterEachSection = false;
    $hiddenFields = array();
    if (isset($configuration['hidden_fields']) && is_array($configuration['hidden_fields'])) {
      $hiddenFields = $configuration['hidden_fields'];
    }
    $headerFields = array();
    if (isset($configuration['header_fields']) && is_array($configuration['header_fields'])) {
      $headerFields = $configuration['header_fields'];
    }
    if (isset($configuration['header_after_section']) && $configuration['header_after_section']) {
      $showHeaderAfterEachSection = true;
    }

    $headerColumns = [];
    foreach($dataProcessorClass->getDataFlow()->getOutputFieldHandlers() as $outputHandler) {
      $headerColumns[$outputHandler->getOutputFieldSpecification()->alias] = $outputHandler->getOutputFieldSpecification()->title;
    }

    $smarty = \CRM_Core_Smarty::singleton();
    $smarty->pushScope(array());
    $smarty->assign('configuration', $configuration);
    $smarty->assign('hiddenFields', $hiddenFields);
    $smarty->assign('headerFields', $headerFields);
    $smarty->assign('headerColumns', $headerColumns);
    $smarty->assign('dataProcessor', $dataProcessor);

    $parts = [];
    foreach (glob($filename.".html.part.*") as $partFilename) {
      $headerPartFileName = str_replace(".part.", ".header_part.", $partFilename);
      $basePartFileName = basename($partFilename);
      $partName = substr($basePartFileName, stripos($basePartFileName, ".part.")+6);
      $partContent = file_get_contents($partFilename);
      $partHeaderContent = file_get_contents($headerPartFileName);
      if ($partName == "_none_") {
        $smarty->assign('sectionTitle', '');
        if ($showHeaderAfterEachSection) {
          $smarty->assign('header', $partHeaderContent);
        } elseif (empty($headerContent)) {
          $headerContent = $partHeaderContent;
        }
        $smarty->assign('rows', $partContent);
        $content .= $smarty->fetch(self::getTemplateFolder($configuration)."table.tpl");
        $smarty->popScope();
      } else {
        $parts[$partName]['content'] = $partContent;
        $parts[$partName]['header'] = $partHeaderContent;
      }
      unlink($partFilename);
      unlink($headerPartFileName);
    }

    foreach($parts as $sectionTitle => $section) {
      $smarty->assign('sectionTitle', $sectionTitle);
      $smarty->assign('rows', $section['content']);
      if ($showHeaderAfterEachSection) {
        $smarty->assign('header', $section['header']);
      } elseif (empty($headerContent)) {
        $headerContent = $section['header'];
      }
      $content .= $smarty->fetch(self::getTemplateFolder($configuration)."table.tpl");
      $smarty->popScope();
      unset($parts[$sectionTitle]);
    }
    $smarty->popScope();

    $smarty->pushScope(array());
    $smarty->assign('configuration', $configuration);
    $smarty->assign('dataProcessor', $dataProcessor);
    $smarty->assign('content', $content);
    if (!$showHeaderAfterEachSection && !empty($headerContent)) {
      $smarty->assign('header', $headerContent);
    }
    $content = $smarty->fetch(self::getTemplateFolder($configuration)."html.tpl");
    $smarty->popScope();

    $pdfFilename = $filename.'.'.$this->getExtension();
    $pdfFormat = isset($configuration['pdf_format']) ? $configuration['pdf_format'] : null;
    $pdfContents = \CRM_Utils_PDF_Utils::html2pdf($content, basename($pdfFilename), TRUE, $pdfFormat);
    $file = fopen($pdfFilename, 'a');
    fwrite($file, $pdfContents."\r\n");
    fclose($file);

    return $pdfFilename;
  }

  protected function exportDataProcessor($filename, \Civi\DataProcessor\ProcessorType\AbstractProcessorType $dataProcessor, $configuration, $idField=null, $selectedIds=array()) {
    $hiddenFields = array();
    if (isset($configuration['hidden_fields']) && is_array($configuration['hidden_fields'])) {
      $hiddenFields = $configuration['hidden_fields'];
    }
    $headerFields = array();
    if (isset($configuration['header_fields']) && is_array($configuration['header_fields'])) {
      $headerFields = $configuration['header_fields'];
    }
    $headerColumns = [];
    foreach($dataProcessor->getDataFlow()->getOutputFieldHandlers() as $outputHandler) {
      $headerColumns[$outputHandler->getOutputFieldSpecification()->alias] = $outputHandler->getOutputFieldSpecification()->title;
    }

    $headerContent = null;

    $smarty = \CRM_Core_Smarty::singleton();
    $smarty->pushScope(array());
    $smarty->assign('configuration', $configuration);
    $smarty->assign('hiddenFields', $hiddenFields);
    $smarty->assign('headerFields', $headerFields);
    $smarty->assign('headerColumns', $headerColumns);
    $smarty->assign('dataProcessor', $dataProcessor);

    if (!isset($configuration['section_titles']) || !is_array($configuration['section_titles'])) {
      $configuration['section_titles'] = array();
    }

    $contents = [];
    try {
      while($record = $dataProcessor->getDataFlow()->nextRecord()) {
        $row = array();
        $content = "";
        $rowIsSelected = true;
        if (isset($idField) && is_array($selectedIds) && count($selectedIds)) {
          $rowIsSelected = false;
          $id = $record[$idField]->rawValue;
          if (in_array($id, $selectedIds)) {
            $rowIsSelected = true;
          }
        }
        $smarty->assign('record', $record);
        if ($rowIsSelected) {
          $content = $smarty->fetch(self::getTemplateFolder($configuration)."row.tpl");
        }

        $sectionHeader = "";
        foreach($configuration['section_titles'] as $section_title) {
          $sectionHeader .= strip_tags($record[$section_title]->formattedValue)." ";
        }
        if (empty($sectionHeader)) {
          $sectionHeader = "_none_";
        }
        $sectionHeader = trim($sectionHeader);
        if (!isset($contents[$sectionHeader]['content'])) {
          $contents[$sectionHeader]['content'] = "";
        }
        if (!isset($contents[$sectionHeader]['header']) && count($headerFields)) {
          $contents[$sectionHeader]['header'] = $smarty->fetch(self::getTemplateFolder($configuration)."header.tpl");;
        }
        $contents[$sectionHeader]['content'] .= $content;
      }
    } catch (\Civi\DataProcessor\DataFlow\EndOfFlowException $e) {
      // Do nothing
    }
    foreach($contents as $sectionHeader => $content) {
      $file = fopen($filename.".html.part.".$sectionHeader, 'a');
      fwrite($file, $content['content'] . "\r\n");
      fclose($file);

      if (isset($content['header'])) {
        $file = fopen($filename.".html.header_part.".$sectionHeader, 'a');
        fwrite($file, $content['header'] . "\r\n");
        fclose($file);
      }
    }

    $smarty->popScope();
  }

  protected static function getTemplateFolder($configuration) {
    $template = "Default";
    if (isset($configuration['template'])) {
      $template = $configuration['template'];
    }
    return "CRM/DataprocessorOutputExport/PDF/{$template}/";
  }


}
