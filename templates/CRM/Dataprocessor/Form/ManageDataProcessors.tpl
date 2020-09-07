{crmScope extensionKey='dataprocessor'}

<div class="crm-content-block">

    <div class="crm-block crm-form-block crm-basic-criteria-form-block">
        <div class="crm-accordion-wrapper crm-data-processor_search-accordion collapsed">
            <div class="crm-accordion-header crm-master-accordion-header">{ts}Search data processors{/ts}</div><!-- /.crm-accordion-header -->
            <div class="crm-accordion-body">
                <table class="form-layout">
                    <tbody>
                        <tr>
                            <td style="width: 25%;">
                                <label>{$form.title.label}</label><br>
                                {$form.title.html}
                            </td>
                            <td style="width: 25%;">
                                <label>{$form.is_active.label}</label><br>
                                {$form.is_active.html}
                            </td>
                            <td style="width: 25%;"></td>
                            <td style="width: 25%;"></td>
                        </tr>
                        <tr>
                            <td>
                                <label>{$form.description.label}</label><br>
                                {$form.description.html}
                            </td>
                            <td></td>
                            <td></td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
                <div class="crm-submit-buttons">
                    {include file="CRM/common/formButtons.tpl"}
                </div>
            </div><!- /.crm-accordion-body -->
        </div><!-- /.crm-accordion-wrapper -->
    </div><!-- /.crm-form-block -->


    <div class="action-link">
        <a class="button" href="{crmURL p="civicrm/dataprocessor/form/edit" q="reset=1&action=add" }">
            <i class="crm-i fa-plus-circle">&nbsp;</i>
            {ts}Add dataprocessor{/ts}
        </a>
        <a class="button" href="{crmURL p="civicrm/dataprocessor/form/import" q="reset=1&action=add" }">
            <i class="crm-i fa-upload">&nbsp;</i>
            {ts}Import data processor{/ts}
        </a>
    </div>

    <div class="clear"></div>

    <div class="crm-results-block">
        {include file="CRM/common/pager.tpl" location="top"}

        <div class="crm-search-results">
            <table class="selector row-highlight">
                <thead class="sticky">
                <tr>
                    <th scope="col" >{ts}Data Processor{/ts}</th>
                    <th scope="col" >{ts}Description{/ts}</th>
                    <th scope="col" >{ts}Is active{/ts}</th>
                    <th scope="col" >{ts}Status{/ts}</th>
                    <th scope="col" >{ts}Outputs{/ts}</th>
                    <th>&nbsp;</th>
                </tr>
                </thead>
                {foreach from=$data_processors item=data_processor}
                    <tr>
                        <td>{$data_processor.title}</td>
                        <td>{$data_processor.description}</td>
                        {if $data_processor.is_active eq 1}
                            <td><span><a href="{crmURL p='civicrm/dataprocessor/form/edit' q="reset=1&action=disable&id=`$data_processor.id`"}"
                                         class="" title="{ts}Disable Data Processor{/ts}">{ts}Enabled{/ts}</a></span></td>
                        {else}
                            <td><span><a href="{crmURL p='civicrm/dataprocessor/form/edit' q="reset=1&action=enable&id=`$data_processor.id`"}"
                                         class="" title="{ts}Enable Data Processor{/ts}">{ts}Disabled{/ts}</a></span></td>
                        {/if}
                        <td>
                            {$data_processor.status_label}
                            {if ($data_processor.status eq 3)}
                                <span>
                                    <a href="{crmURL p='civicrm/dataprocessor/form/edit' q="reset=1&action=revert&id=`$data_processor.id`"}"  class="" title="{ts}Revert Data Processor{/ts}">
                                        {ts}Revert{/ts}
                                    </a>
                                </span>
                            {/if}
                        </td>
                        <td class="crm-dataprocessor-navigation">
                            {if $data_processor.navigation}
                                {foreach from=$data_processor.navigation item=navigationItem}
                                  {if $navigationItem.url}
                                    <a href="{$navigationItem.url}" class="action-item" title="{$navigationItem.title}">{$navigationItem.type}</a>
                                  {else}
                                    <span class="">{$navigationItem.type}</span>
                                  {/if}
                                {/foreach}
                            {/if}
                        </td>
                      <td class="right nowrap" style="width: 100px;">
                        <div class="crm-configure-actions">
                        <span class="btn-slide crm-hover-button">{ts}Actions{/ts}
                        <ul class="panel">
                          <li><a class="action-item crm-hover-button" href="{crmURL p='civicrm/dataprocessor/form/edit' q="reset=1&action=update&id=`$data_processor.id`"}"title="{ts}Edit Data Processor{/ts}">{ts}Edit{/ts}</a></li>
                          <li><a class="action-item crm-hover-button" href="{crmURL p='civicrm/dataprocessor/form/clone' q="reset=1&action=add&id=`$data_processor.id`"}" title="{ts}Clone Data Processor{/ts}">{ts}Clone{/ts}</a></li>
                          <li><a class="action-item crm-hover-button" href="{crmURL p='civicrm/dataprocessor/form/edit' q="reset=1&action=export&id=`$data_processor.id`"}" title="{ts}Export Data Processor{/ts}">{ts}Export{/ts}</a></li>
                          <li><a class="action-item crm-hover-button" href="{crmURL p='civicrm/dataprocessor/form/edit' q="reset=1&action=delete&id=`$data_processor.id`"}" title="{ts}Delete Data Processor{/ts}">{ts}Delete{/ts}</a></li>
                        </ul>
                        </span>
                        </div>
                        </td>
                    </tr>
                {/foreach}
            </table>
        </div>

        {include file="CRM/common/pager.tpl" location="bottom"}
    </div>
</div>
<style type="text/css">
{literal}
.crm-container .CRM_Dataprocessor_Form_ManageDataProcessors .crm-configure-actions .btn-slide {
  padding-right: 15px !important;
  text-indent: initial;
  width: auto;
}
{/literal}
</style>
{/crmScope}
