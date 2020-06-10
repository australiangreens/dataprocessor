<html>
<head>
  <title>{$dataProcessor.title}</title>
</head>
<body>
<style>
  {literal}
  table { border-collapse: collapse; margin-bottom: 2em;}
  th, td { border-bottom: 1px solid black; }
  {/literal}
</style>
<h1>{$dataProcessor.title}</h1>
{if $configuration.header}
{$configuration.header}
{/if}
{if ($header)}
  {$header}
{/if}
{$content}
</body>
</html>
