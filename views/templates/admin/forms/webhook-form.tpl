<div class="form-horizontal">
  <input type="hidden" class="pf-wh-id" value="{$wh.id_webhook|default:0|intval}">

  <div class="form-group">
    <label class="col-sm-2 control-label">Name</label>
    <div class="col-sm-10">
      <input type="text" class="form-control pf-wh-name" value="{$wh.name|default:''|escape}" placeholder="e.g. Notify CRM">
      <p class="help-block">A label for your own reference. Not sent with the request. Use something descriptive like "Zapier Lead Capture" or "Slack Notification".</p>
    </div>
  </div>

  <div class="form-group">
    <label class="col-sm-2 control-label">Method &amp; URL</label>
    <div class="col-sm-2">
      {assign var="wh_method" value=$wh.method|default:'POST'}
      <select class="form-control pf-wh-method">
        {foreach ['POST','GET','PUT'] as $m}
          <option value="{$m}" {if $wh_method==$m}selected{/if}>{$m}</option>
        {/foreach}
      </select>
    </div>
    <div class="col-sm-8">
      <input type="text" class="form-control pf-wh-url" value="{$wh.url|default:''|escape}" placeholder="https://hooks.zapier.com/hooks/catch/…">
      <p class="help-block">
        The full HTTPS URL to send the data to. <strong>POST</strong> is recommended for most integrations (Zapier, Make, custom APIs).
        Use <strong>GET</strong> only if the receiving service explicitly requires it.
        The URL must start with <code>https://</code>.
      </p>
    </div>
  </div>

  <div class="form-group">
    <label class="col-sm-2 control-label">Headers</label>
    <div class="col-sm-10">
      <textarea class="form-control pf-wh-headers" rows="3" style="font-family:monospace"
                placeholder="Authorization: Bearer your-token&#10;X-Source: prestaform">{if !empty($wh.headers)}{foreach $wh.headers as $h}{$h.key|default:''|escape}: {$h.value|default:''|escape}
{/foreach}{/if}</textarea>
      <p class="help-block">Optional HTTP headers to include with the request — one per line in <code>Key: Value</code> format. Use this to pass API keys or authentication tokens required by the receiving service, e.g. <code>Authorization: Bearer abc123</code>.</p>
    </div>
  </div>

  <div class="form-group">
    <label class="col-sm-2 control-label">Fields to send</label>
    <div class="col-sm-10">
      {assign var="wh_field_map" value=$wh.field_map|default:null}
      <div style="margin-bottom:6px">
        <label style="font-weight:normal">
          <input type="radio" class="pf-wh-fields-all" name="pf_wh_fields_{$wh.id_webhook|default:'new'}"
            value="all" {if empty($wh_field_map)}checked{/if}>
          <strong>All fields</strong> — send every submitted field value in the payload
        </label>
      </div>
      <div>
        <label style="font-weight:normal">
          <input type="radio" class="pf-wh-fields-sel" name="pf_wh_fields_{$wh.id_webhook|default:'new'}"
            value="select" {if !empty($wh_field_map)}checked{/if}>
          <strong>Selected fields only</strong> — choose which fields to include below
        </label>
      </div>
      <div class="pf-wh-field-checkboxes" style="margin-top:10px;padding:10px;background:#f9f9f9;border:1px solid #eee;border-radius:3px;{if empty($wh_field_map)}display:none{/if}">
        {foreach $fields as $f}{if $f.name}
          <label style="margin-right:16px;font-weight:normal">
            <input type="checkbox" class="pf-wh-field-chk" value="{$f.name|escape}"
              {if !empty($wh_field_map) && in_array($f.name, $wh_field_map)}checked{/if}>
            {$f.name|escape}
          </label>
        {/if}{/foreach}
      </div>
      <p class="help-block">Choose whether to send all form fields or only specific ones. Limiting to selected fields is useful when the receiving service expects a fixed set of properties.</p>
    </div>
  </div>

  <div class="form-group">
    <label class="col-sm-2 control-label">Retry &amp; Timeout</label>
    <div class="col-sm-2">
      <label class="control-label" style="font-weight:normal;font-size:12px">Retries</label>
      <input type="number" class="form-control pf-wh-retry" value="{$wh.retry_count|default:3|intval}" min="0" max="10" placeholder="3">
    </div>
    <div class="col-sm-2">
      <label class="control-label" style="font-weight:normal;font-size:12px">Timeout (seconds)</label>
      <input type="number" class="form-control pf-wh-timeout" value="{$wh.timeout_seconds|default:10|intval}" min="3" max="60" placeholder="10">
    </div>
    {assign var="wh_active" value=$wh.active|default:1}
    <div class="col-sm-2">
      <label class="control-label" style="font-weight:normal;font-size:12px">Status</label>
      <select class="form-control pf-wh-active">
        <option value="1" {if $wh_active}selected{/if}>Active</option>
        <option value="0" {if !$wh_active}selected{/if}>Inactive</option>
      </select>
    </div>
    <div class="col-sm-10 col-sm-offset-2" style="margin-top:4px">
      <p class="help-block">
        <strong>Retries</strong> — how many times to retry the request if it fails (0–10). Recommended: 3.<br>
        <strong>Timeout</strong> — how many seconds to wait for a response before giving up (3–60). Recommended: 10.<br>
        <strong>Status</strong> — set to <em>Inactive</em> to temporarily pause this webhook without deleting it.
      </p>
    </div>
  </div>

  <div class="form-group">
    <div class="col-sm-offset-2 col-sm-10">
      <button type="button" class="btn btn-primary pf-wh-save"><i class="icon-save"></i> Save Webhook</button>
      <button type="button" class="btn btn-default pf-wh-test"><i class="icon-bolt"></i> Test Webhook</button>
      <button type="button" class="btn btn-danger pf-wh-delete"><i class="icon-trash"></i> Delete</button>
      <span class="pf-wh-test-result" style="margin-left:10px;font-size:12px"></span>
      <p class="help-block" style="margin-top:8px">
        <strong>Save Webhook</strong> — saves this webhook configuration.<br>
        <strong>Test Webhook</strong> — sends a test payload to the URL right now so you can verify the connection is working. Check the response shown next to the button.<br>
        <strong>Delete</strong> — permanently removes this webhook.
      </p>
    </div>
  </div>
</div>
