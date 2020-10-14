<script type="text/javascript">
    {literal}
    CRM.$(function($) {
      // Clear any old selection that may be lingering in quickform
      $("input.select-row, input.select-rows", 'form.crm-search-form').prop('checked', false).closest('tr').removeClass('crm-row-selected');
      // Retrieve stored checkboxes
      var selectedIds = {/literal}{$selectedIds|@json_encode}{literal};
      if (selectedIds.length > 0) {
        $('#mark_x_' + selectedIds.join(',#mark_x_') + ',input[name=radio_ts][value=ts_sel]').trigger('click');
      }
      $('.crm-search-tasks .action-links .action-link .other-output-button').on('click', function() {
        var output_id = $(this).data('output-id');
        var $form = $(this).closest('form');
        $form.find('input[name=export_id]').val(output_id);
        $form.submit();
        // The form triggers a download, therefore the following code continues
        // to run as the page is still loaded.
        $form.find('input[name=export_id]').val('');
        return false;
      });
      $('.crm-form-submit').on('click', function() {
        $('input[name=export_id]').val('');
      })
    });
    {/literal}
</script>
