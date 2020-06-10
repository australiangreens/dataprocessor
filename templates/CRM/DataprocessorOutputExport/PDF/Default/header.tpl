{foreach from=$headerColumns item=title key=columnName}
  {if (in_array($columnName, $headerFields))}
    <p><strong>{$title|htmlentities}</strong>: {$record.$columnName->formattedValue|htmlentities}</p>
  {/if}
{/foreach}
