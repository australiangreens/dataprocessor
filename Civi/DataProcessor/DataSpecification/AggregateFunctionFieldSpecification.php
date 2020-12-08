<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

namespace Civi\DataProcessor\DataSpecification;

use Civi\DataProcessor\Exception\InvalidConfigurationException;
use CRM_Dataprocessor_ExtensionUtil as E;

/**
 * Class used to add aggregate functions (such as SUM) to a database query.
 *
 * @package Civi\DataProcessor\DataSpecification
 */
class AggregateFunctionFieldSpecification extends FieldSpecification implements Aggregatable {

  protected $function;

  /**
   * Aggregate the field in all the records and return the aggregated value.
   *
   * @param $records
   * @param string $fieldName
   *
   * @return mixed
   */
  public function aggregateRecords($records, $fieldName="") {
    $values = array();
    $value = 0;
    $processedValues = array();
    foreach($records as $record) {
      if (isset($record[$fieldName])) {
        switch ($this->function) {
          case 'SUM':
            $value += $record[$fieldName];
            break;
          case 'COUNT':
            $value ++;
            break;
          case 'COUNT_DISTINCT':
            if (!in_array($record[$fieldName], $processedValues)) {
              $value ++;
              $processedValues[] = $record[$fieldName];
            }
            break;
          case 'MIN':
            if ($record[$fieldName] < $value) {
              $value = $record[$fieldName];
            }
            break;
          case 'MAX':
            if ($record[$fieldName] > $value) {
              $value = $record[$fieldName];
            }
            break;
          case 'AVG':
          case 'STDDEV_POP':
          case 'STDDEV_SAMP':
            $values[] = $record[$fieldName];
            break;
        };
      }
    }
    switch ($this->function) {
      case 'AVG':
        $value = array_sum($values) / count($values);
        break;
      case 'STDDEV_POP':
        $value = $this->stats_standard_deviation($values, false);
        break;
      case 'VAR_POP':
        $avg = array_sum($values) / count($values);
        $value = array_sum(array_map(function ($x) use ($avg) {
            return pow($x - $avg, 2);
        }, $values)) / count($values);
        break;
    }

    return $value;
  }

  public function setAggregateFunction($function) {
    $functions = self::functionList();
    if (!isset($functions[$function])) {
      throw new InvalidConfigurationException(E::ts('Field %1 has an invalid aggregate function.', [1=>$this->title]));
    }
    $this->function = $function;
  }

  /**
   * Convert a fieldSpecification into the aggregate function field specification.
   *
   * @param \Civi\DataProcessor\DataSpecification\FieldSpecification $fieldSpecification
   * @param $function
   *
   * @return \Civi\DataProcessor\DataSpecification\AggregateFunctionFieldSpecification
   */
  public static function convertFromFieldSpecification(FieldSpecification $fieldSpecification, $function) {
    $return = new AggregateFunctionFieldSpecification($fieldSpecification->getName(), $fieldSpecification->type, $fieldSpecification->title, $fieldSpecification->options, $fieldSpecification->alias);
    $return->setAggregateFunction($function);
    return $return;
  }

  /**
   * Returns the select statement for this field.
   * E.g. COUNT(civicrm_contact.id) AS contact_id_count
   *
   * @param String $table_alias
   * @return string
   */
  public function getSqlSelectStatement($table_alias) {
    $function_arg = "";
    $function = $this->function;
    if ($function == 'COUNT_DISTINCT') {
      $function = 'COUNT';
      $function_arg = 'DISTINCT';
    }
    return "{$function}({$function_arg}`{$table_alias}`.`{$this->name}`) AS `{$this->alias}`";
  }

  /**
   * Returns the valid aggregate functions
   *
   * @return array
   */
  public static function functionList() {
    return [
      'SUM' => E::ts('Sum'),
      'AVG' => E::ts('Average'),
      'MIN' => E::ts('Minimum'),
      'MAX' => E::ts('Maximum'),
      'STDDEV_POP' => E::ts('Standard deviation'),
      'VAR_POP' => E::ts('Standard variance'),
      'COUNT' => E::ts('Count'),
      'COUNT_DISTINCT' => E::ts('Distinct count')
    ];
  }

  /**
   * Taken from https://www.php.net/manual/en/function.stats-standard-deviation.php#114473
   *
   * This user-land implementation follows the implementation quite strictly;
   * it does not attempt to improve the code or algorithm in any way. It will
   * raise a warning if you have fewer than 2 values in your array, just like
   * the extension does (although as an E_USER_WARNING, not E_WARNING).
   *
   * @param array $a
   * @param bool $sample [optional] Defaults to false
   * @return float|bool The standard deviation or false on error.
   */
  private function stats_standard_deviation(array $a, $sample = false) {
    $n = count($a);
    if ($n === 0) {
      trigger_error("The array has zero elements", E_USER_WARNING);
      return false;
    }
    if ($sample && $n === 1) {
      trigger_error("The array has only 1 element", E_USER_WARNING);
      return false;
    }
    $mean = array_sum($a) / $n;
    $carry = 0.0;
    foreach ($a as $val) {
      $d = ((double) $val) - $mean;
      $carry += $d * $d;
    };
    if ($sample) {
      --$n;
    }
    return sqrt($carry / $n);
  }

}
