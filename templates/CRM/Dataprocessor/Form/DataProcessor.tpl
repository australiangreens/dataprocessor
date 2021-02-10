{crmScope extensionKey='dataprocessor'}
<div class="crm-submit-buttons">
  {include file="CRM/common/formButtons.tpl" location="top"}
</div>

{if $action eq 8}
  {* Are you sure to delete form *}
  <h3>{ts}Delete Data Processor{/ts}</h3>
  <div class="crm-block crm-form-block crm-data-processor_label-block">
    <div class="crm-section">{ts 1=$dataProcessor.title}Are you sure to delete data processor '%1'?{/ts}</div>
  </div>

{else}

<h3>{ts}Data Processor{/ts}</h3>
<div class="crm-block crm-form-block crm-data-processor_title-block">
  <div class="crm-section">
    <div class="label">{$form.title.label}</div>
    <div class="content">
      {$form.title.html}
      <span class="">
        {ts}System name:{/ts}&nbsp;
        <span id="systemName" style="font-style: italic;">{if ($dataProcessor)}{$dataProcessor.name}{/if}</span>
        <a href="javascript:void(0);" onclick="jQuery('#nameSection').removeClass('hiddenElement'); jQuery(this).parent().addClass('hiddenElement'); return false;">
          {ts}Change{/ts}
        </a>
      </span>
    </div>
    <div class="clear"></div>
  </div>
  <div id="nameSection" class="crm-section hiddenElement">
    <div class="label">{$form.name.label}</div>
    <div class="content">
      {$form.name.html}
      <p class="description">{ts}Leave empty to let the system generate a name. The name should consist of lowercase letters, numbers and underscore. E.g team_captains.{/ts}</p>
    </div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.description.label}</div>
    <div class="content">{$form.description.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.is_active.label}</div>
    <div class="content">{$form.is_active.html}</div>
    <div class="clear"></div>
  </div>
</div>

  {if $data_processor_id}
    <table>
      <tr>
        <td style="width: 50%;">
          {include file="CRM/Dataprocessor/Form/DataProcessorBlocks/Sources.tpl"}

          {if $data_processor_id && $sortFields && count($sortFields)}
            <h3>{ts}Default Sort{/ts}</h3>
            <div class="crm-block crm-form-block crm-data-processor_outputs-block">
                <ul id="defaultsort" class="crm-checkbox-list crm-sortable-list" style="width: 100%;">
                  {foreach from=$sortFields item="sortFieldLabel" key="sortFieldValue"}
                    <li id="defaultsort-{$sortFieldValue}">
                      {if $defaultSortUseIcon}
                        <i class="crm-i fa-arrows crm-grip" style="float:left;"></i>
                      {/if}
                      {$form.defaultSort.$sortFieldValue.html}
                    </li>
                  {/foreach}
                </ul>
            </div>
          {/if}

          {include file="CRM/Dataprocessor/Form/DataProcessorBlocks/Outputs.tpl"}
        </td>
        <td style="width: 50%;">
          {include file="CRM/Dataprocessor/Form/DataProcessorBlocks/Fields.tpl"}
          {include file="CRM/Dataprocessor/Form/DataProcessorBlocks/Filters.tpl"}
        </td>
      </tr>
    </table>
  {/if}

  <script type="text/javascript">
    {literal}
    CRM.$(function($) {
      var id = {/literal}{if ($dataProcessor)}{$dataProcessor.id}{else}false{/if}{literal};

      $('#title').on('blur', function() {
        var title = $('#title').val();
        if ($('#nameSection').hasClass('hiddenElement') && !id) {
          CRM.api3('DataProcessor', 'check_name', {
            'title': title
          }).done(function (result) {
            $('#systemName').html(result.name);
            $('#name').val(result.name);
          });
        }
      });

      function getSorting(e, ui) {
        var params = [];
        var y = 0;
        var items = $("#defaultsort li");
        if (items.length > 0) {
          for (var y = 0; y < items.length; y++) {
            var idState = items[y].id.split('-');
            params[y + 1] = idState[1];
          }
        }
        $('#default_sort_weight').val(params.toString());
      }

      $("#defaultsort").sortable({
        placeholder: 'ui-state-highlight',
        update: getSorting
      });
    });
    {/literal}
  </script>

  <style type="text/css">{literal}
    .crm-container ul.crm-sortable-list li label::after {
      display: block;
      font-family: "FontAwesome";
      content: "\f047";
      position: absolute;
      left: 6px;
      top: 6px;
      font-size: 10px;
      color: grey;
    }

    .crm-container ul.crm-checkbox-list.crm-sortable-list li {
      padding: 4px 7px;
      list-style: none;
    }

    .crm-container ul.crm-checkbox-list.crm-sortable-list li input {
      left: 20px;
      top: 4px;
    }
    {/literal}
    {if $defaultSortUseIcon}{literal}
    .crm-container ul.crm-checkbox-list.crm-sortable-list {
      border: 1px solid #a5a5a5;
      padding: 0px;
      background-color: white;
    }
    .crm-container ul.crm-checkbox-list.crm-sortable-list li i {
      margin-top: 3px;
    }
    {/literal}{/if}
    </style>

{/if}

<div class="crm-submit-buttons">
  {include file="CRM/common/formButtons.tpl" location="bottom"}
</div>

<style type="text/css">
  {literal}
  .crm-container .CRM_Dataprocessor_Form_DataProcessor .crm-configure-actions .btn-slide {
    padding-right: 15px !important;
    text-indent: initial;
    width: auto;
  }
  {/literal}
</style>
{/crmScope}
