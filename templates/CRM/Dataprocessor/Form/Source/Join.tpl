{crmScope extensionKey='dataprocessor'}
<div id="joinBlock">
{if !$is_first_data_source}
    <div class="crm-accordion-wrapper">
        <div class="crm-accordion-header">{ts}Join with other sources{/ts}</div>
        <div class="crm-accordion-body">
            <div class="crm-section">
                <div class="label">{$form.join_type.label}</div>
                <div class="content">{$form.join_type.html}</div>
                <div class="clear"></div>
            </div>
            {if ($join_configuration_template)}
                {include file=$join_configuration_template}
            {/if}
        </div>
    </div>

    <script type="text/javascript">
        {literal}
        CRM.$(function($) {
          $('#join_type').on('change', function() {
            var type = $('#type').val();
            var join_type = $('#join_type').val();
            var id = {/literal}{if ($source)}{$source.id}{else}false{/if}{literal};
            var data_processor_id = {/literal}{$data_processor_id}{literal};
            if (type) {
              var params = {type: type, 'data_processor_id': data_processor_id, 'id': id, 'join_type': join_type, 'block': 'joinOnly'};
              var table_name = $('#table_name').val();
              if (table_name) {
                params.table_name = table_name;
              }
              var dataUrl = CRM.url('civicrm/dataprocessor/form/source', params);
              CRM.loadPage(dataUrl, {'target': '#joinBlock'});
            }
          });
        });
        {/literal}
    </script>
{/if}
</div>
{/crmScope}