{crmScope extensionKey='dataprocessor'}

{include file="CRM/Dataprocessor/Form/Source/Configuration.tpl"}

<div class="crm-accordion-wrapper collapsed">
  <div class="crm-accordion-header">{ts}Aggregation{/ts}</div>
  <div class="crm-accordion-body">
    <table class="report-layout">
        <tr class="report-contents crm-report crm-report-criteria-filter">
          <td class="report-contents">{$form.aggregate_function.label}</td>
          <td>{$form.aggregate_function.html}</td>
        </tr>
      <tr class="report-contents crm-report crm-report-criteria-filter">
        <td class="report-contents">{$form.aggregate_by.label}</td>
        <td>{$form.aggregate_by.html}</td>
      </tr>
    </table>

  </div>
</div>
{/crmScope}
