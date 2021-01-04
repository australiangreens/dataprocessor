{crmScope extensionKey='dataprocessor'}
  {include file="CRM/Dataprocessor/Form/Field/Configuration/SimpleFieldOutputHandler.tpl"}
  <div class="crm-section">
    <div class="label">{$form.financial_type.label}</div>
    <div class="content">{$form.financial_type.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.status.label}</div>
    <div class="content">{$form.status.html}</div>
    <div class="clear"></div>
  </div>
{/crmScope}
