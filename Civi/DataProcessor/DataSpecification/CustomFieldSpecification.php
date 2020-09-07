<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

namespace Civi\DataProcessor\DataSpecification;

class CustomFieldSpecification extends FieldSpecification {

  public $customGroupName;

  public $customGroupTableName;

  public $customFieldColumnName;

  public $customFieldName;

  public $customFieldTitle;

  public $customGroupTitle;

  public $customFieldId;

  public $customField;

  public function __construct($custom_group_name, $custom_group_table_name, $custom_group_title, $field, $alias) {
    $this->customField = $field;
    $this->customFieldColumnName = $field['column_name'];
    $this->customGroupName = $custom_group_name;
    $this->customGroupTableName = $custom_group_table_name;
    $this->customGroupTitle = $custom_group_title;
    $this->customFieldId = $field['id'];
    $this->customFieldName = $field['name'];
    $this->customFieldTitle = $field['label'];

    $options = \CRM_Core_PseudoConstant::get('CRM_Core_BAO_CustomField', 'custom_' . $this->customFieldId, array(), 'search');
    parent::__construct($alias, $field['data_type'], $custom_group_title. ': '.$this->customFieldTitle, $options, $alias);

  }

  /**
   * Returns whether this field is a multiple select field.
   *
   * @return bool
   */
  public function isMultiple() {
    if (!$this->getOptions()) {
      return false;
    }
    if ($this->type == 'Boolean') {
      return false;
    }
    if ($this->customField['html_type'] == 'Radio') {
      return false;
    }
    if ($this->customField['html_type'] == 'Select') {
      return false;
    }
    return true;
  }

  /**
   * @return String
   */
  public function getName() {
    return $this->customFieldColumnName;
  }

}
