<div class="form-horizontal">
  <input type="hidden" class="pf-wh-id" value="{$wh.id_webhook|default:0|intval}">
  <div class="form-group">
    <label class="col-sm-2 control-label">Name</label>
    <div class="col-sm-10">
      <input type="text" class="form-control pf-wh-name" value="{$wh.name|default:''|escape}">
    </div>
  </div>
  <div class="form-group">
    <label class="col-sm-2 control-label">Method</label>
    <div class="col-sm-2">
      <select class="form-control pf-wh-method">
        {foreach ['POST','GET','PUT'] as $m}
          <option value="{$m}" {if ($wh.method|default:'POST')==$m}selected{/if}>{$m}</option>
        {/foreach}
      </select>
    </div>
    <label class="col-sm-1 control-label">URL</label>
    <div class="col-sm-7">
      <input type="text" class="form-control pf-wh-url" value="{$wh.url|default:''|escape}" placeholder="https://...">
    </div>
  </div>
  <div class="form-group">
    <label class="col-sm-2 control-label">Headers <small>key: value</small></label>
    <div class="col-sm-10">
      <textarea class="form-control pf-wh-headers" rows="3" style="font-family:monospace"
                placeholder="Authorization: Bearer token&#10;X-Source: prestaform">{foreach ($wh.headers|default:[]) as $h}{$h.key|escape}: {$h.value|escape}
{/foreach}</textarea>
    </div>
  </div>
  <div class="form-group">
    <label class="col-sm-2 control-label">Fields</label>
    <div class="col-sm-10">
      <label><input type="radio" class="pf-wh-fields-all" name="pf_wh_fields_{$wh.id_webhook|default:'new'}"
        value="all" {if !$wh.field_map}checked{/if}> All fields</label>
      &nbsp;
      <label><input type="radio" class="pf-wh-fields-sel" name="pf_wh_fields_{$wh.id_webhook|default:'new'}"
        value="select" {if $wh.field_map}checked{/if}> Select fields:</label>
      <div class="pf-wh-field-checkboxes" style="margin-top:8px;{if !$wh.field_map}display:none{/if}">
        {foreach $fields as $f}{if $f.name}
          <label style="margin-right:12px">
            <input type="checkbox" class="pf-wh-field-chk" value="{$f.name|escape}"
              {if is_array($wh.field_map) && in_array($f.name, $wh.field_map)}checked{/if}>
            {$f.name|escape}
          </label>
        {/if}{/foreach}
      </div>
    </div>
  </div>
  <div class="form-group">
    <label class="col-sm-2 control-label">Retry / Timeout</label>
    <div class="col-sm-2">
      <input type="number" class="form-control pf-wh-retry" value="{$wh.retry_count|default:3|intval}" min="0" max="10" placeholder="Retries">
    </div>
    <div class="col-sm-2">
      <input type="number" class="form-control pf-wh-timeout" value="{$wh.timeout_seconds|default:10|intval}" min="3" max="60" placeholder="Timeout (s)">
    </div>
    <div class="col-sm-2">
      <select class="form-control pf-wh-active">
        <option value="1" {if ($wh.active|default:1)}selected{/if}>Active</option>
        <option value="0" {if !($wh.active|default:1)}selected{/if}>Inactive</option>
      </select>
    </div>
  </div>
  <div class="form-group">
    <div class="col-sm-offset-2 col-sm-10">
      <button type="button" class="btn btn-primary pf-wh-save"><i class="icon-save"></i> Save Webhook</button>
      <button type="button" class="btn btn-default pf-wh-test"><i class="icon-bolt"></i> Test Webhook</button>
      <button type="button" class="btn btn-danger pf-wh-delete"><i class="icon-trash"></i> Delete</button>
      <span class="pf-wh-test-result" style="margin-left:10px;font-size:12px"></span>
    </div>
  </div>
</div>
