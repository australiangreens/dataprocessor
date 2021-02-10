{crmScope extensionKey='dataprocessor'}
    <h3>{ts}Filters{/ts}</h3>
    <div class="crm-block crm-form-block crm-data-processor_source-block">
        <table>
            <tr>
                <th>{ts}Title{/ts}</th>
                <th>{ts}Exposed{/ts}</th>
                <th></th>
                <th></th>
            </tr>
            {foreach from=$filters item=filter}
                <tr>
                    <td>
                        {$filter.title}
                        {if ($filter.is_required)}
                            <span class="crm-marker">*</span>
                        {/if} <br />
                        <span class="description">{$filter.name}</span>
                    </td>
                    <td>
                        {if ($filter.is_exposed)}{ts}Yes{/ts}{else}{ts}No{/ts}{/if}
                    </td>
                    <td style="width: 60px">{if ($filter.weight && !is_numeric($filter.weight))}{$filter.weight}{/if}</td>
                    <td class="right nowrap" style="width: 100px;">
                        <div class="crm-configure-actions">
                        <span class="btn-slide crm-hover-button">{ts}Configure{/ts}
                        <ul class="panel">
                            <li><a class="action-item crm-hover-button" href="{crmURL p="civicrm/dataprocessor/form/filter" q="reset=1&action=update&data_processor_id=`$filter.data_processor_id`&id=`$filter.id`"}">{ts}Type &amp; configuration{/ts}</a></li>
                            <li><a class="action-item crm-hover-button" href="{crmURL p="civicrm/dataprocessor/form/filter_value" q="reset&action=update&data_processor_id=`$filter.data_processor_id`&id=`$filter.id`"}">{ts}Filter setting{/ts}</a></li>
                            <li><a class="action-item crm-hover-button" href="{crmURL p="civicrm/dataprocessor/form/filter" q="reset=1&action=delete&data_processor_id=`$filter.data_processor_id`&id=`$filter.id`"}">{ts}Remove{/ts}</a></li>
                        </ul>
                        </span>
                        </div>
                    </td>
                </tr>
            {/foreach}
        </table>

        <div class="crm-submit-buttons">
            <a class="add button" title="{ts}Add Filter{/ts}" href="{$addFilterUrl}">
                <i class='crm-i fa-plus-circle'></i> {ts}Add Filter{/ts}</a>
        </div>
    </div>
{/crmScope}
