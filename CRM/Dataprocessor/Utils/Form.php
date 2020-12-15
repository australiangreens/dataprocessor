<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

/**
 * Helper function for form elements.
 *
 * This is introduced so are sure the addDataPickerRange function is compatible with both
 * civicrm before 5.25.0 and after 5.25.0
 *
 * Class CRM_Dataprocessor_Utils_Form
 */
class CRM_Dataprocessor_Utils_Form {

  /**
   * Add a search for a range using date picker fields.
   *
   * @param CRM_Core_Form $form
   * @param string $fieldName
   * @param string $label
   * @param bool $isDateTime
   *   Is this a date-time field (not just date).
   * @param bool $required
   * @param string $fromLabel
   * @param string $toLabel
   * @param array $additionalOptions
   * @param string $to string to append to the to field.
   * @param string $from string to append to the from field.
   */
  public static function addDatePickerRange($form, $fieldName, $label, $isDateTime = FALSE, $required = FALSE, $fromLabel = 'From', $toLabel = 'To', $additionalOptions = [], $to = '_high', $from = '_low') {
    $options = [
        '' => ts('- any -'),
        0 => ts('Choose Date Range'),
      ] + CRM_Core_OptionGroup::values('relative_date_filters');

    if ($additionalOptions) {
      foreach ($additionalOptions as $key => $optionLabel) {
        $options[$key] = $optionLabel;
      }
    }

    $form->add('select',
      "{$fieldName}_relative",
      $label,
      $options,
      $required,
      ['class' => 'crm-select2']
    );
    $attributes = ['formatType' => 'searchDate'];
    $extra = ['time' => $isDateTime];
    $form->add('datepicker', $fieldName . $from, ts($fromLabel), $attributes, $required, $extra);
    $form->add('datepicker', $fieldName . $to, ts($toLabel), $attributes, $required, $extra);
  }

}
