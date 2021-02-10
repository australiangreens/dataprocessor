{crmScope extensionKey='dataprocessor'}

    <div class="crm-section">
        <div class="label">{$form.api_entity.label}</div>
        <div class="content">{$form.api_entity.html}</div>
        <div class="clear"></div>
    </div>
    <div class="crm-section">
        <div class="label">{$form.api_action.label}</div>
        <div class="content">{$form.api_action.html}</div>
        <div class="clear"></div>
    </div>
    <div class="crm-section">
        <div class="label">{$form.api_count_action.label}</div>
        <div class="content">{$form.api_count_action.html}</div>
        <div class="clear"></div>
    </div>
    <div class="crm-section">
        <div class="label">{$form.permission.label}</div>
        <div class="content">{$form.permission.html}</div>
        <div class="clear"></div>
    </div>

    {if $doc}
      <div class="crm-accordion-wrapper collapsed">
        <div class="crm-accordion-header hiddenElement table-bordered" id="docPreviewHeader">
          {ts}Documentation{/ts}
        </div>
        <div class="crm-accordion-body hiddenElement" id="docPreview">
        </div>
        <div class="crm-accordion-header">
          {ts}Documentation source{/ts}
        </div>
        <div class="crm-accordion-body">
          <div class="crm-block crm-form-block">
            <pre class="" id="mkDoc">{$doc}</pre>
          </div>
        </div>
      </div>

    {/if}

{/crmScope}

<script type="text/javascript">
  {literal}
  CRM.$(function($) {
    var md = window.markdownit({
      html: true,
      linkify: true,
      typographer: true
    });
    var result = md.render($('#mkDoc').text());
    $('#docPreviewHeader').removeClass('hiddenElement');
    $('#docPreview').removeClass('hiddenElement');
    $('#docPreview').html(result);
    $('#docPreview table tr:odd').addClass('odd');
    $('#docPreview table tr:even').addClass('even');

  });
  {/literal}
</script>
<style type="text/css">
{literal}
#docPreview code, #docPreview pre, #docPreview kbd {
  font-size: inherit !important;
}
#docPreview h2 {
  margin-top: 1em;
}
#docPreview table tr {
  border-bottom: 1px solid silver;
}
{/literal}
</style>
