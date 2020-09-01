{crmScope extensionKey='dataprocessor'}
    <h3>{ts}Default Filter Value{/ts}</h3>
    <div class="crm-block crm-form-block crm-data-processor_filter_value-block">
        <table>
            <tr>
                <th>{ts}Name{/ts}</th>
                <th>{ts}Operator{/ts}</th>
                <th>{ts}Value{/ts}</th>
            </tr>
            {include file=$filter_template filterName=$filter.alias filter=$filter}
        </table>
    </div>

    <div class="crm-submit-buttons">
        {include file="CRM/common/formButtons.tpl" location="bottom"}
    </div>

  <script type="text/javascript">
    var val = "dnc";
    {assign var=fieldOp value=$filter.alias|cat:"_op"}
    {if !($filter.operatorType & 4) && !$filter.no_display && $form.$fieldOp.html}
        var val = document.getElementById("{$fieldOp}").value;
    {/if}
    showHideMaxMinVal("{$filter.alias}", val );
    initializeOperator("{$filter.alias}");
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

    CRM.$(function($) {
      cj("#{/literal}{$fieldOp}{literal}").change(function() {
        var val = $(this).val();
        showHideMaxMinVal("{/literal}{$filter.alias}{literal}", val );
      });
    });
    {/literal}
  </script>
{/crmScope}
