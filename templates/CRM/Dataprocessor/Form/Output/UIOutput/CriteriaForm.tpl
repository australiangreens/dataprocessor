{crmScope extensionKey='dataprocessor'}
{if $has_exposed_filters}
  <div class="crm-form-block crm-search-form-block">
      <div class="crm-accordion-wrapper crm-advanced_search_form-accordion {if (!empty($rows))}collapsed{/if}">
          <div class="crm-accordion-header crm-master-accordion-header">
              {if isset($criteriaFormTitle)}{$criteriaFormTitle}{else}{ts}Edit Search Criteria{/ts}{/if}
          </div>
          <!-- /.crm-accordion-header -->
          <div class="crm-accordion-body">
              <div id="searchForm" class="form-item">
                  <table>
                      <tr>
                          <th>{ts}Name{/ts}</th>
                          <th>{ts}Operator{/ts}</th>
                          <th>{ts}Value{/ts}</th>
                      </tr>
                      {foreach from=$filters key=filterName item=filter}
                          {include file=$filter.template filterName=$filter.alias filter=$filter.filter}
                      {/foreach}
                      {if $additional_criteria_template && is_array($additional_criteria_template)}
                        {foreach from=$additional_criteria_template item=template}
                          {include file=$template}
                        {/foreach}
                      {elseif $additional_criteria_template}
                        {include file=$additional_criteria_template}
                      {/if}
                  </table>

                  <div class="crm-submit-buttons">{include file="CRM/common/formButtons.tpl" location="botton"}</div>
              </div>
          </div>
      </div>
  </div>

  {literal}
  <script type="text/javascript">
      {/literal}
      {foreach from=$filters item=filter key=filterName}
              {literal}var val = "dnc";{/literal}
      {assign var=fieldOp     value=$filterName|cat:"_op"}
      {if !($field.operatorType & 4) && !$field.no_display && $form.$fieldOp.html}
              {literal}var val = document.getElementById("{/literal}{$fieldOp}{literal}").value;{/literal}
      {/if}
      {literal}showHideMaxMinVal( "filter-{/literal}{$filterName}{literal}", val );{/literal}
      {literal}initializeOperator( "filter-{/literal}{$filterName}{literal}");{/literal}
      {literal}
        CRM.$(function($) {
          cj("#{/literal}{$fieldOp}{literal}").change(function() {
            var val = $(this).val();
            showHideMaxMinVal("filter-{/literal}{$filter.alias}{literal}", val );
          });
        });
      {/literal}
      {/foreach}

      {literal}
      function showHideMaxMinVal( field, val ) {
        var fldVal    = field + "_value_cell";
        var fldMinMax = field + "_min_max_cell";
        if ( val == "bw" || val == "nbw" ) {
          cj('#' + fldVal ).hide();
          cj('#' + fldMinMax ).show();
        } else if (val =="nll" || val == "nnll") {
          cj('#' + fldVal).hide() ;
          cj('#' + field + '_value').val('');
          cj('#' + fldMinMax ).hide();
        } else {
          cj('#' + fldVal ).show();
          cj('#' + fldMinMax ).hide();
        }
      }

      function initializeOperator(filterName) {
        var currentOp = cj('.filter-processor-element.'+filterName+' select option:selected');
        cj('.filter-processor-element.'+filterName).addClass('hiddenElement');
        cj('.filter-processor-show-close.'+filterName).html(currentOp.html() + '&nbsp;<i class="crm-i fa-pencil">&nbsp;</i>');
        cj('.filter-processor-show-close.'+filterName).attr('title', '{/literal}{ts}Change{/ts}{literal}');
        cj('.filter-processor-show-close.'+filterName).addClass('crm-editable-enabled');
        cj('.filter-processor-show-close.'+filterName).click(function () {
          cj('.filter-processor-element.'+filterName).removeClass('hiddenElement');
          cj('.filter-processor-show-close.'+filterName).removeClass('crm-editable-enabled');
          cj('.filter-processor-show-close.'+filterName).addClass('hiddenElement');
        });
      }

  </script>
  {/literal}
{/if}
{/crmScope}
