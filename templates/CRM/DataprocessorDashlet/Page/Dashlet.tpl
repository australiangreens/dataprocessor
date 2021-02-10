<div id="dataprocessorDashlet_{$dataProcessorName}_{$context}"></div>

{literal}
<script type="text/javascript">
(function($) {
  var target = "#dataprocessorDashlet_{/literal}{$dataProcessorName}_{$context}{literal}";
  var form = CRM.loadForm(CRM.url('civicrm/dataprocessor/form/dashlet', {"reset": 1, "data_processor": "{/literal}{$dataProcessorName}{literal}"}), {
    "target": target,
    "dialog": false,
  }).on('crmFormSuccess', function(event, data) {
    $(target).crmSnippet('option', 'url', data.userContext).crmSnippet('refresh');
  });

})(CRM.$);
</script>
{/literal}
