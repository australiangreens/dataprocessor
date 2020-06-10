{crmScope extensionKey='dataprocessor'}
  <div class="crm-section">
    <div class="label">{$form.pdf_format.label}</div>
    <div class="content">{$form.pdf_format.html}
      <p class="description">
        {ts 1=$ManagePdfFormatUrl}You can manage PDF Formats at <a href="%1">Administer --> Communications --> PDF Formats</a>{/ts}
      </p>
    </div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.border.label}</div>
    <div class="content">{$form.border.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.template.label}</div>
    <div class="content">{$form.template.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.header.label}</div>
    <div class="content">{$form.header.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.header_fields.label}</div>
    <div class="content">{$form.header_fields.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.header_after_section.label}</div>
    <div class="content">{$form.header_after_section.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.hidden_fields.label}</div>
    <div class="content">{$form.hidden_fields.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.section_titles.label}</div>
    <div class="content">{$form.section_titles.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.additional_column.label}</div>
    <div class="content">{$form.additional_column.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section additional_column">
    <div class="label">{$form.additional_column_title.label}</div>
    <div class="content">{$form.additional_column_title.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section additional_column">
    <div class="label">{$form.additional_column_width.label}</div>
    <div class="content">{$form.additional_column_width.html}
      <p class="description">{ts}E.g. 2 cm{/ts}</p>
    </div>
    <div class="clear"></div>
  </div>
  <div class="crm-section additional_column">
    <div class="label">{$form.additional_column_height.label}</div>
    <div class="content">{$form.additional_column_height.html}
      <p class="description">{ts}E.g. 2 cm{/ts}</p>
    </div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.anonymous.label}</div>
    <div class="content">{$form.anonymous.html}
      <p class="description">
        {ts}Tick this box when you want to make the PDF available for non-logged in users. <br>
        <strong>Caution:</strong> when you check this box the data becomes available without logging so this might lead to a data breach.{/ts}</p>
    </div>
    <div class="clear"></div>
  </div>

  <script type="text/javascript">
    {literal}
    CRM.$(function($) {
      function toggleAdditionalColumn() {
        if ($('#additional_column').prop('checked')) {
          $('.crm-section.additional_column').show();
        } else {
          $('.crm-section.additional_column').hide();
        }
      }

      $('#additional_column').on('click', toggleAdditionalColumn);
      $('#additional_column').on('keypress', toggleAdditionalColumn);

      //$('#additional_column').trigger('change');
      toggleAdditionalColumn();
    });
    {/literal}
  </script>
{/crmScope}
