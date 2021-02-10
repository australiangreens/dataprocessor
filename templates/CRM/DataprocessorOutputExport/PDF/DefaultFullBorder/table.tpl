{if $sectionTitle}
  <h2>{$sectionTitle}</h2>
{/if}
{if ($header)}
  {$header}
{/if}
<table style="width: 100%;">
  <thead>
  <tr>
    {foreach from=$headerColumns item=title key=columnName}
      {if (!in_array($columnName, $hiddenFields))}
        <th>{$title|htmlentities}</th>
      {/if}
    {/foreach}
    <th styile="
      {if (isset($configuration.additional_column_width) && $configuration.additional_column_width)}width: {$configuration.additional_column_width};{/if}
    ">
      {if (isset($configuration.additional_column_title))}{$configuration.additional_column_title}{/if}
    </th>
  </tr>
  </thead>
  {$rows}
</table>
