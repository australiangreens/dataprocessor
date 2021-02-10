{include file="CRM/Dataprocessor/Form/Output/UIOutput/CriteriaForm.tpl"}

{if (isset($output.configuration.help_text) && $output.configuration.help_text)}
    <div class="help">
        {$output.configuration.help_text}
    </div>
{/if}


{include file="CRM/DataprocessorSearch/Form/Debug.tpl"}

{if (isset($rows) && !empty($rows))}
    <div class="crm-content-block">
        <div class="crm-results-block">
            {* This section handles form elements for action task select and submit *}
            <div class="crm-search-tasks">
                {include file="CRM/common/searchResultTasks.tpl"}
                {include file="CRM/DataprocessorSearch/Form/OtherOutputs.tpl"}
            </div>

            {include file="CRM/common/pager.tpl" location="top"}

            <div class="crm-search-results">
                <a href="#" class="crm-selection-reset crm-hover-button"><i class="crm-i fa-times-circle-o"></i> {ts}Reset all selections{/ts}</a>
                <table class="selector row-highlight">
                    <thead class="sticky">
                    <tr>
                        <th scope="col" title="Select Rows">{$form.toggleSelect.html}</th>
                        <th scope="col"></th>
                        {foreach from=$columnHeaders key=headerName item=headerTitle}
                            <th scope="col">
                                {if ($sort->_response.$headerName)}
                                    {$sort->_response.$headerName.link}
                                {else}
                                    {$headerTitle}
                                {/if}
                            </th>
                        {/foreach}
                      {if $output.configuration.link_to_view_contact}
                        <th scope="col"></th>
                      {/if}
                    </tr></thead>


                    {foreach from=$rows item=row}
                        <tr id='rowid{$row.id}' class="{cycle values="odd-row,even-row"}">
                            {assign var=cbName value=$row.checkbox}
                            {assign var=id value=$row.id}
                            {assign var=record value=$row.record}
                            <td>{$form.$cbName.html}</td>
                            <td>{$row.contact_type}</td>
                            {foreach from=$columnHeaders key=headerName item=headerTitle}
                                {assign var=columnValue value=$record.$headerName}
                                <td>{$columnValue}</td>
                            {/foreach}
                          {if $output.configuration.link_to_view_contact}
                            <td>
                                {if ($row.url)}
                                    <a href="{$row.url}">
                                        {$row.link_text}
                                    </a>
                                {/if}
                            </td>
                          {/if}
                        </tr>
                    {/foreach}

                </table>
            </div>

            {include file="CRM/common/pager.tpl" location="bottom"}
        </div>
    </div>

    {include file="CRM/DataprocessorSearch/Form/ResultsJavascript.tpl"}
{elseif isset($no_result_text)}
  <div class="crm-content-block">
    <div class="crm-results-block">
      {$no_result_text}
    </div>
  </div>
{/if}
