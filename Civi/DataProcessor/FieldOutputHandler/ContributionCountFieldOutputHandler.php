<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

namespace Civi\DataProcessor\FieldOutputHandler;

use Civi\DataProcessor\Utils\AlterExportInterface;
use CRM_Dataprocessor_ExtensionUtil as E;

class ContributionCountFieldOutputHandler extends AbstractSimpleFieldOutputHandler implements AlterExportInterface {

  protected $financial_types = [];

  protected $contribution_status = [];

  /**
   * Initialize the processor
   *
   * @param String $alias
   * @param String $title
   * @param array $configuration
   * @param \Civi\DataProcessor\ProcessorType\AbstractProcessorType $processorType
   */
  public function initialize($alias, $title, $configuration) {
    parent::initialize($alias, $title, $configuration);
    if (isset($configuration['financial_type']) && is_array($configuration['financial_type'])) {
      $this->financial_types = $configuration['financial_type'];
    }
    if (isset($configuration['status']) && is_array($configuration['status'])) {
      $this->contribution_status = $configuration['status'];
    }
  }

  /**
   * Returns the data type of this field
   *
   * @return String
   */
  protected function getType() {
    return 'Integer';
  }

  /**
   * Returns the formatted value
   *
   * @param $rawRecord
   * @param $formattedRecord
   *
   * @return \Civi\DataProcessor\FieldOutputHandler\FieldOutput
   */
  public function formatField($rawRecord, $formattedRecord) {
    $contact_id = $rawRecord[$this->inputFieldSpec->alias];
    $contributions = \Civi\Api4\Contribution::get()->selectRowCount();
    if ($this->financial_types && is_array($this->financial_types) && count($this->financial_types)) {
      $contributions->addWhere('financial_type_id', 'IN', $this->financial_types);
    }
    if ($this->contribution_status && is_array($this->contribution_status) && count($this->contribution_status)) {
      $contributions->addWhere('contribution_status_id', 'IN', $this->contribution_status);
    }
    $contributions->addWhere('contact_id', '=', $contact_id);
    $contributions = $contributions->execute();
    $output = new FieldOutput($contributions->count());
    return $output;
  }

  /**
   * When this handler has configuration specify the template file name
   * for the configuration form.
   *
   * @return false|string
   */
  public function getConfigurationTemplateFileName() {
    return "CRM/Dataprocessor/Form/Field/Configuration/ContributionCountFieldOutputHandler.tpl";
  }

  /**
   * When this handler has additional configuration you can add
   * the fields on the form with this function.
   *
   * @param \CRM_Core_Form $form
   * @param array $field
   */
  public function buildConfigurationForm(\CRM_Core_Form $form, $field=array()) {
    parent::buildConfigurationForm($form, $field);
    $finanancialTypeOptions = [];
    $finanancialTypeApi = civicrm_api3('FinancialType', 'get', ['is_active' => '1', 'options' => ['limit' => 0]]);
    foreach($finanancialTypeApi['values'] as $financialType) {
      $finanancialTypeOptions[$financialType['id']] = $financialType['name'];
    }

    $statusOptions = [];
    $statusApi = \Civi\Api4\OptionValue::get()
      ->addWhere('option_group_id:name', '=', 'contribution_status')
      ->addWhere('is_active', '=', TRUE)
      ->execute();
    foreach($statusApi as $status) {
      $statusOptions[$status['value']] = $status['label'];
    }

    $form->add('select', 'financial_type', E::ts('Financial Type'), $finanancialTypeOptions, false, array(
      'style' => 'min-width:250px',
      'class' => 'crm-select2 huge',
      'multiple' => 'multiple',
      'placeholder' => E::ts('- select -'),
    ));

    $form->add('select', 'status', E::ts('Contribution Status'), $statusOptions, false, array(
      'style' => 'min-width:250px',
      'class' => 'crm-select2 huge',
      'multiple' => 'multiple',
      'placeholder' => E::ts('- select -'),
    ));
    if (isset($field['configuration'])) {
      $configuration = $field['configuration'];
      $defaults = array();
      if (isset($configuration['financial_type'])) {
        $defaults['financial_type'] = $configuration['financial_type'];
      }
      if (isset($configuration['status'])) {
        $defaults['status'] = $configuration['status'];
      }
      $form->setDefaults($defaults);
    }
  }

  /**
   * Process the submitted values and create a configuration array
   *
   * @param $submittedValues
   * @return array
   */
  public function processConfiguration($submittedValues) {
    $configuration = parent::processConfiguration($submittedValues);
    if (isset($submittedValues['financial_type'])) {
      $configuration['financial_type'] = $submittedValues['financial_type'];
    } else {
      $configuration['financial_type'] = [];
    }
    if (isset($submittedValues['status'])) {
      $configuration['status'] = $submittedValues['status'];
    } else {
      $configuration['status'] = [];
    }
    return $configuration;
  }

  /**
   * Function to alter the export data.
   * E.g. use this to convert ids to names
   *
   * @param array $data
   *
   * @return array
   */
  public function alterExportData($data) {
    $finanancialTypeOptions = [];
    $finanancialTypeApi = civicrm_api3('FinancialType', 'get', ['is_active' => '1', 'options' => ['limit' => 0]]);
    foreach($finanancialTypeApi['values'] as $financialType) {
      $finanancialTypeOptions[$financialType['id']] = $financialType['name'];
    }

    $statusOptions = [];
    $statusApi = \Civi\Api4\OptionValue::get()
      ->addWhere('option_group_id:name', '=', 'contribution_status')
      ->addWhere('is_active', '=', TRUE)
      ->execute();
    foreach($statusApi as $status) {
      $statusOptions[$status['value']] = $status['name'];
    }

    $financialTypes = [];
    if (isset($data['configuration']['financial_type']) && is_array($data['configuration']['financial_type'])) {
      foreach($data['configuration']['financial_type'] as $financial_type_id) {
        $financialTypes[] = $finanancialTypeOptions[$financial_type_id];
      }
    }
    $data['configuration']['financial_type'] = $financialTypes;

    $statuses = [];
    if (isset($data['configuration']['status']) && is_array($data['configuration']['status'])) {
      foreach($data['configuration']['status'] as $status_id) {
        $statuses[] = $statusOptions[$status_id];
      }
    }
    $data['configuration']['status'] = $statuses;

    return $data;
  }

  /**
   * Function to alter the export data.
   * E.g. use this to convert names to ids
   *
   * @param array $data
   *
   * @return array
   */
  public function alterImportData($data) {
    $finanancialTypeOptions = [];
    $finanancialTypeApi = civicrm_api3('FinancialType', 'get', ['is_active' => '1', 'options' => ['limit' => 0]]);
    foreach($finanancialTypeApi['values'] as $financialType) {
      $finanancialTypeOptions[$financialType['name']] = $financialType['id'];
    }

    $statusOptions = [];
    $statusApi = \Civi\Api4\OptionValue::get()
      ->addWhere('option_group_id:name', '=', 'contribution_status')
      ->addWhere('is_active', '=', TRUE)
      ->execute();
    foreach($statusApi as $status) {
      $statusOptions[$status['name']] = $status['value'];
    }

    $financialTypes = [];
    if (isset($data['configuration']['financial_type']) && is_array($data['configuration']['financial_type'])) {
      foreach($data['configuration']['financial_type'] as $financial_type_name) {
        $financialTypes[] = $finanancialTypeOptions[$financial_type_name];
      }
    }
    $data['configuration']['financial_type'] = $financialTypes;

    $statuses = [];
    if (isset($data['configuration']['status']) && is_array($data['configuration']['status'])) {
      foreach($data['configuration']['status'] as $status_name) {
        $statuses[] = $statusOptions[$status_name];
      }
    }
    $data['configuration']['status'] = $statuses;

    return $data;
  }


}
