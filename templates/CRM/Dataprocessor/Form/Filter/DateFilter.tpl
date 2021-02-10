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
          {include file="CRM/Dataprocessor/Form/Filter/DateRange.tpl" fieldName=$filter.alias from='_from' to='_to'}
      {else}
          <span id="{$filterVal}_cell">
            {$form.$filterVal.label}&nbsp;{$form.$filterVal.html}</span>
            <span id="{$filterMin}_max_cell">{$form.$filterMin.label}&nbsp;{$form.$filterMin.html}&nbsp;&nbsp;{$form.$filterMax.label}&nbsp;{$form.$filterMax.html}</span>
            <p class="description">{ts 1='https://www.php.net/manual/en/datetime.formats.php'}Enter a date according to PHP Date Function. <br />
                E.g. '2020-12-31' or '2 years ago'. <br />
                See <a href="%1"php.net</a> for more information.{/ts}</p>
      {/if}
    </td>
</tr>

{/crmScope}
