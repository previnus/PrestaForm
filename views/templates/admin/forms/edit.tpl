{extends file="helpers/view/view.tpl"}
{block name="override_tpl"}
<div class="panel">
  <div class="panel-heading">
    <i class="icon-edit"></i>
    {if $form.id_form > 0}Edit Form: {$form.name|escape}{else}New Form{/if}
    <a href="{$base_url|escape}" class="btn btn-default btn-sm pull-right">
      <i class="icon-arrow-left"></i> Back to Forms
    </a>
  </div>

  {* Tab Navigation *}
  <ul class="nav nav-tabs" id="pfFormTabs">
    <li class="active"><a href="#tab-builder"    data-toggle="tab">Form Builder</a></li>
    <li>              <a href="#tab-mail"        data-toggle="tab">Mail</a></li>
    <li>              <a href="#tab-webhooks"    data-toggle="tab">Webhooks</a></li>
    <li>              <a href="#tab-conditions"  data-toggle="tab">Conditions</a></li>
    <li>              <a href="#tab-settings"    data-toggle="tab">Settings</a></li>
  </ul>

  <div class="tab-content" style="padding:20px">

    {* ── Tab 1: Form Builder ── *}
    <div class="tab-pane active" id="tab-builder">
      <form method="post" action="{$base_url|escape}">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id_form" value="{$form.id_form|intval}">

        <div class="form-group">
          <label>Form Name</label>
          <input type="text" name="name" class="form-control" value="{$form.name|escape}" required>
        </div>

        <div class="row">
          <div class="col-lg-3" id="pf-tag-panel">
            <label>Insert Tag</label>
            <div class="list-group" id="pf-tag-buttons">
              {foreach ['text','email','tel','number','date','textarea','select','checkbox','radio','file','hidden','recaptcha','submit'] as $tagType}
              <button type="button" class="list-group-item pf-tag-btn" data-type="{$tagType}">
                <code>[{$tagType}]</code>
              </button>
              {/foreach}
            </div>
          </div>

          <div class="col-lg-9">
            <label>Form Template <small class="text-muted">— write HTML + [tag shortcodes]</small></label>
            <textarea name="template" id="pf-template" class="form-control" rows="20"
                      style="font-family:monospace">{$form.template|escape}</textarea>
          </div>
        </div>

        <div style="margin-top:15px">
          <button type="submit" class="btn btn-primary"><i class="icon-save"></i> Save Form</button>
        </div>
      </form>

      {* Tag Generator Modal *}
      <div class="modal fade" id="pfTagModal" tabindex="-1">
        <div class="modal-dialog">
          <div class="modal-content">
            <div class="modal-header">
              <button type="button" class="close" data-dismiss="modal">&times;</button>
              <h4 class="modal-title">Insert Tag: <span id="pfTagModalTitle"></span></h4>
            </div>
            <div class="modal-body" id="pfTagModalBody">
              {* Populated by form-builder.js *}
            </div>
            <div class="modal-footer">
              <div class="alert alert-info" style="font-family:monospace;font-size:12px" id="pfTagPreview"></div>
              <button type="button" class="btn btn-primary" id="pfInsertTag">Insert Tag</button>
              <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    {* ── Tab 2: Mail ── *}
    <div class="tab-pane" id="tab-mail">
      <form method="post" action="{$base_url|escape}">
        <input type="hidden" name="action" value="save_mail">
        <input type="hidden" name="id_form" value="{$form.id_form|intval}">
        <input type="hidden" name="mail_routes_json" id="mail_routes_json" value="">

        <ul class="nav nav-pills" style="margin-bottom:15px">
          <li class="active"><a href="#mail-admin" data-toggle="pill">Admin Notifications</a></li>
          <li>              <a href="#mail-confirm" data-toggle="pill">Submitter Confirmation</a></li>
        </ul>

        <div class="tab-content">
          {foreach $email_routes as $route}
          <div class="tab-pane {if $route.type == 'admin'}active{/if}" id="mail-{$route.type}">
            {if $route.type == 'confirmation'}
            <div class="form-group">
              <label>
                <input type="checkbox" class="pf-mail-enabled" data-type="confirmation"
                  {if $route.enabled}checked{/if}>
                Enable submitter confirmation email
              </label>
            </div>
            {/if}
            <div class="form-group">
              <label>Notify address(es) <small>one per line — supports <code>[field-name]</code></small></label>
              <textarea class="form-control pf-notify-addresses" data-type="{$route.type}" rows="3"
                        style="font-family:monospace">{$route.notify_addresses|@implode:"\n"|escape}</textarea>
            </div>
            {if $route.type == 'confirmation'}
            <div class="form-group">
              <label>Reply-to <small>(leave blank for store default)</small></label>
              <input type="text" class="form-control pf-reply-to" value="{$route.reply_to|default:''|escape}">
            </div>
            {/if}
            <div class="form-group">
              <label>Subject <small><code>[field]</code> <code>[_form_title]</code> <code>[_date]</code></small></label>
              <input type="text" class="form-control pf-subject" data-type="{$route.type}"
                     value="{$route.subject|escape}">
            </div>
            <div class="form-group">
              <label>Body</label>
              <textarea class="form-control pf-body" data-type="{$route.type}" rows="8">{$route.body|escape}</textarea>
            </div>
            {if $route.type == 'admin'}
            <div class="panel panel-default">
              <div class="panel-heading">Conditional Routing Rules</div>
              <div class="panel-body">
                <small class="text-muted">Override notify address based on a field value.</small>
                <table class="table table-condensed" id="pf-routing-table">
                  <thead><tr><th>If field</th><th>equals</th><th>Send to</th><th></th></tr></thead>
                  <tbody id="pf-routing-rows">
                    {foreach $route.routing_rules as $rule}
                    <tr class="pf-routing-row">
                      <td><select class="form-control pf-route-field" style="min-width:120px">
                        {foreach $fields as $f}{if $f.name}
                          <option value="{$f.name|escape}" {if $rule.field == $f.name}selected{/if}>{$f.name|escape}</option>
                        {/if}{/foreach}
                      </select></td>
                      <td><input type="text" class="form-control pf-route-value" value="{$rule.value|escape}" placeholder="value"></td>
                      <td><input type="text" class="form-control pf-route-email" value="{$rule.email|escape}" placeholder="email@store.com"></td>
                      <td><button type="button" class="btn btn-danger btn-xs pf-remove-route"><i class="icon-trash"></i></button></td>
                    </tr>
                    {/foreach}
                  </tbody>
                </table>
                <button type="button" class="btn btn-default btn-sm" id="pf-add-route">+ Add Rule</button>
              </div>
            </div>
            {/if}
          </div>
          {/foreach}
        </div>

        <button type="submit" class="btn btn-primary" id="pf-save-mail"><i class="icon-save"></i> Save Mail Settings</button>
      </form>
    </div>

    {* ── Tab 3: Webhooks ── *}
    <div class="tab-pane" id="tab-webhooks">
      <div style="margin-bottom:15px">
        <button type="button" class="btn btn-default" id="pf-add-webhook">
          <i class="icon-plus"></i> Add Webhook
        </button>
      </div>
      <div id="pf-webhook-list">
        {foreach $webhooks as $wh}
        <div class="panel panel-default pf-webhook-item" data-id="{$wh.id_webhook|intval}">
          <div class="panel-heading" style="cursor:pointer" data-toggle="collapse" data-target="#wh-{$wh.id_webhook|intval}">
            <strong>{$wh.name|escape}</strong>
            <span class="text-muted" style="font-size:12px"> — {$wh.method|escape} {$wh.url|escape|truncate:60}</span>
            <span class="label label-{if $wh.active}success{else}default{/if} pull-right">
              {if $wh.active}Active{else}Inactive{/if}
            </span>
          </div>
          <div id="wh-{$wh.id_webhook|intval}" class="panel-collapse collapse">
            <div class="panel-body">
              {include file="$smarty.const._PS_MODULE_DIR_/prestaform/views/templates/admin/forms/webhook-form.tpl" wh=$wh fields=$fields}
            </div>
          </div>
        </div>
        {/foreach}
      </div>
      <template id="pf-webhook-tpl">
        {include file="$smarty.const._PS_MODULE_DIR_/prestaform/views/templates/admin/forms/webhook-form.tpl" wh=[] fields=$fields}
      </template>
    </div>

    {* ── Tab 4: Conditions ── *}
    <div class="tab-pane" id="tab-conditions">
      <form method="post" action="{$base_url|escape}">
        <input type="hidden" name="action" value="save_conditions">
        <input type="hidden" name="id_form" value="{$form.id_form|intval}">
        <input type="hidden" name="conditions_json" id="conditions_json" value="">

        <div id="pf-condition-groups">
          {foreach $conditions as $cg}
          <div class="panel panel-default pf-cg">
            <div class="panel-heading">
              Rule: <select class="pf-cg-action form-control" style="display:inline;width:auto">
                <option value="show" {if $cg.action=='show'}selected{/if}>Show</option>
                <option value="hide" {if $cg.action=='hide'}selected{/if}>Hide</option>
              </select>
              field: <select class="pf-cg-target form-control" style="display:inline;width:auto">
                {foreach $fields as $f}{if $f.name}
                  <option value="{$f.name|escape}" {if $cg.target_field==$f.name}selected{/if}>{$f.name|escape}</option>
                {/if}{/foreach}
              </select>
              when
              <select class="pf-cg-logic form-control" style="display:inline;width:auto">
                <option value="AND" {if $cg.logic=='AND'}selected{/if}>ALL</option>
                <option value="OR"  {if $cg.logic=='OR'}selected{/if}>ANY</option>
              </select>
              of:
              <button type="button" class="btn btn-danger btn-xs pull-right pf-remove-cg"><i class="icon-trash"></i></button>
            </div>
            <div class="panel-body">
              <div class="pf-cg-rules">
                {foreach $cg.rules as $rule}
                <div class="pf-rule row" style="margin-bottom:8px">
                  <div class="col-sm-4">
                    <select class="form-control pf-rule-field">
                      {foreach $fields as $f}{if $f.name}
                        <option value="{$f.name|escape}" {if $rule.field==$f.name}selected{/if}>{$f.name|escape}</option>
                      {/if}{/foreach}
                    </select>
                  </div>
                  <div class="col-sm-3">
                    <select class="form-control pf-rule-operator">
                      {foreach ['equals','not_equals','contains','is_empty','is_not_empty'] as $op}
                        <option value="{$op}" {if $rule.operator==$op}selected{/if}>{$op|replace:'_':' '}</option>
                      {/foreach}
                    </select>
                  </div>
                  <div class="col-sm-4">
                    <input type="text" class="form-control pf-rule-value" value="{$rule.value|escape}">
                  </div>
                  <div class="col-sm-1">
                    <button type="button" class="btn btn-danger btn-xs pf-remove-rule"><i class="icon-trash"></i></button>
                  </div>
                </div>
                {/foreach}
              </div>
              <button type="button" class="btn btn-default btn-xs pf-add-rule">+ Add condition</button>
            </div>
          </div>
          {/foreach}
        </div>

        <button type="button" class="btn btn-default" id="pf-add-cg">+ Add Rule</button>
        <button type="submit" class="btn btn-primary pull-right" id="pf-save-conditions">
          <i class="icon-save"></i> Save Conditions
        </button>
      </form>
    </div>

    {* ── Tab 5: Settings ── *}
    <div class="tab-pane" id="tab-settings">
      <form method="post" action="{$base_url|escape}">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id_form" value="{$form.id_form|intval}">
        {* Re-post required fields *}
        <input type="hidden" name="name" value="{$form.name|escape}">
        <input type="hidden" name="template" id="settings-template-mirror" value="{$form.template|escape}">

        <div class="form-group">
          <label>Form Slug</label>
          <input type="text" name="slug" class="form-control" value="{$form.slug|escape}" style="max-width:300px">
          <p class="help-block">Used in <code>{ldelim}prestaform name="{$form.slug|escape}"{rdelim}</code></p>
        </div>

        <div class="form-group">
          <label>Status</label>
          <select name="status" class="form-control" style="max-width:150px">
            <option value="draft"  {if $form.status=='draft'}selected{/if}>Draft</option>
            <option value="active" {if $form.status=='active'}selected{/if}>Active</option>
          </select>
        </div>

        <div class="form-group">
          <label>Success Message</label>
          <textarea name="success_message" class="form-control" rows="3">{$form.success_message|escape}</textarea>
        </div>

        <div class="form-group">
          <label>Custom CSS <small>scoped automatically to <code>#prestaform-{$form.id_form|intval}</code></small></label>
          <textarea name="custom_css" class="form-control" rows="6"
                    style="font-family:monospace">{$form.custom_css|escape}</textarea>
        </div>

        <div class="row">
          <div class="col-md-4">
            <div class="form-group">
              <label>CAPTCHA Provider</label>
              <select name="captcha_provider" class="form-control">
                {foreach $captcha_providers as $val => $label}
                  <option value="{$val}" {if $form.captcha_provider==$val}selected{/if}>{$label}</option>
                {/foreach}
              </select>
            </div>
          </div>
          <div class="col-md-4">
            <div class="form-group">
              <label>Submission Retention</label>
              <select name="retention_days" class="form-control" id="retention-select">
                <option value=""  {if $form.retention_days===null}selected{/if}>Forever</option>
                <option value="30"  {if $form.retention_days==30}selected{/if}>30 days</option>
                <option value="90"  {if $form.retention_days==90}selected{/if}>90 days</option>
                <option value="180" {if $form.retention_days==180}selected{/if}>180 days</option>
                <option value="365" {if $form.retention_days==365}selected{/if}>1 year</option>
                <option value="custom" id="retention-custom-opt">Custom…</option>
              </select>
              <input type="number" name="retention_days_custom" id="retention-custom-input"
                     class="form-control" min="1" placeholder="Days"
                     style="margin-top:8px;display:none"
                     value="{$form.retention_days|intval}">
            </div>
          </div>
        </div>

        {if $form.id_form > 0}
        <div class="form-group">
          <label>Embed Shortcode</label>
          <div class="input-group" style="max-width:500px">
            <input type="text" class="form-control" id="pf-embed-code" readonly
                   value="{ldelim}prestaform id=&quot;{$form.id_form|intval}&quot;{rdelim} or {ldelim}prestaform name=&quot;{$form.slug|escape}&quot;{rdelim}">
            <span class="input-group-btn">
              <button type="button" class="btn btn-default" onclick="pfCopyEmbed()">Copy</button>
            </span>
          </div>
        </div>
        {/if}

        <button type="submit" class="btn btn-primary"><i class="icon-save"></i> Save Settings</button>
      </form>
    </div>

  </div>{* end tab-content *}
</div>
{/block}
