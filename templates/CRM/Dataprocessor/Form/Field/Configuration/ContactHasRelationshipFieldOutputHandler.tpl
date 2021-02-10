{crmScope extensionKey='dataprocessor'}
  <p class="help">{ts}With this output you can display a text when a contact has one or more active relationships of a given type.{/ts}</p>
  <div class="crm-section">
    <div class="label">{$form.field.label}</div>
    <div class="content">{$form.field.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.relationship_types.label}</div>
    <div class="content">{$form.relationship_types.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.active_text.label}</div>
    <div class="content">{$form.active_text.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.inactive_text.label}</div>
    <div class="content">{$form.inactive_text.html}</div>
    <div class="clear"></div>
  </div>
{/crmScope}
