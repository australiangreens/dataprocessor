{crmScope extensionKey='dataprocessor'}
<h3>{ts}Fields{/ts}</h3>
<div class="crm-block crm-form-block crm-data-processor_source-block">
    <table>
        <tr>
            <th>{ts}Title{/ts}</th>
            <th></th>
            <th></th>
        </tr>
        {foreach from=$fields item=field}
            <tr>
                <td>{$field.title} <br /><span class="description">{$field.name}</span></td>
                <td style="width: 60px">{if ($field.weight && !is_numeric($field.weight))}{$field.weight}{/if}</td>
                <td class="right nowrap" style="width: 100px;">
                    <div class="crm-configure-actions">
                    <span class="btn-slide crm-hover-button">{ts}Configure{/ts}
                    <ul class="panel">
                        <li><a class="action-item crm-hover-button" href="{crmURL p="civicrm/dataprocessor/form/field" q="reset=1&action=update&data_processor_id=`$field.data_processor_id`&id=`$field.id`"}">{ts}Edit{/ts}</a></li>
                        <li><a class="action-item crm-hover-button" href="{crmURL p="civicrm/dataprocessor/form/field" q="reset=1&action=delete&data_processor_id=`$field.data_processor_id`&id=`$field.id`"}">{ts}Remove{/ts}</a></li>
                    </ul>
                    </span>
                    </div>
                </td>
            </tr>
        {/foreach}
    </table>

    <div class="crm-submit-buttons">
        <a class="add button" title="{ts}Add Field{/ts}" href="{$addFieldUrl}">
            <i class='crm-i fa-plus-circle'></i> {ts}Add Field{/ts}</a>
    </div>
</div>
{/crmScope}
