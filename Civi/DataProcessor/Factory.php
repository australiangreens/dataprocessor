<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

namespace Civi\DataProcessor;

use Civi\DataProcessor\DataFlow\Sort\SortCompareFactory;
use Civi\DataProcessor\Event\FilterHandlerEvent;
use Civi\DataProcessor\Event\OutputHandlerEvent;
use Civi\DataProcessor\Event\SourceOutputHandlerEvent;
use Civi\DataProcessor\ProcessorType\AbstractProcessorType;
use Civi\DataProcessor\Factory\Definition;

use CRM_Dataprocessor_ExtensionUtil as E;


class Factory {

  /**
   * @var array<String>
   */
  protected $types = array();


  /**
   * @var array<Definition>
   */
  protected $typesClasses = array();

  /**
   * @var array<String>
   */
  protected $sources = array();


  /**
   * @var array<Definition>
   */
  protected $sourceClasses = array();

  /**
   * @var array<String>
   */
  protected $outputs = array();


  /**
   * @var array<Definition>
   */
  protected $outputClasses = array();

  /**
   * @var array<String>
   */
  protected $filters = array();


  /**
   * @var array<Definition>
   */
  protected $filterClasses = array();

  /**
   * @var String[]
   */
  protected $outputHandlers = array();

  /**
   * @var array<Definition>
   */
  protected $outputHandlerClasses = array();

  /**
   * @var array<String>
   */
  protected $joins = array();


  /**
   * @var array<Definition>
   */
  protected $joinClasses = array();

  protected $fieldOutputHandlers = array();

  /**
   * @var SortCompareFactory
   */
  protected $sortCompareFactory;


  public function __construct() {
    $this->addDataProcessorType('default', new Definition('Civi\DataProcessor\ProcessorType\DefaultProcessorType'), E::ts('Default'));
    $this->addDataSource('activity', new Definition('Civi\DataProcessor\Source\Activity\ActivitySource'), E::ts('Activity'));
    $this->addDataSource('contact', new Definition('Civi\DataProcessor\Source\Contact\ContactSource'), E::ts('Contact'));
    $this->addDataSource('contact_acl', new Definition('Civi\DataProcessor\Source\Contact\ACLContactSource'), E::ts('Permissioned Contact'));
    $this->addDataSource('individual', new Definition('Civi\DataProcessor\Source\Contact\IndividualSource'), E::ts('Individual'));
    $this->addDataSource('household', new Definition('Civi\DataProcessor\Source\Contact\HouseholdSource'), E::ts('Household'));
    $this->addDataSource('organization', new Definition('Civi\DataProcessor\Source\Contact\OrganizationSource'), E::ts('Organization'));
    $this->addDataSource('group', new Definition('Civi\DataProcessor\Source\Group\GroupSource'), E::ts('Group'));
    $this->addDataSource('group_contact', new Definition('Civi\DataProcessor\Source\Group\GroupContactSource'), E::ts('Contacts in a group'));
    $this->addDataSource('group_contact_cache', new Definition('Civi\DataProcessor\Source\Group\SmartGroupContactSource'), E::ts('Contacts in a smart group'));
    $this->addDataSource('email', new Definition('Civi\DataProcessor\Source\Contact\EmailSource'), E::ts('E-mail'));
    $this->addDataSource('address', new Definition('Civi\DataProcessor\Source\Contact\AddressSource'), E::ts('Address'));
    $this->addDataSource('phone', new Definition('Civi\DataProcessor\Source\Contact\PhoneSource'), E::ts('Phone'));
    $this->addDataSource('website', new Definition('Civi\DataProcessor\Source\Contact\WebsiteSource'), E::ts('Website'));
    $this->addDataSource('campaign', new Definition('Civi\DataProcessor\Source\Campaign\CampaignSource'), E::ts('Campaign'));
    $this->addDataSource('contribution', new Definition('Civi\DataProcessor\Source\Contribution\ContributionSource'), E::ts('Contribution'));
    $this->addDataSource('contribution_recur', new Definition('Civi\DataProcessor\Source\Contribution\ContributionRecurSource'), E::ts('Recurring Contribution'));
    $this->addDataSource('case', new Definition('Civi\DataProcessor\Source\Cases\CaseSource'), E::ts('Case'));
    $this->addDataSource('relationship', new Definition('Civi\DataProcessor\Source\Contact\RelationshipSource'), E::ts('Relationship'));
    $this->addDataSource('relationship_type', new Definition('Civi\DataProcessor\Source\Contact\RelationshipTypeSource'), E::ts('Relationship Type'));
    $this->addDataSource('event', new Definition('Civi\DataProcessor\Source\Event\EventSource'), E::ts('Event'));
    $this->addDataSource('participant', new Definition('Civi\DataProcessor\Source\Event\ParticipantSource'), E::ts('Participant'));
    $this->addDataSource('line_item', new Definition('Civi\DataProcessor\Source\Price\LineItemSource'), E::ts('Line Item'));
    $this->addDataSource('mailing', new Definition('Civi\DataProcessor\Source\Mailing\MailingSource'), E::ts('Mailing'));
    $this->addDataSource('mailing_job', new Definition('Civi\DataProcessor\Source\Mailing\MailingJobSource'), E::ts('Mailing Job'));
    $this->addDataSource('mailing_group', new Definition('Civi\DataProcessor\Source\Mailing\MailingGroupSource'), E::ts('Mailing Group'));
    $this->addDataSource('membership', new Definition('Civi\DataProcessor\Source\Member\MembershipSource'), E::ts('Membership'));
    $this->addDataSource('primary_membership', new Definition('Civi\DataProcessor\Source\Member\PrimaryMembershipSource'), E::ts('Primary Membership (retrieve the owner membership id'));
    $this->addDataSource('membership_type', new Definition('Civi\DataProcessor\Source\Member\MembershipTypeSource'), E::ts('Membership Type'));
    $this->addDataSource('membership_status', new Definition('Civi\DataProcessor\Source\Member\MembershipStatusSource'), E::ts('Membership Status'));
    $this->addDataSource('membership_payment', new Definition('Civi\DataProcessor\Source\Member\MembershipPaymentSource'), E::ts('Membership Payment'));
    $this->addDataSource('note', new Definition('Civi\DataProcessor\Source\Note\NoteSource'), E::ts('Note'));
    $this->addDataSource('csv', new Definition('Civi\DataProcessor\Source\CSV'), E::ts('CSV File'));
    $this->addDataSource('sqltable', new Definition('Civi\DataProcessor\Source\SQLTable'), E::ts('SQL Table'));
    $this->addOutput('api', new Definition('Civi\DataProcessor\Output\Api'), E::ts('API'));
    $this->addOutput('contact_summary_tab', new Definition('CRM_Contact_DataProcessorContactSummaryTab'), E::ts('Tab on contact summary'));
    $this->addOutput('dashlet', new Definition('CRM_DataprocessorDashlet_Dashlet'), E::ts('Dashlet'));
    $this->addOutput('search', new Definition('CRM_DataprocessorSearch_Search'), E::ts('Search / Report'));
    $this->addOutput('contact_search', new Definition('CRM_Contact_DataProcessorContactSearch'), E::ts('Contact Search'));
    $this->addOutput('activity_search', new Definition('CRM_DataprocessorSearch_ActivitySearch'), E::ts('Activity Search'));
    $this->addOutput('case_search', new Definition('CRM_DataprocessorSearch_CaseSearch'), E::ts('Case Search'));
    $this->addOutput('contribution_search', new Definition('CRM_DataprocessorSearch_ContributionSearch'), E::ts('Contribution Search'));
    $this->addOutput('membership_search', new Definition('CRM_DataprocessorSearch_MembershipSearch'), E::ts('Membership Search'));
    $this->addOutput('participant_search', new Definition('CRM_DataprocessorSearch_ParticipantSearch'), E::ts('Participant Search'));
    $this->addOutput('export_csv', new Definition('CRM_DataprocessorOutputExport_CSV'), E::ts('CSV Export'));
    $this->addOutput('export_pdf', new Definition('CRM_DataprocessorOutputExport_PDF'), E::ts('PDF Export'));
    $this->addFilter('simple_sql_filter', new Definition('Civi\DataProcessor\FilterHandler\SimpleSqlFilter'), E::ts('Field filter'));
    $this->addFilter('date_filter', new Definition('Civi\DataProcessor\FilterHandler\DateFilter'), E::ts('Date filter'));
    $this->addFilter('multiple_field_filter', new Definition('Civi\DataProcessor\FilterHandler\MultipleFieldFilter'), E::ts('Text in multiple fields Filter'));
    $this->addFilter('activity_filter', new Definition('Civi\DataProcessor\FilterHandler\ActivityFilter'), E::ts('Activity filter'));
    $this->addFilter('event_filter', new Definition('Civi\DataProcessor\FilterHandler\EventFilter'), E::ts('Event filter'));
    $this->addFilter('contact_filter', new Definition('Civi\DataProcessor\FilterHandler\ContactFilter'), E::ts('Contact filter'));
    $this->addFilter('contact_in_group_filter', new Definition('Civi\DataProcessor\FilterHandler\ContactInGroupFilter'), E::ts('Contact in Group filter'));
    $this->addFilter('contact_with_tag_filter', new Definition('Civi\DataProcessor\FilterHandler\ContactWithTagFilter'), E::ts('Contact has Tag filter'));
    $this->addFilter('contact_has_membership', new Definition('Civi\DataProcessor\FilterHandler\ContactHasMembershipFilter'), E::ts('Contact has Membership filter'));
    $this->addFilter('contact_type_filter', new Definition('Civi\DataProcessor\FilterHandler\ContactTypeFilter'), E::ts('Contact Type filter'));
    $this->addFilter('permission_to_view_contact', new Definition('Civi\DataProcessor\FilterHandler\PermissionToViewContactFilter'), E::ts('Permission to view contact'));
    $this->addFilter('case_role_filter', new Definition('Civi\DataProcessor\FilterHandler\CaseRoleFilter'), E::ts('Case Role filter'));
    $this->addFilter('in_api_filter', new Definition('Civi\DataProcessor\FilterHandler\InApiFilter'), E::ts('API filter'));
    $this->addjoinType('simple_join', new Definition('Civi\DataProcessor\DataFlow\MultipleDataFlows\SimpleJoin'), E::ts('Select fields to join on'));
    $this->addjoinType('simple_non_required_join', new Definition('Civi\DataProcessor\DataFlow\MultipleDataFlows\SimpleNonRequiredJoin'), E::ts('Select fields to join on (not required)'));
    $this->addOutputHandler('raw', new Definition('Civi\DataProcessor\FieldOutputHandler\RawFieldOutputHandler'), E::ts('Raw field value'));
    $this->addOutputHandler('markup', new Definition('Civi\DataProcessor\FieldOutputHandler\MarkupFieldOutputHandler'), E::ts('Markup/Html field value'));
    $this->addOutputHandler('formatted_address', new Definition('Civi\DataProcessor\FieldOutputHandler\FormattedAddressFieldOutputHandler'), E::ts('Formatted Address'));
    $this->addOutputHandler('number', new Definition('Civi\DataProcessor\FieldOutputHandler\NumberFieldOutputHandler'), E::ts('Formatted Number field value'));
    $this->addOutputHandler('date', new Definition('Civi\DataProcessor\FieldOutputHandler\DateFieldOutputHandler'), E::ts('Date field value'));
    $this->addOutputHandler('age', new Definition('Civi\DataProcessor\FieldOutputHandler\AgeFieldOutputHandler'), E::ts('Age field value'));
    $this->addOutputHandler('contact_link', new Definition('Civi\DataProcessor\FieldOutputHandler\ContactLinkFieldOutputHandler'), E::ts('Link to view contact'));
    $this->addOutputHandler('is_active', new Definition('Civi\DataProcessor\FieldOutputHandler\IsActiveFieldOutputHandler'), E::ts('Is Active (based on dates)'));
    $this->addOutputHandler('file_field', new Definition('Civi\DataProcessor\FieldOutputHandler\FileFieldOutputHandler'), E::ts('File download link'));
    $this->addOutputHandler('option_label', new Definition('Civi\DataProcessor\FieldOutputHandler\OptionFieldOutputHandler'), E::ts('Option label'));
    $this->addOutputHandler('relationships', new Definition('Civi\DataProcessor\FieldOutputHandler\RelationshipsFieldOutputHandler'), E::ts('Relationships'));
    $this->addOutputHandler('case_roles', new Definition('Civi\DataProcessor\FieldOutputHandler\CaseRolesFieldOutputHandler'), E::ts('Case Roles'));
    $this->addOutputHandler('manage_case_link', new Definition('Civi\DataProcessor\FieldOutputHandler\ManageCaseLinkFieldOutputHandler'), E::ts('Link to manage case'));
    $this->addOutputHandler('groups_of_contact', new Definition('Civi\DataProcessor\FieldOutputHandler\GroupsOfContactFieldOutputHandler'), E::ts('Display the groups of a contact'));
    $this->addOutputHandler('event_repeating_info', new Definition('Civi\DataProcessor\FieldOutputHandler\EventRepeatingInfoFieldOutputHandler'), E::ts('Display info about repeating event'));
    $this->addOutputHandler('event_participants', new Definition('Civi\DataProcessor\FieldOutputHandler\EventParticipantsFieldOutputHandler'), E::ts('List participants'));
    $this->addOutputHandler('calculations_substract', new Definition('Civi\DataProcessor\FieldOutputHandler\Calculations\SubtractFieldOutputHandler'), E::ts('Calculations (on multiple fields): Subtract'));
    $this->addOutputHandler('calculations_total', new Definition('Civi\DataProcessor\FieldOutputHandler\Calculations\TotalFieldOutputHandler'), E::ts('Calculations (on multiple fields): Adding up'));
    $this->addOutputHandler('aggregation_function', new Definition('Civi\DataProcessor\FieldOutputHandler\AggregateFunctionFieldOutputHandler'), E::ts('Aggregation function'));
  }

  /**
   * @return array<String>
   */
  public function getDataProcessorTypes() {
    return $this->types;
  }

  /**
   * @param $name
   *
   * @return AbstractProcessorType
   */
  public function getDataProcessorTypeByName($name) {
    return $this->typesClasses[$name]->get();
  }

  /**
   * @return array<String>
   */
  public function getDataSources() {
    return $this->sources;
  }

  /**
   * @param $name
   *
   * @return \Civi\DataProcessor\Source\SourceInterface
   */
  public function getDataSourceByName($name) {
    return $this->sourceClasses[$name]->get();
  }

  /**
   * @return array<String>
   */
  public function getOutputs() {
    return $this->outputs;
  }

  /**
   * @param $name
   *
   * @return \Civi\DataProcessor\Output\OutputInterface
   */
  public function getOutputByName($name) {
    return $this->outputClasses[$name]->get();
  }

  /**
   * @return array<String>
   */
  public function getFilters() {
    return $this->filters;
  }

  /**
   * @param $name
   *
   * @param string $name
   * @return \Civi\DataProcessor\FilterHandler\AbstractFilterHandler
   */
  public function getFilterByName($name) {
    if (!isset($this->filterClasses[$name])) {
      return null;
    }
    return $this->filterClasses[$name]->get();
  }

  /**
   * @return array<String>
   */
  public function getJoins() {
    return $this->joins;
  }

  /**
   * @param $name
   *
   * @return \Civi\DataProcessor\DataFlow\MultipleDataFlows\JoinInterface
   */
  public function getJoinByName($name) {
    return $this->joinClasses[$name]->get();
  }

  /**
   * @param $name
   * @param $class
   * @param $label
   * @return Factory
   */
  public function addjoinType($name, $class, $label) {
    if (is_string($class)) {
      $class = new Definition($class);
    }
    $this->joinClasses[$name] = $class;
    $this->joins[$name] = $label;
    return $this;
  }

  /**
   * @param $name
   * @param $class
   * @param $label
   * @return Factory
   */
  public function addDataProcessorType($name, $class, $label) {
    if (is_string($class)) {
      $class = new Definition($class);
    }
    $this->typesClasses[$name] = $class;
    $this->types[$name] = $label;
    return $this;
  }

  /**
   * @param $name
   * @param $class
   * @param $label
   * @return Factory
   */
  public function addDataSource($name, $class, $label) {
    if (is_string($class)) {
      $class = new Definition($class);
    }
    $this->sourceClasses[$name] = $class;
    $this->sources[$name] = $label;
    return $this;
  }

  /**
   * @param $name
   * @param $class
   * @param $label
   * @return Factory
   */
  public function addFilter($name, $class, $label) {
    if (is_string($class)) {
      $class = new Definition($class);
    }
    $this->filterClasses[$name] = $class;
    $this->filters[$name] = $label;
    return $this;
  }

  /**
   * @param $name
   * @param $class
   * @param $label
   */
  public function addOutputHandler($name, $class, $label) {
    if (is_string($class)) {
      $class = new Definition($class);
    }
    $this->outputHandlerClasses[$name] = $class;
    $this->outputHandlers[$name] = $label;
  }

  /**
   * @return String[]
   */
  public function getOutputHandlers() {
    return $this->outputHandlers;
  }

  /**
   * @param $name
   *
   * @return \Civi\DataProcessor\FieldOutputHandler\AbstractFieldOutputHandler
   */
  public function getOutputHandlerByName($name) {
    if ($name && isset($this->outputHandlerClasses[$name])) {
      $class = $this->outputHandlerClasses[$name];
      return $class->get();
    } else {
      return null;
    }
  }

  /**
   * @param $name
   * @param $class
   * @param $label
   * @return Factory
   */
  public function addOutput($name, $class, $label) {
    if (is_string($class)) {
      $class = new Definition($class);
    }
    $this->outputClasses[$name] = $class;
    $this->outputs[$name] = $label;
    return $this;
  }

  /**
   * @return \Civi\DataProcessor\DataFlow\Sort\SortCompareFactory
   */
  public function getSortCompareFactory() {
    if (!$this->sortCompareFactory) {
      $this->sortCompareFactory = new SortCompareFactory();
    }
    return $this->sortCompareFactory;
  }

}
