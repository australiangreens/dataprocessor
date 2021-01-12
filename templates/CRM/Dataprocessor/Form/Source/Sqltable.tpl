{crmScope extensionKey='dataprocessor'}
<div class="crm-accordion-wrapper">
    <div class="crm-accordion-header">{ts}Configuration{/ts}</div>
    <div class="crm-accordion-body">

        <div class="help-block" id="help">
            {ts}<p>On this form you can configure the SQL Table data source.</p>
            {/ts}
        </div>

        <div class="crm-section">
            <div class="label">{$form.table_name.label}</div>
            <div class="content">{$form.table_name.html}</div>
            <div class="clear"></div>
        </div>
    </div>
</div>
<script type="text/javascript">
    {literal}
    CRM.$(function($) {
        $('#table_name').on('change', function() {
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
{/crmScope}
