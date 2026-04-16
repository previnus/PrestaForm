{extends file="helpers/view/view.tpl"}
{block name="override_tpl"}
<div class="panel">
  <div class="panel-heading">
    <i class="icon-eye"></i> Submission #{$submission.id_submission|intval}
    <a href="{$base_url|escape}" class="btn btn-default btn-sm pull-right">
      <i class="icon-arrow-left"></i> Back
    </a>
  </div>
  <div class="panel-body">
    <dl class="dl-horizontal">
      <dt>Form</dt><dd>{$submission.form_name|default:'—'|escape}</dd>
      <dt>Date</dt><dd>{$submission.date_add|escape}</dd>
      <dt>IP</dt>  <dd>{$submission.ip_address|escape}</dd>
    </dl>
    <hr>
    <table class="table table-striped">
      <thead><tr><th>Field</th><th>Value</th></tr></thead>
      <tbody>
        {foreach $submission.data as $k => $v}
        <tr>
          <td><strong>{$k|escape}</strong></td>
          <td>{if is_array($v)}{$v|@implode:', '|escape}{else}{$v|escape|nl2br}{/if}</td>
        </tr>
        {/foreach}
      </tbody>
    </table>
  </div>
</div>
{/block}
