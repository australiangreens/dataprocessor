{crmScope extensionKey='dataprocessor'}
    <h3>{ts}Data Sources{/ts}</h3>
<div class="crm-block crm-form-block crm-data-processor_source-block">
    <table>
        <tr>
            <th>{ts}Title{/ts}</th>
            <th></th>
            <th></th>
        </tr>
        {foreach from=$sources item=source}
            <tr>
                <td>{$source.title} <br />
                    <span class="description">{$source.type_name}</span>
                </td>
                <td style="width: 60px;">{if ($source.weight && !is_numeric($source.weight))}{$source.weight}{/if}</td>
                <td class="right nowrap" style="width: 100px;">
                  <div class="crm-configure-actions">
                        <span class="btn-slide crm-hover-button">{ts}Configure{/ts}
                        <ul class="panel">
                            <li><a class="action-item crm-hover-button" href="{crmURL p="civicrm/dataprocessor/form/source" q="reset=1&action=update&data_processor_id=`$source.data_processor_id`&id=`$source.id`"}">{ts}Edit{/ts}</a></li>
                            <li><a class="action-item crm-hover-button" href="{crmURL p="civicrm/dataprocessor/form/source" q="reset=1&action=delete&data_processor_id=`$source.data_processor_id`&id=`$source.id`"}">{ts}Remove{/ts}</a></li>
                        </ul>
                        </span>
                  </div>
                </td>
            </tr>
        {/foreach}
    </table>

    <div class="crm-submit-buttons">
        <a class="add button" title="{ts}Add Data Source{/ts}" href="{$addDataSourceUrl}">
            <i class='crm-i fa-plus-circle'></i> {ts}Add Data Source{/ts}</a>
    </div>
</div>
{/crmScope}
