<tr>
  {foreach from=$record item=value key=field}
    {if (!in_array($field, $hiddenFields))}
      <td>{$value->formattedValue|htmlentities}</td>
    {/if}
  {/foreach}
  {if (isset($configuration.additional_column) && $configuration.additional_column)}
    <td styile="
      {if (isset($configuration.additional_column_width) && $configuration.additional_column_width)}width: {$configuration.additional_column_width};{/if}
      {if (isset($configuration.additional_column_height) && $configuration.additional_column_height)}height: {$configuration.additional_column_height};{/if}
    ">
      &nbsp;
    </td>
  {/if}
</tr>
