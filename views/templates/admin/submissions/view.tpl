<div class="panel">
  <div class="panel-heading">
    <i class="icon-eye"></i> Submission #{$submission.id_submission|intval}
    <a href="{$base_url|escape}" class="btn btn-default btn-sm pull-right">
      <i class="icon-arrow-left"></i> Back
    </a>
  </div>
  <div class="panel-body">

    <p class="text-muted" style="margin-bottom:15px">
      Full details of a single form submission. The table below lists every field that was filled in by the visitor.
      To export multiple submissions at once, return to the list and use the <strong>Export CSV</strong> button.
    </p>

    <dl class="dl-horizontal">
      <dt>Form</dt><dd>{$submission.form_name|default:'—'|escape}</dd>
      <dt>Date</dt><dd>{$submission.date_add|default:''|escape}</dd>
      <dt>IP</dt>  <dd>{$submission.ip_address|default:''|escape}</dd>
    </dl>
    <hr>
    <table class="table table-striped">
      <thead><tr><th>Field</th><th>Value</th></tr></thead>
      <tbody>
        {if !empty($submission.data)}
        {foreach $submission.data as $k => $v}
        <tr>
          <td><strong>{if isset($field_labels[$k])}{$field_labels[$k]|escape}{else}{$k|replace:'-':' '|replace:'_':' '|capitalize}{/if}</strong></td>
          <td>{$v|escape|nl2br}</td>
        </tr>
        {/foreach}
        {/if}
      </tbody>
    </table>
  </div>
</div>
