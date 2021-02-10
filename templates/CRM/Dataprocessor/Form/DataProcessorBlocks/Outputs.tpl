{crmScope extensionKey='dataprocessor'}
    <h3>{ts}Output{/ts}</h3>
    <div class="crm-block crm-form-block crm-data-processor_outputs-block">
    <table>
        <tr>
            <th>{ts}Output{/ts}</th>
            <th></th>
            <th></th>
        </tr>
        {foreach from=$outputs item=output}
            <tr>
                <td>{$output.type_name}</td>
                <td style="width:60px;"></td>
                <td class="right nowrap" style="width: 100px;">
                  <div class="crm-configure-actions">
                        <span class="btn-slide crm-hover-button">{ts}Configure{/ts}
                        <ul class="panel">
                            <li><a class="action-item crm-hover-button" href="{crmURL p="civicrm/dataprocessor/form/output" q="reset=1&action=update&data_processor_id=`$output.data_processor_id`&id=`$output.id`"}">{ts}Edit{/ts}</a></li>
                            <li><a class="action-item crm-hover-button" href="{crmURL p="civicrm/dataprocessor/form/output" q="reset=1&action=delete&data_processor_id=`$output.data_processor_id`&id=`$output.id`"}">{ts}Remove{/ts}</a></li>
                            {if isset($output.navigation_url) }
                                <li><a class="action-item crm-hover-button" href="{$output.navigation_url}">{ts}Execute{/ts}</a></li>
                            {/if}
                        </ul>
                        </span>
                  </div>
                </td>
            </tr>
        {/foreach}
    </table>

    <div class="crm-submit-buttons">
        <a class="add button" title="{ts}Add Output{/ts}" href="{$addOutputUrl}">
            <i class='crm-i fa-plus-circle'></i> {ts}Add Output{/ts}</a>
    </div>
</div>
{/crmScope}
