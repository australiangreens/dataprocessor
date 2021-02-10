# {$dataprocessor.title}

{$dataprocessor.description}

This API contains the following actions:

* `{$output.api_entity}.{$output.api_action}` to retrieve data.
* `{$output.api_entity}.{$output.api_count_action}` to retrieve the number of records.
* `{$output.api_entity}.getfields` to retrieve the fields available in this api


## `{$output.api_entity}.{$output.api_action}`

**Filters**
{if (count($filters))}

Use the filter criteria below to filter data in this API.

| Field | Required | Type
--- | --- | ---
{foreach from=$filters item=filter}
{$filter.name} | {if $filter.required}Required{/if} | {$filter.data_type}
{/foreach}
{else}
This API does not have any filter criteria
{/if}

**Output**

This API outputs the following:

```json
{literal}{{/literal}
  "is_error": 0,
  "version": 3,
  "count": 1,
  "values": [
    {literal}{{/literal}
    {foreach from=$fields item=field name=fields}
      "{$field.name}": {$field.data_type}{if !$smarty.foreach.fields.last},
{/if}
    {/foreach}

    {literal}}{/literal}
  ]
{literal}}{/literal}
```

**Examples**

Rest:

`{$resourceBase}/extern/rest.php?entity={$output.api_entity}&action={$output.api_action}&api_key=userkey&key=sitekey&json={literal}{{/literal}{literal}}{/literal}`

With rest you can add the filters in the json parameters.

PHP:

```php

$params = [];
// Each filter can be  added to the param array
// $params['name_of_the_filter'] = 'value';
// Or with an operator
// $params['name_of_the_filter'] = ["NOT IN": ['value1', 'value2']];

$result = civicrm_api3('{$output.api_entity}', '{$output.api_action}', $params);

```


## `{$output.api_entity}.{$output.api_count_action}`

**Filters**
{if (count($filters))}

Use the filter criteria below to filter data in this API.

| Field | Required | Type
--- | --- | ---
{foreach from=$filters item=filter}
  {$filter.name} | {if $filter.required}Required{/if} | {$filter.data_type}
{/foreach}
{else}
  This API does not have any filter criteria
{/if}

**Output**

This API outputs the following:

```json
{literal}{{/literal}
"is_error": 0,
"version": 3,
"count": 1
{literal}}{/literal}
```

**Examples**

Rest:

`{$resourceBase}/extern/rest.php?entity={$output.api_entity}&action={$output.api_count_action}&api_key=userkey&key=sitekey&json={literal}{{/literal}{literal}}{/literal}`

With rest you can add the filters in the json parameters.

PHP:

```php

$params = [];
// Each filter can be  added to the param array
// $params['name_of_the_filter'] = 'value';
// Or with an operator
// $params['name_of_the_filter'] = ["NOT IN": ['value1', 'value2']];

$result = civicrm_api3('{$output.api_entity}', '{$output.api_count_action}', $params);

```


## `{$output.api_entity}.getfields`

**Filters**

Use the filter criteria below to filter data in this API.

| Field | Value | Required | Type
--- | --- | ---
api_action | {$output.api_entity} | Yes | String

**Examples**

Rest:

`{$resourceBase}/extern/rest.php?entity={$output.api_entity}&action=getfields&api_key=userkey&key=sitekey&json={literal}{{/literal}"api_action":"{$output.api_action}"{literal}}{/literal}`

With rest you can add the filters in the json parameters.

PHP:

```php

$params = [];
$params['api_action'] = {$output.api_action};

$result = civicrm_api3('{$output.api_entity}', 'getfields', $params);

```





