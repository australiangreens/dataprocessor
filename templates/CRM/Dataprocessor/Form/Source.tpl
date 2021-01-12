{crmScope extensionKey='dataprocessor'}

{if $action eq 8}
    {* Are you sure to delete form *}
    <h3>{ts}Delete Data Source{/ts}</h3>
    <div class="crm-block crm-form-block crm-data-processor_label-block">
        <div class="crm-section">{ts 1=$source.title}Are you sure to delete data processor source '%1'?{/ts}</div>
    </div>

    <div class="crm-submit-buttons">
        {include file="CRM/common/formButtons.tpl" location="bottom"}
    </div>
{elseif (!$snippet)}

    {* block for rule data *}
    <h3>{ts}Data Processor Source{/ts}</h3>
    <div class="crm-block crm-form-block crm-data-processor_source-block">
        <div class="crm-section">
            <div class="label">{$form.type.label}</div>
            <div class="content">{$form.type.html}</div>
            <div class="clear"></div>
        </div>
        <div class="crm-section">
            <div class="label">{$form.title.label}</div>
            <div class="content">
                {$form.title.html}
                <span class="">
                {ts}System name:{/ts}&nbsp;
                <span id="systemName" style="font-style: italic;">{if ($source)}{$source.name}{/if}</span>
                <a href="javascript:void(0);" onclick="jQuery('#nameSection').removeClass('hiddenElement'); jQuery(this).parent().addClass('hiddenElement'); return false;">
                  {ts}Change{/ts}
                </a>
                </span>
            </div>
            <div class="clear"></div>
        </div>
        <div id="nameSection" class="crm-section hiddenElement">
            <div class="label">{$form.name.label}</div>
            <div class="content">{$form.name.html}</div>
            <div class="clear"></div>
        </div>

        <div id="type_configuration">
            {if ($configuration_template)}
                {include file=$configuration_template}
            {/if}

            {include file="CRM/Dataprocessor/Form/Source/Join.tpl"}
        </div>
    </div>

    <div class="crm-submit-buttons">
        {include file="CRM/common/formButtons.tpl" location="bottom"}
    </div>

    <script type="text/javascript">
        {literal}

        CRM.$(function($) {
          $('#type').on('change', function() {
            {/literal}{if $action eq 1}{literal}
              var titlepreset = $.trim($('#type option:selected').text().split("::").pop());
              titlepreset = $.trim(titlepreset.split("(").shift());
              $('#title').val(titlepreset).trigger('blur');
            {/literal}{/if}{literal};
            var type = $('#type').val();
            var join_type = $('#join_type').val();
            var id = {/literal}{if ($source)}{$source.id}{else}false{/if}{literal};
            var data_processor_id = {/literal}{$data_processor_id}{literal};
            if (type) {
              var params = {type: type, 'data_processor_id': data_processor_id, 'id': id, 'join_type': join_type};
              var table_name = $('#table_name').val();
              if (table_name) {
                params.table_name = table_name;
              }
              var dataUrl = CRM.url('civicrm/dataprocessor/form/source', params);
              CRM.loadPage(dataUrl, {'target': '#type_configuration'});
            }
          });

          $('#title').on('blur', function() {
            var title = $('#title').val();
            var id = {/literal}{if ($source)}{$source.id}{else}false{/if}{literal};
            var data_processor_id = {/literal}{$data_processor_id}{literal};
            if ($('#nameSection').hasClass('hiddenElement') && !id) {
              CRM.api3('DataProcessorSource', 'check_name', {
                'title': title,
                'data_processor_id': data_processor_id
              }).done(function (result) {
                $('#systemName').html(result.name);
                $('#name').val(result.name);
              });
            }
          });

          $('#type').change();
        });
        {/literal}
    </script>

{elseif ($block == 'joinOnly')}
    {include file="CRM/Dataprocessor/Form/Source/Join.tpl"}
{elseif ($block == 'configuration')}
    <div id="type_configuration">
        {if ($configuration_template)}
            {include file=$configuration_template}
        {/if}

        {include file="CRM/Dataprocessor/Form/Source/Join.tpl"}
    </div>
{/if}

{/crmScope}
