<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

namespace Civi\DataProcessor\Source\Member;

use Civi\DataProcessor\DataFlow\CombinedDataFlow\SubqueryDataFlow;
use Civi\DataProcessor\DataFlow\MultipleDataFlows\DataFlowDescription;
use Civi\DataProcessor\DataFlow\MultipleDataFlows\SimpleJoin;
use Civi\DataProcessor\DataFlow\MultipleDataFlows\SimpleNonRequiredJoin;
use Civi\DataProcessor\DataFlow\SqlDataFlow\AndClause;
use Civi\DataProcessor\DataFlow\SqlDataFlow\OrClause;
use Civi\DataProcessor\DataFlow\SqlDataFlow\PureSqlStatementClause;
use Civi\DataProcessor\DataFlow\SqlDataFlow\SimpleWhereClause;
use Civi\DataProcessor\DataFlow\SqlTableDataFlow;
use Civi\DataProcessor\DataSpecification\DataSpecification;
use Civi\DataProcessor\DataSpecification\FieldExistsException;
use Civi\DataProcessor\DataSpecification\FieldSpecification;
use Civi\DataProcessor\Source\AbstractSource;

use CRM_Dataprocessor_ExtensionUtil as E;

class PrimaryMembershipSource extends AbstractSource {

  /**
   * @var \Civi\DataProcessor\DataSpecification\DataSpecification
   */
  protected $availableFields;

  /**
   * @var \Civi\DataProcessor\DataSpecification\DataSpecification
   */
  protected $availableFilterFields;

  /**
   * @var SqlTableDataFlow
   */
  protected $primary_membership_data_flow;

  /**
   * @var SqlTableDataFlow
   */
  protected $membership_data_flow;

  /**
   * Initialize the join
   *
   * @return void
   */
  public function initialize() {
    if (!$this->dataFlow) {
      $this->primary_membership_data_flow = new SqlTableDataFlow('civicrm_membership', $this->getSourceName().'_primary_membership');
      $this->membership_data_flow = new SqlTableDataFlow('civicrm_membership', $this->getSourceName().'_membership');

      $joinClause = new OrClause();
      $joinClause1 = new AndClause();
      $joinClause1->addWhereClause(new SimpleWhereClause($this->membership_data_flow->getTableAlias(), 'owner_membership_id', 'IS NOT NULL', null, 'String', TRUE));
      $joinClause1->addWhereClause(new PureSqlStatementClause("`{$this->primary_membership_data_flow->getTableAlias()}`.`id` = `{$this->membership_data_flow->getTableAlias()}`.`owner_membership_id`"));
      $joinClause->addWhereClause($joinClause1);

      $joinClause2 = new AndClause();
      $joinClause2->addWhereClause(new SimpleWhereClause($this->membership_data_flow->getTableAlias(), 'owner_membership_id', 'IS NULL', null, 'String', TRUE));
      $joinClause2->addWhereClause(new PureSqlStatementClause("`{$this->primary_membership_data_flow->getTableAlias()}`.`id` = `{$this->membership_data_flow->getTableAlias()}`.`id`"));
      $joinClause->addWhereClause($joinClause2);

      $join = new SimpleNonRequiredJoin(null, null, null, null, 'INNER');
      $join->addFilterClause($joinClause);

      $this->dataFlow = new SubqueryDataFlow($this->getSourceName(), 'civicrm_membership', $this->getSourceName());
      $primaryMembershipDataFlowDescription = new DataFlowDescription($this->primary_membership_data_flow);
      $this->dataFlow->addSourceDataFlow($primaryMembershipDataFlowDescription);
      $memberShipDataFlowDescription = new DataFlowDescription($this->membership_data_flow, $join);
      $this->dataFlow->addSourceDataFlow($memberShipDataFlowDescription);

      try {
        $this->primary_membership_data_flow->getDataSpecification()
          ->addFieldSpecification('id', new FieldSpecification('id','Integer', E::ts('Primary Membership ID'), null, 'primary_membership_id'))
          ->addFieldSpecification('contact_id', new FieldSpecification('contact_id','Integer', E::ts('Primary Membership Contact ID'), null, 'primary_membership_contact_id'));

        $this->membership_data_flow->getDataSpecification()
          ->addFieldSpecification('id', new FieldSpecification('id','Integer', E::ts('Membership ID'), null, 'membership_id'))
          ->addFieldSpecification('contact_id', new FieldSpecification('contact_id','Integer', E::ts('Membership Contact ID'), null, 'membership_contact_id'));

        /*$this->dataFlow->getDataSpecification()
          ->addFieldSpecification('primary_membership_id', $this->getAvailableFields()
            ->getFieldSpecificationByName('primary_membership_id'))
          ->addFieldSpecification('primary_membership_contact_id', $this->getAvailableFields()
            ->getFieldSpecificationByName('primary_membership_contact_id'))
          ->addFieldSpecification('membership_id', $this->getAvailableFields()
            ->getFieldSpecificationByName('membership_id'))
          ->addFieldSpecification('membership_contact_id', $this->getAvailableFields()
            ->getFieldSpecificationByName('membership_contact_id'));*/
      } catch (\Exception $e) {
        // Do nothing
      }

    }
  }

  /**
   * @return \Civi\DataProcessor\DataSpecification\DataSpecification
   * @throws \Exception
   */
  public function getAvailableFields() {
    if (!$this->availableFields) {
      $this->availableFields = new DataSpecification();
      $this->loadFields($this->availableFields);
    }
    return $this->availableFields;
  }

  /**
   * @return \Civi\DataProcessor\DataSpecification\DataSpecification
   * @throws \Exception
   */
  public function getAvailableFilterFields() {
    if (!$this->availableFilterFields) {
      $this->availableFilterFields = new DataSpecification();
      $this->loadFields($this->availableFilterFields);
    }
    return $this->availableFilterFields;
  }

  /**
   * Add custom fields to the available fields section
   *
   * @param DataSpecification $dataSpecification
   * @throws \Civi\DataProcessor\DataSpecification\FieldExistsException
   * @throws \Exception
   */
  protected function loadFields(DataSpecification $dataSpecification) {
    $dataSpecification->addFieldSpecification('primary_membership_id', new FieldSpecification('primary_membership_id','Integer', E::ts('Primary Membership ID'), null, $this->getSourceName().'_primary_membership_id'));
    $dataSpecification->addFieldSpecification('membership_id', new FieldSpecification('membership_id','Integer', E::ts('Membership ID'), null, $this->getSourceName().'_membership_id'));
    $dataSpecification->addFieldSpecification('primary_membership_contact_id', new FieldSpecification('primary_membership_contact_id','Integer', E::ts('Primary Membership Contact ID'), null, $this->getSourceName().'_primary_membership_contact_id'));
    $dataSpecification->addFieldSpecification('membership_contact_id', new FieldSpecification('membership_contact_id','Integer', E::ts('Membership Contact ID'), null, $this->getSourceName().'_membership_contact_id'));
  }

  /**
   * Ensures a field is in the data source
   *
   * @param \Civi\DataProcessor\DataSpecification\FieldSpecification $fieldSpecification
   * @return \Civi\DataProcessor\Source\SourceInterface
   * @throws \Exception
   */
  public function ensureFieldInSource(FieldSpecification $fieldSpecification) {
    try {
      $this->dataFlow->getDataSpecification()->addFieldSpecification($fieldSpecification->alias, $fieldSpecification);
    } catch (FieldExistsException $e) {
      // Do nothing.
    }
    return $this;
  }

  /**
   * Ensure that filter field is accesible in the query
   *
   * @param FieldSpecification $field
   * @return \Civi\DataProcessor\DataFlow\AbstractDataFlow|null
   * @throws \Exception
   */
  public function ensureField(FieldSpecification $field) {
    return $this->dataFlow;
  }

}
