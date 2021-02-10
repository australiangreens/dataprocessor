{crmScope extensionKey='dataprocessor'}
    <div class="crm-section">
        <div class="label">{$form.contact_id_field.label}</div>
        <div class="content">{$form.contact_id_field.html}</div>
        <div class="clear"></div>
    </div>
    <div class="crm-section">
      <div class="label">{$form.relationship_type_checkboxes.label}</div>
      <div class="content">
        <ul id="relationship_types" class="crm-checkbox-list crm-sortable-list" style="width: 600px;">
          {foreach from=$relationship_types item="relationshipType"}
            <li id="relationship-type-{$relationshipType}">
              {if $useSortIcon}
                <i class="crm-i fa-arrows crm-grip" style="float:left;"></i>
              {/if}
              {$form.relationship_type_checkboxes.$relationshipType.html}
            </li>
          {/foreach}
        </ul>
      </div>
      <div class="clear"></div>
    </div>
    <div class="crm-section">
      <div class="label">{$form.separator.label}</div>
      <div class="content">{$form.separator.html}</div>
      <div class="clear"></div>
    </div>
    <div class="crm-section">
        <div class="label"></div>
        <div class="content">{$form.show_label.html}&nbsp;{$form.show_label.label}</div>
        <div class="clear"></div>
    </div>
    <div class="crm-section">
      <div class="label"></div>
      <div class="content">{$form.include_deceased.html} {$form.include_deceased.label}</div>
      <div class="clear"></div>
    </div>
    <div class="crm-section">
      <div class="label">{$form.sort.label}</div>
      <div class="content">{$form.sort.html}</div>
      <div class="clear"></div>
    </div>
{/crmScope}

<script type="text/javascript">
  {literal}
  CRM.$(function($) {
    function getRelstionshipTypeSorting(e, ui) {
      var params = [];
      var y = 0;
      var items = $("#relationship_types li");
      if (items.length > 0) {
        for (var y = 0; y < items.length; y++) {
          var idState = items[y].id.substring(18);
          params[y + 1] = idState;
        }
      }
      $('#sorted_relationship_types').val(params.toString());
    }

    $("#relationship_types").sortable({
      placeholder: 'ui-state-highlight',
      update: getRelstionshipTypeSorting
    });
  });
  {/literal}
</script>

<style type="text/css">{literal}
  .crm-container ul.crm-sortable-list li label::after {
    display: block;
    font-family: "FontAwesome";
    content: "\f047";
    position: absolute;
    left: 6px;
    top: 6px;
    font-size: 10px;
    color: grey;
  }

  .crm-container ul.crm-checkbox-list.crm-sortable-list li {
    padding: 4px 7px;
    list-style: none;
  }

  .crm-container ul.crm-checkbox-list.crm-sortable-list li input {
    left: 20px;
    top: 4px;
  }
  {/literal}
  {if $useSortIcon}{literal}
  .crm-container ul.crm-checkbox-list.crm-sortable-list {
    border: 1px solid #a5a5a5;
    padding: 0px;
    background-color: white;
  }
  .crm-container ul.crm-checkbox-list.crm-sortable-list li i {
    margin-top: 3px;
  }
  {/literal}{/if}
</style>
