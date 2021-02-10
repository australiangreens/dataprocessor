{crmScope extensionKey='dataprocessor'}
  <div class="crm-section">
      <div class="label">{$form.contact_id_field.label}</div>
      <div class="content">{$form.contact_id_field.html}</div>
      <div class="clear"></div>
  </div>
  <div class="crm-section">
      <div class="label">{$form.case_id_field.label}</div>
      <div class="content">{$form.case_id_field.html}</div>
      <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.link_title.label}</div>
    <div class="content">
      {$form.link_title.html}
      <p class="description">{ts}Leave empty to use default.{/ts}</p>
    </div>
    <div class="clear"></div>
  </div>
{/crmScope}
