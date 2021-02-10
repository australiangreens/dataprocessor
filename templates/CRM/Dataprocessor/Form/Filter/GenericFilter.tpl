{crmScope extensionKey='dataprocessor'}
{assign var=fieldOp     value=$filter.alias|cat:"_op"}
{assign var=filterVal   value=$filter.alias|cat:"_value"}
{assign var=filterMin   value=$filter.alias|cat:"_min"}
{assign var=filterMax   value=$filter.alias|cat:"_max"}

<tr>
    <td class="label">{$filter.title}</td>
    <td>
      {if $form.$fieldOp.html}
      <span class="filter-processor-element filter-{$filter.alias}">{$form.$fieldOp.html}</span>
      <span class="filter-processor-show-close filter-{$filter.alias}">&nbsp;</span>
      {/if}
    </td>
    <td>
      {if $filter.type == 'Date' || $filter.type == 'Timestamp'}
          {include file="CRM/Dataprocessor/Form/Filter/DateRange.tpl" fieldName=$filter.alias from='_low' to='_high'}
      {else}
          <span id="{$filterVal}_cell">{$form.$filterVal.label}&nbsp;{$form.$filterVal.html}</span>
          <span id="{$filterMin}_max_cell">{$form.$filterMin.label}&nbsp;{$form.$filterMin.html}&nbsp;&nbsp;{$form.$filterMax.label}&nbsp;{$form.$filterMax.html}</span>
      {/if}
    </td>
</tr>

{/crmScope}

{literal}
<script type="text/javascript">
  CRM.$(function($) {
    cj("#{/literal}{$fieldOp}{literal}").change(function() {
      var val = $(this).val();
      if (val == 'current_user' || val == 'null' || val == 'not null') {
        cj("#{/literal}{$filterVal}{literal}").addClass('hiddenElement');
      } else {
        cj("#{/literal}{$filterVal}{literal}").removeClass('hiddenElement');
      }
    }).change();
  });
</script>
{/literal}
