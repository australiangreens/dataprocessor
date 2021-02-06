{crmScope extensionKey='dataprocessor'}
    <div class="crm-section">
        <div class="label">{$form.link_field_1.label}</div>
        <div class="content">{$form.link_field_1.html}</div>
        <div class="label">{$form.link_field_2.label}</div>
        <div class="content">{$form.link_field_2.html}</div>
        <div class="clear"></div>
    </div>
    <p class="help">{ts}Use %1 and %2 as placeholders in template and/or link text, they will be replaced by the value of the corresponding link field{/ts}</p>
    <div class="crm-section">
        <div class="label">{$form.link_template.label}</div>
        <div class="content">{$form.link_template.html}</div>
        <div class="clear"></div>
    </div>
    <div class="crm-section">
        <div class="label">{$form.link_text.label}</div>
        <div class="content">{$form.link_text.html}</div>
        <div class="clear"></div>
    </div>
{/crmScope}
