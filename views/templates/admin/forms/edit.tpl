<div class="panel">
  <div class="panel-heading">
    <i class="icon-edit"></i>
    {if $form.id_form > 0}Edit Form: {$form.name|escape}{else}New Form{/if}
    <a href="{$base_url|escape}" class="btn btn-default btn-sm pull-right">
      <i class="icon-arrow-left"></i> Back to Forms
    </a>
  </div>

  {* Tab Navigation — dual data-toggle/data-bs-toggle for Bootstrap 4 & 5.
     data-pf-tabs activates our vanilla JS fallback in case Bootstrap JS is absent. *}
  <ul class="nav nav-tabs" id="pfFormTabs" data-pf-tabs="1">
    <li class="active"><a href="#tab-builder"   data-toggle="tab" data-bs-toggle="tab"><i class="icon-pencil"></i> Form Builder</a></li>
    <li>              <a href="#tab-mail"        data-toggle="tab" data-bs-toggle="tab"><i class="icon-envelope"></i> Mail</a></li>
    <li>              <a href="#tab-webhooks"    data-toggle="tab" data-bs-toggle="tab"><i class="icon-bolt"></i> Webhooks</a></li>
    <li>              <a href="#tab-conditions"  data-toggle="tab" data-bs-toggle="tab"><i class="icon-random"></i> Conditions</a></li>
    <li>              <a href="#tab-settings"    data-toggle="tab" data-bs-toggle="tab"><i class="icon-cog"></i> Settings</a></li>
  </ul>

  <div class="tab-content" style="padding:20px">

    {* ── Tab 1: Form Builder ── *}
    <div class="tab-pane active" id="tab-builder">

      <div class="alert alert-info" style="margin-bottom:20px">
        <strong><i class="icon-info-sign"></i> How the Form Builder works</strong><br>
        Give your form a name, then build its layout in the <strong>Form Template</strong> box using a mix of plain HTML and <strong>shortcode tags</strong> like <code>[text* your-name placeholder "Your Name"]</code>.
        Click any tag button on the left to open a configuration dialog and insert a tag automatically.
        Use <code>*</code> after the tag type (e.g. <code>[text*]</code>) to make a field required.
        Save your form here, then visit the other tabs to configure emails, webhooks, and field conditions.
      </div>

      <form method="post" action="{$base_url|escape}">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id_form" value="{$form.id_form|intval}">
        {* Preserve Settings-tab fields so saving here doesn't silently reset
           status to draft or wipe slug / CSS / success message / CAPTCHA. *}
        <input type="hidden" name="slug"             value="{$form.slug|default:''|escape}">
        <input type="hidden" name="status"           value="{$form.status|default:'draft'|escape}">
        <input type="hidden" name="success_message"  value="{$form.success_message|default:''|escape}">
        <input type="hidden" name="custom_css"       value="{$form.custom_css|default:''|escape}">
        <input type="hidden" name="captcha_provider" value="{$form.captcha_provider|default:'none'|escape}">
        <input type="hidden" name="retention_days"   value="{$form.retention_days|default:''}">

        <div class="form-group">
          <label>Form Name <span class="text-danger">*</span></label>
          <input type="text" name="name" class="form-control" value="{$form.name|escape}" required style="max-width:400px">
          <p class="help-block">An internal label used to identify this form in the admin panel. Not shown to visitors.</p>
        </div>

        <div class="row">
          <div class="col-lg-3" id="pf-tag-panel">
            <label>Insert Tag</label>
            <p class="help-block" style="font-size:12px">Click a tag type to configure and insert it at the cursor position in the template.</p>
            <div class="list-group" id="pf-tag-buttons" style="margin-bottom:0">
              {foreach ['text','email','tel','number','date','textarea','select','checkbox','radio','file','hidden','recaptcha','submit'] as $tagType}
              <button type="button" class="list-group-item pf-tag-btn" data-type="{$tagType}" style="padding:5px 10px">
                <code style="font-size:12px">[{$tagType}]</code>
              </button>
              {/foreach}
            </div>

            {* Inline tag configurator — shown/hidden by JS, no Bootstrap modal needed *}
            <div id="pf-tag-config" style="display:none;margin-top:8px;padding:12px;border:1px solid #d1d5da;border-radius:4px;background:#fff;box-shadow:0 2px 6px rgba(0,0,0,.12)">
              <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
                <strong id="pf-tag-config-title" style="font-size:13px"></strong>
                <button type="button" id="pf-tag-config-close" style="background:none;border:none;font-size:18px;cursor:pointer;padding:0 4px;line-height:1">&times;</button>
              </div>
              <div id="pf-tag-config-body"></div>
              <div id="pf-tag-preview" style="font-family:monospace;font-size:11px;background:#f0f8ff;border:1px solid #bee5fd;border-radius:3px;padding:6px;margin:8px 0;word-break:break-all"></div>
              <button type="button" class="btn btn-primary btn-sm" id="pf-tag-insert" style="width:100%"><i class="icon-check"></i> Insert Tag</button>
            </div>
          </div>

          <div class="col-lg-9">
            <label>Form Template</label>
            <p class="help-block" style="font-size:12px">
              Write your form layout using standard HTML and shortcode tags.
              Wrap tags in <code>&lt;p&gt;</code> or <code>&lt;div&gt;</code> elements to control spacing and layout.
              Add <code>*</code> after the type to mark a field as required, e.g. <code>[text* full-name]</code>.
              The <strong>field name</strong> (e.g. <code>full-name</code>) becomes the column heading in submitted data — use lowercase letters, numbers, and hyphens only.
            </p>
            <textarea name="template" id="pf-template" class="form-control" rows="20"
                      style="font-family:monospace;font-size:13px">{$form.template|escape}</textarea>
            <p class="help-block" style="margin-top:6px">
              <strong>Quick reference:</strong>
              <code>[text* name placeholder "Label"]</code> &nbsp;
              <code>[email* email]</code> &nbsp;
              <code>[select country "UK" "US" "Other"]</code> &nbsp;
              <code>[submit "Send"]</code>
            </p>
          </div>
        </div>

        <div style="margin-top:15px">
          <button type="submit" class="btn btn-primary"><i class="icon-save"></i> Save Form</button>
          <span class="text-muted" style="margin-left:10px;font-size:12px">Changes to the template take effect immediately after saving.</span>
        </div>
      </form>

      {* Embed shortcode — shown here on the builder tab so it's easy to find *}
      {if $form.id_form > 0}
      <div class="well well-sm" style="margin-top:20px">
        <strong><i class="icon-code"></i> Embed this form</strong> &mdash;
        copy either shortcode and paste it into any CMS page, category description, or custom HTML block:
        <div class="input-group" style="max-width:620px;margin-top:8px">
          <input type="text" class="form-control" id="pf-embed-code" readonly
                 value="{ldelim}prestaform id=&quot;{$form.id_form|intval}&quot;{rdelim}  or  {ldelim}prestaform name=&quot;{$form.slug|escape}&quot;{rdelim}">
          <span class="input-group-btn">
            <button type="button" class="btn btn-default" onclick="pfCopyEmbed()"><i class="icon-copy"></i> Copy</button>
          </span>
        </div>
      </div>
      {/if}

    </div>

    {* ── Tab 2: Mail ── *}
    <div class="tab-pane" id="tab-mail">

      {* Available mail-tags reference bar *}
      <div style="background:#f5f5f5;border:1px solid #e0e0e0;border-radius:4px;padding:8px 12px;margin-bottom:16px;font-size:12px">
        <strong>Available mail-tags:</strong>
        {foreach $fields as $f}{if $f.name}<code style="margin-right:4px">[{$f.name|escape}]</code>{/if}{/foreach}
        <code style="margin-right:4px">[_form_title]</code>
        <code style="margin-right:4px">[_date]</code>
        <code style="margin-right:4px">[_ip]</code>
        <code style="margin-right:4px">[_all_fields]</code>
        <code style="margin-right:4px">[_shop_name]</code>
        <code style="margin-right:4px">[_shop_email]</code>
      </div>

      <form method="post" action="{$base_url|escape}">
        <input type="hidden" name="action" value="save_mail">
        <input type="hidden" name="id_form" value="{$form.id_form|intval}">
        {* Pre-seeded with current DB state — protects against accidental wipe if JS fails *}
        <input type="hidden" name="mail_routes_json" id="mail_routes_json"
               value="{$mail_routes_init_json|default:'[]'|escape:'html'}">

        {foreach $email_routes as $route}
        {assign var="route_type" value=$route.type|default:''}
        {assign var="is_mail2"   value=($route_type == 'confirmation')}

        <div class="panel panel-default pf-mail-panel" data-mail-type="{$route_type|escape}"
             style="{if $is_mail2}margin-top:10px{/if}">
          <div class="panel-heading" style="display:flex;justify-content:space-between;align-items:center">
            <strong>
              {if $is_mail2}<i class="icon-envelope-alt"></i> Mail (2){else}<i class="icon-envelope"></i> Mail{/if}
            </strong>
            {if $is_mail2}
            <label style="margin:0;font-weight:normal;font-size:13px">
              <input type="checkbox" class="pf-mail-enabled" data-mail="confirmation"
                {if $route.enabled|default:0}checked{/if}>
              Use Mail (2)
            </label>
            {/if}
          </div>

          <div class="panel-body {if $is_mail2}pf-mail2-body{/if}"
               {if $is_mail2 && !($route.enabled|default:0)}style="display:none"{/if}>

            {if $is_mail2}
            <p class="text-muted" style="margin-bottom:15px;font-size:13px">
              Mail (2) is an additional mail template, typically used as an autoresponder sent to the person who submitted the form.
              Set <strong>To</strong> to the field name containing the visitor's email address, e.g. <code>[your-email]</code>.
            </p>
            {/if}

            <div class="form-group">
              <label>To</label>
              <input type="text" class="form-control pf-mail-to" data-mail="{$route_type|escape}"
                     value="{$route.notify_addresses|default:''|escape}"
                     placeholder="{if $is_mail2}[your-email]{else}admin@yourstore.com, sales@yourstore.com{/if}">
              {if $is_mail2}
              <p class="help-block">The email address to send the confirmation to. Use a mail-tag like <code>[your-email]</code> to send to the address the visitor entered in the form. You can also enter a fixed address.</p>
              {else}
              <p class="help-block">Comma-separated list of addresses that receive a notification on each submission. Supports mail-tags, e.g. <code>[your-email]</code>. Leave blank to use the store's default contact address.</p>
              {/if}
            </div>

            <div class="form-group">
              <label>From</label>
              <input type="text" class="form-control pf-mail-from" data-mail="{$route_type|escape}"
                     value="{$route.from_address|default:''|escape}"
                     placeholder="[_shop_name] &lt;[_shop_email]&gt;">
              <p class="help-block">The sender name and address. Format: <code>Display Name &lt;email@domain.com&gt;</code> or just <code>email@domain.com</code>. Supports mail-tags.</p>
            </div>

            <div class="form-group">
              <label>Subject</label>
              <input type="text" class="form-control pf-mail-subject" data-mail="{$route_type|escape}"
                     value="{$route.subject|default:''|escape}">
              <p class="help-block">The email subject line. Supports mail-tags, e.g. <code>[_form_title]</code>, <code>[your-name]</code>.</p>
            </div>

            <div class="form-group">
              <label>Additional headers</label>
              <textarea class="form-control pf-mail-headers" data-mail="{$route_type|escape}"
                        rows="3" style="font-family:monospace">{$route.additional_headers|default:''|escape}</textarea>
              <p class="help-block">
                One header per line. Supported: <code>Reply-To: email</code> and <code>Bcc: email</code>. Supports mail-tags.<br>
                Example: <code>Reply-To: [your-email]</code>
              </p>
            </div>

            <div class="form-group">
              <label>Message body</label>
              <textarea class="form-control pf-mail-body" data-mail="{$route_type|escape}"
                        rows="12" style="font-family:monospace">{$route.body|default:''|escape}</textarea>
              <p class="help-block">
                The email body. HTML is supported. Use mail-tags to include submitted values.<br>
                <code>[_all_fields]</code> — inserts an HTML table of all fields and values &nbsp;
                <code>[field-name]</code> — inserts a single field value
              </p>
            </div>

            {if !$is_mail2}
            {* Conditional routing — only for Mail 1 (admin) *}
            <div class="panel panel-default" style="margin-top:10px">
              <div class="panel-heading"><i class="icon-random"></i> Conditional Routing <small class="text-muted">(optional)</small></div>
              <div class="panel-body">
                <p class="text-muted" style="margin-bottom:10px;font-size:13px">
                  Override the <strong>To</strong> address based on a submitted field value.
                  E.g. if the visitor picks "Sales" in a department field, send to <em>sales@yourstore.com</em>.
                  First matching rule wins.
                </p>
                <table class="table table-condensed" id="pf-routing-table">
                  <thead>
                    <tr>
                      <th>If field&hellip;</th>
                      <th>&hellip;equals</th>
                      <th>Send To</th>
                      <th></th>
                    </tr>
                  </thead>
                  <tbody id="pf-routing-rows">
                    {if !empty($route.routing_rules)}
                    {foreach $route.routing_rules as $rule}
                    <tr class="pf-routing-row">
                      <td><select class="form-control pf-route-field" style="min-width:120px">
                        {foreach $fields as $f}{if $f.name}
                          <option value="{$f.name|escape}" {if ($rule.field|default:'') == $f.name}selected{/if}>{$f.name|escape}</option>
                        {/if}{/foreach}
                      </select></td>
                      <td><input type="text" class="form-control pf-route-value" value="{$rule.value|default:''|escape}" placeholder="value to match"></td>
                      <td><input type="text" class="form-control pf-route-email" value="{$rule.email|default:''|escape}" placeholder="recipient@example.com"></td>
                      <td><button type="button" class="btn btn-danger btn-xs pf-remove-route"><i class="icon-trash"></i></button></td>
                    </tr>
                    {/foreach}
                    {/if}
                  </tbody>
                </table>
                <button type="button" class="btn btn-default btn-sm" id="pf-add-route"><i class="icon-plus"></i> Add Rule</button>
              </div>
            </div>
            {/if}

          </div>{* end panel-body *}
        </div>{* end panel *}
        {/foreach}

        <button type="submit" class="btn btn-primary" id="pf-save-mail"><i class="icon-save"></i> Save Mail Settings</button>
        <span class="text-muted" style="margin-left:10px;font-size:12px">Mail and Mail (2) are saved together.</span>
      </form>
    </div>

    {* ── Tab 3: Webhooks ── *}
    <div class="tab-pane" id="tab-webhooks">

      <div class="alert alert-info" style="margin-bottom:20px">
        <strong><i class="icon-bolt"></i> Webhooks</strong><br>
        A webhook sends the submitted form data as an HTTP request to an external URL whenever this form is submitted.
        Use webhooks to integrate with services like Zapier, Make (Integromat), Slack, a CRM, or any custom API.
        You can add multiple webhooks — each fires independently.
        Click <strong>Add Webhook</strong> below to create your first one.
      </div>

      <div style="margin-bottom:15px">
        <button type="button" class="btn btn-default" id="pf-add-webhook">
          <i class="icon-plus"></i> Add Webhook
        </button>
      </div>

      {if $form.id_form == 0}
      <div class="alert alert-warning">
        <i class="icon-warning-sign"></i> Please <strong>save the form first</strong> before adding webhooks.
      </div>
      {/if}

      <div id="pf-webhook-list">
        {foreach $webhooks as $wh}
        {assign var="wh_active" value=$wh.active|default:0}
        <div class="panel panel-default pf-webhook-item" data-id="{$wh.id_webhook|intval}">
          <div class="panel-heading" style="cursor:pointer" data-toggle="collapse" data-bs-toggle="collapse" data-target="#wh-{$wh.id_webhook|intval}" data-bs-target="#wh-{$wh.id_webhook|intval}">
            <strong>{$wh.name|default:''|escape}</strong>
            <span class="text-muted" style="font-size:12px"> — {$wh.method|default:''|escape} {$wh.url|default:''|escape|truncate:60}</span>
            <span class="label label-{if $wh_active}success{else}default{/if} pull-right">
              {if $wh_active}Active{else}Inactive{/if}
            </span>
          </div>
          <div id="wh-{$wh.id_webhook|intval}" class="panel-collapse collapse">
            <div class="panel-body">
              {include file="{$pf_tpl_dir}webhook-form.tpl" wh=$wh fields=$fields}
            </div>
          </div>
        </div>
        {/foreach}
      </div>
      <template id="pf-webhook-tpl">
        {include file="{$pf_tpl_dir}webhook-form.tpl" wh=[] fields=$fields}
      </template>
    </div>

    {* ── Tab 4: Conditions ── *}
    <div class="tab-pane" id="tab-conditions">

      <div class="alert alert-info" style="margin-bottom:20px">
        <strong><i class="icon-random"></i> Conditional Logic</strong><br>
        Show or hide specific fields based on what a visitor has already entered.
        For example: show a <em>Company Name</em> field only when the visitor selects "Business" in a customer type field.
        Each rule targets one field and can have multiple conditions joined by <strong>ALL</strong> (every condition must match) or <strong>ANY</strong> (at least one must match).
        <br><strong>Note:</strong> Field names must match the names used in your Form Template exactly.
      </div>

      <form method="post" action="{$base_url|escape}">
        <input type="hidden" name="action" value="save_conditions">
        <input type="hidden" name="id_form" value="{$form.id_form|intval}">
        {* Pre-seeded with current DB state — protects against accidental wipe if JS fails *}
        <input type="hidden" name="conditions_json" id="conditions_json"
               value="{$conditions_init_json|default:'[]'|escape:'html'}">

        {if $form.id_form == 0}
        <div class="alert alert-warning">
          <i class="icon-warning-sign"></i> Please <strong>save the form first</strong> before adding conditions. The field list is built from your saved template.
        </div>
        {/if}

        <div id="pf-condition-groups">
          {foreach $conditions as $cg}
          <div class="panel panel-default pf-cg">
            <div class="panel-heading">
              Rule: <select class="pf-cg-action form-control" style="display:inline;width:auto">
                <option value="show" {if ($cg.action|default:'')=='show'}selected{/if}>Show</option>
                <option value="hide" {if ($cg.action|default:'')=='hide'}selected{/if}>Hide</option>
              </select>
              field: <select class="pf-cg-target form-control" style="display:inline;width:auto">
                {foreach $fields as $f}{if $f.name}
                  <option value="{$f.name|escape}" {if ($cg.target_field|default:'')==$f.name}selected{/if}>{$f.name|escape}</option>
                {/if}{/foreach}
              </select>
              when
              <select class="pf-cg-logic form-control" style="display:inline;width:auto">
                <option value="AND" {if ($cg.logic|default:'')=='AND'}selected{/if}>ALL</option>
                <option value="OR"  {if ($cg.logic|default:'')=='OR'}selected{/if}>ANY</option>
              </select>
              of the following are true:
              <button type="button" class="btn btn-danger btn-xs pull-right pf-remove-cg"><i class="icon-trash"></i> Remove rule</button>
            </div>
            <div class="panel-body">
              <p class="text-muted" style="font-size:12px;margin-bottom:8px">
                Add one or more conditions below. Each condition checks a field value using the chosen operator.
              </p>
              <div class="pf-cg-rules">
                {if !empty($cg.rules)}
                {foreach $cg.rules as $rule}
                <div class="pf-rule row" style="margin-bottom:8px">
                  <div class="col-sm-4">
                    <select class="form-control pf-rule-field">
                      {foreach $fields as $f}{if $f.name}
                        <option value="{$f.name|escape}" {if ($rule.field|default:'')==$f.name}selected{/if}>{$f.name|escape}</option>
                      {/if}{/foreach}
                    </select>
                  </div>
                  <div class="col-sm-3">
                    <select class="form-control pf-rule-operator">
                      {foreach ['equals','not_equals','contains','is_empty','is_not_empty'] as $op}
                        <option value="{$op}" {if ($rule.operator|default:'')==$op}selected{/if}>{$op|replace:'_':' '}</option>
                      {/foreach}
                    </select>
                  </div>
                  <div class="col-sm-4">
                    <input type="text" class="form-control pf-rule-value" value="{$rule.value|default:''|escape}" placeholder="value to match">
                  </div>
                  <div class="col-sm-1">
                    <button type="button" class="btn btn-danger btn-xs pf-remove-rule"><i class="icon-trash"></i></button>
                  </div>
                </div>
                {/foreach}
                {/if}
              </div>
              <button type="button" class="btn btn-default btn-xs pf-add-rule"><i class="icon-plus"></i> Add condition</button>
            </div>
          </div>
          {/foreach}
        </div>

        {if empty($conditions)}
        <p class="text-muted" id="pf-no-conditions-msg">No conditional rules yet. Click <strong>+ Add Rule</strong> to create one.</p>
        {/if}

        <div style="margin-top:15px">
          <button type="button" class="btn btn-default" id="pf-add-cg"><i class="icon-plus"></i> Add Rule</button>
          <button type="submit" class="btn btn-primary pull-right" id="pf-save-conditions">
            <i class="icon-save"></i> Save Conditions
          </button>
        </div>
        <p class="help-block pull-right" style="clear:both;text-align:right;font-size:12px">
          Conditions are applied in real-time on the front-end form as the visitor types.
        </p>
      </form>
    </div>

    {* ── Tab 5: Settings ── *}
    <div class="tab-pane" id="tab-settings">

      <div class="alert alert-info" style="margin-bottom:20px">
        <strong><i class="icon-cog"></i> Form Settings</strong><br>
        Configure the form's URL slug, visibility status, success message, spam protection, and data retention.
        The <strong>Embed Shortcode</strong> at the bottom of this tab is what you paste into any CMS page or widget to display the form on your storefront.
      </div>

      <form method="post" action="{$base_url|escape}">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id_form" value="{$form.id_form|intval}">
        {* Re-post required fields so they are not lost when saving from this tab *}
        <input type="hidden" name="name" value="{$form.name|escape}">
        <input type="hidden" name="template" id="settings-template-mirror" value="{$form.template|escape}">

        <div class="form-group">
          <label>Form Slug</label>
          <input type="text" name="slug" class="form-control" value="{$form.slug|escape}" style="max-width:300px" placeholder="e.g. contact-us">
          <p class="help-block">
            A short, unique identifier used in the embed shortcode and in the form's public URL.
            Use lowercase letters, numbers, and hyphens only — no spaces.
            Example: <code>contact-us</code>, <code>quote-request</code>.<br>
            Used in: <code>{ldelim}prestaform name="{$form.slug|escape}"{rdelim}</code>
          </p>
        </div>

        <div class="form-group">
          <label>Status</label>
          <select name="status" class="form-control" style="max-width:150px">
            <option value="draft"  {if ($form.status|default:'')=='draft'}selected{/if}>Draft</option>
            <option value="active" {if ($form.status|default:'')=='active'}selected{/if}>Active</option>
          </select>
          <p class="help-block">
            <strong>Draft</strong> — the form is hidden from visitors. Use this while you are building or testing.<br>
            <strong>Active</strong> — the form is visible and accepts submissions from the public.
          </p>
        </div>

        <div class="form-group">
          <label>Success Message</label>
          <textarea name="success_message" class="form-control" rows="3">{$form.success_message|escape}</textarea>
          <p class="help-block">The message displayed to the visitor after they successfully submit the form. HTML is supported. Keep it short and reassuring, e.g. <em>"Thank you! We'll be in touch shortly."</em></p>
        </div>

        <div class="form-group">
          <label>Custom CSS</label>
          <textarea name="custom_css" class="form-control" rows="6"
                    style="font-family:monospace">{$form.custom_css|escape}</textarea>
          <p class="help-block">
            Optional CSS rules that apply <strong>only to this form</strong>. Rules are automatically scoped to <code>#prestaform-{$form.id_form|intval}</code> so they will not affect the rest of your theme.
            Example: <code>input { border-radius: 4px; }</code>
          </p>
        </div>

        <div class="row">
          <div class="col-md-5">
            <div class="form-group">
              <label>CAPTCHA / Spam Protection</label>
              <select name="captcha_provider" class="form-control">
                {foreach $captcha_providers as $val => $label}
                  <option value="{$val}" {if ($form.captcha_provider|default:'')==$val}selected{/if}>{$label}</option>
                {/foreach}
              </select>
              <p class="help-block">
                Choose a spam-protection method for this form.<br>
                <strong>None</strong> — no bot protection (not recommended for public forms).<br>
                <strong>reCAPTCHA v2</strong> — shows the "I'm not a robot" tick-box.<br>
                <strong>reCAPTCHA v3</strong> — invisible scoring; no interaction needed.<br>
                <strong>Cloudflare Turnstile</strong> — privacy-friendly alternative to reCAPTCHA.<br>
                <span class="text-warning"><i class="icon-warning-sign"></i>
                  API keys (site key &amp; secret key) for reCAPTCHA and Turnstile are entered in the
                  <strong>PrestaForm &rsaquo; Settings</strong> page (use the left-hand menu or the Settings tab in the module manager),
                  not here. Select the provider above, save this form, then go to Settings to paste in your keys.
                </span>
              </p>
            </div>
          </div>
          <div class="col-md-4">
            <div class="form-group">
              <label>Submission Retention</label>
              <select name="retention_days" class="form-control" id="retention-select">
                <option value=""    {if $form.retention_days===null}selected{/if}>Forever</option>
                <option value="30"  {if $form.retention_days==30}selected{/if}>30 days</option>
                <option value="90"  {if $form.retention_days==90}selected{/if}>90 days</option>
                <option value="180" {if $form.retention_days==180}selected{/if}>180 days</option>
                <option value="365" {if $form.retention_days==365}selected{/if}>1 year</option>
                <option value="custom" id="retention-custom-opt">Custom&hellip;</option>
              </select>
              <input type="number" name="retention_days_custom" id="retention-custom-input"
                     class="form-control" min="1" placeholder="Number of days"
                     style="margin-top:8px;display:none"
                     value="{$form.retention_days|intval}">
              <p class="help-block">
                How long to keep submission records in the database.
                Older submissions are deleted automatically by the daily cron job.
                Choose <strong>Forever</strong> if you need a permanent archive, or set a limit to comply with data-protection policies (e.g. GDPR).
              </p>
            </div>
          </div>
        </div>

        {if $form.id_form > 0}
        <div class="form-group">
          <label>Embed Shortcode</label>
          <div class="input-group" style="max-width:560px">
            <input type="text" class="form-control" id="pf-embed-code" readonly
                   value="{ldelim}prestaform id=&quot;{$form.id_form|intval}&quot;{rdelim} or {ldelim}prestaform name=&quot;{$form.slug|escape}&quot;{rdelim}">
            <span class="input-group-btn">
              <button type="button" class="btn btn-default" onclick="pfCopyEmbed()"><i class="icon-copy"></i> Copy</button>
            </span>
          </div>
          <p class="help-block">Paste either shortcode into any PrestaShop CMS page, category description, or custom HTML block to display this form on the front-end.</p>
        </div>
        {/if}

        <button type="submit" class="btn btn-primary"><i class="icon-save"></i> Save Settings</button>
      </form>
    </div>

  </div>{* end tab-content *}
</div>
