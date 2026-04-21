# PrestaForm Module Implementation Plan — Part 3: Admin Controllers & Templates

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build all admin controllers and their Smarty templates — Forms CRUD, the 5-tab form editor (Builder, Mail, Webhooks, Conditions, Settings), Submissions view with CSV export, and the Global Settings page.

**Prerequisites:** Parts 1 & 2 complete.

**Architecture:** Each admin controller extends `ModuleAdminController`. Templates live in `views/templates/admin/`. AJAX actions for tag generator, webhook test, and conditions save use `displayAjax*` methods.

**Tech Stack:** PHP 8.1+, PS9 ModuleAdminController, Smarty 4, vanilla JS for admin interactions.

---

### Task 13: Admin Forms List Controller

**Files:**
- Create: `controllers/admin/AdminPrestaFormFormsController.php`
- Create: `views/templates/admin/forms/list.tpl`
- Create: `views/templates/admin/forms/edit.tpl`

- [ ] **Step 1: Create controllers/admin/AdminPrestaFormFormsController.php**

```php
<?php
declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

class AdminPrestaFormFormsController extends ModuleAdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->bootstrap  = true;
        $this->meta_title = 'PrestaForm — Forms';
    }

    public function initContent(): void
    {
        $this->addJS(_MODULE_DIR_ . 'prestaform/views/js/admin/form-builder.js');
        $this->addCSS(_MODULE_DIR_ . 'prestaform/views/css/admin.css');
        parent::initContent();
    }

    public function renderList(): string
    {
        $repo  = new \PrestaForm\Repository\FormRepository();
        $forms = $repo->findAll();
        $this->context->smarty->assign(['forms' => $forms, 'base_url' => $this->context->link->getAdminLink('AdminPrestaFormForms')]);
        return $this->context->smarty->fetch(_PS_MODULE_DIR_ . 'prestaform/views/templates/admin/forms/list.tpl');
    }

    public function renderForm(): string
    {
        $repo   = new \PrestaForm\Repository\FormRepository();
        $wRepo  = new \PrestaForm\Repository\WebhookRepository();
        $cRepo  = new \PrestaForm\Repository\ConditionRepository();
        $eRepo  = new \PrestaForm\Repository\EmailRouteRepository();
        $id     = (int) Tools::getValue('id_form');
        $form   = $id ? $repo->findById($id) : $this->emptyForm();
        $parser = new \PrestaForm\Service\ShortcodeParser();
        $fields = $form ? $parser->parse((string) $form['template']) : [];

        $this->context->smarty->assign([
            'form'        => $form ?? $this->emptyForm(),
            'fields'      => $fields,
            'webhooks'    => $id ? $wRepo->findByForm($id) : [],
            'conditions'  => $id ? $cRepo->findByForm($id) : [],
            'email_routes'=> $id ? $eRepo->findByForm($id) : [
                ['type' => 'admin',        'enabled' => 1, 'notify_addresses' => [], 'reply_to' => null, 'subject' => 'New submission: [_form_title]', 'body' => '', 'routing_rules' => []],
                ['type' => 'confirmation', 'enabled' => 0, 'notify_addresses' => [], 'reply_to' => null, 'subject' => 'We received your message', 'body' => '', 'routing_rules' => []],
            ],
            'base_url'    => $this->context->link->getAdminLink('AdminPrestaFormForms'),
            'captcha_providers' => ['none' => 'None', 'recaptcha_v2' => 'reCAPTCHA v2', 'recaptcha_v3' => 'reCAPTCHA v3', 'turnstile' => 'Cloudflare Turnstile'],
        ]);

        return $this->context->smarty->fetch(_PS_MODULE_DIR_ . 'prestaform/views/templates/admin/forms/edit.tpl');
    }

    public function postProcess(): void
    {
        $action = Tools::getValue('action');

        if ($action === 'save') {
            $this->processSave();
        } elseif ($action === 'delete') {
            $this->processDelete();
        } elseif ($action === 'save_webhooks') {
            $this->processSaveWebhooks();
        } elseif ($action === 'save_conditions') {
            $this->processSaveConditions();
        } elseif ($action === 'save_mail') {
            $this->processSaveMail();
        } elseif ($action === 'test_webhook') {
            $this->processTestWebhook();
        } elseif ($action === 'delete_webhook') {
            $this->processDeleteWebhook();
        }

        parent::postProcess();
    }

    private function processSave(): void
    {
        $repo = new \PrestaForm\Repository\FormRepository();
        $slug = Tools::getValue('slug');
        $id   = (int) Tools::getValue('id_form');

        if ($repo->slugExists($slug, $id)) {
            $this->errors[] = 'Slug is already in use. Please choose another.';
            return;
        }

        $retentionRaw = Tools::getValue('retention_days');
        $repo->save([
            'id_form'          => $id ?: null,
            'name'             => Tools::getValue('name'),
            'slug'             => $slug,
            'template'         => Tools::getValue('template'),
            'custom_css'       => Tools::getValue('custom_css'),
            'success_message'  => Tools::getValue('success_message'),
            'status'           => Tools::getValue('status'),
            'captcha_provider' => Tools::getValue('captcha_provider'),
            'retention_days'   => $retentionRaw !== '' ? (int) $retentionRaw : null,
        ]);

        $this->confirmations[] = 'Form saved.';
    }

    private function processDelete(): void
    {
        $id   = (int) Tools::getValue('id_form');
        $repo = new \PrestaForm\Repository\FormRepository();
        $repo->delete($id);
        $this->confirmations[] = 'Form deleted.';
    }

    private function processSaveWebhooks(): void
    {
        $id       = (int) Tools::getValue('id_form');
        $repo     = new \PrestaForm\Repository\WebhookRepository();
        $webhooks = json_decode(Tools::getValue('webhooks_json', '[]'), true) ?? [];

        foreach ($webhooks as $w) {
            $w['id_form'] = $id;
            $repo->save($w);
        }
        $this->confirmations[] = 'Webhooks saved.';
    }

    private function processSaveConditions(): void
    {
        $id      = (int) Tools::getValue('id_form');
        $repo    = new \PrestaForm\Repository\ConditionRepository();
        $groups  = json_decode(Tools::getValue('conditions_json', '[]'), true) ?? [];
        $repo->saveForForm($id, $groups);
        $this->confirmations[] = 'Conditions saved.';
    }

    private function processSaveMail(): void
    {
        $id     = (int) Tools::getValue('id_form');
        $repo   = new \PrestaForm\Repository\EmailRouteRepository();
        $routes = json_decode(Tools::getValue('mail_routes_json', '[]'), true) ?? [];
        $repo->saveForForm($id, $routes);
        $this->confirmations[] = 'Mail settings saved.';
    }

    private function processTestWebhook(): void
    {
        $webhookId  = (int) Tools::getValue('id_webhook');
        $wRepo      = new \PrestaForm\Repository\WebhookRepository();
        $webhook    = $wRepo->findById($webhookId);

        if (!$webhook) {
            $this->outputJson(['success' => false, 'message' => 'Webhook not found.']);
            return;
        }

        $testPayload = ['_test' => true, 'timestamp' => date('c')];
        $dispatcher  = new \PrestaForm\Service\WebhookDispatcher($wRepo);
        // Build and fire a test request directly
        $ch = curl_init($webhook['url']);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => (int) $webhook['timeout_seconds'],
            CURLOPT_CUSTOMREQUEST  => $webhook['method'],
            CURLOPT_POSTFIELDS     => json_encode($testPayload),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        ]);
        $body   = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->outputJson(['success' => $status >= 200 && $status < 300, 'status' => $status, 'body' => mb_substr((string) $body, 0, 500)]);
    }

    private function processDeleteWebhook(): void
    {
        $id   = (int) Tools::getValue('id_webhook');
        $repo = new \PrestaForm\Repository\WebhookRepository();
        $repo->delete($id);
        $this->outputJson(['success' => true]);
    }

    private function outputJson(array $data): void
    {
        ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    private function emptyForm(): array
    {
        return [
            'id_form'          => 0,
            'name'             => '',
            'slug'             => '',
            'template'         => '',
            'custom_css'       => '',
            'success_message'  => 'Thank you! Your message has been sent.',
            'status'           => 'draft',
            'captcha_provider' => 'none',
            'retention_days'   => null,
        ];
    }
}
```

- [ ] **Step 2: Create views/templates/admin/forms/list.tpl**

```smarty
{extends file="helpers/view/view.tpl"}
{block name="override_tpl"}
<div class="panel">
  <div class="panel-heading">
    <i class="icon-list"></i> PrestaForm — All Forms
    <a href="{$base_url|escape}&action=edit" class="btn btn-default btn-sm pull-right">
      <i class="icon-plus"></i> New Form
    </a>
  </div>
  <div class="panel-body">
    {if $forms|@count == 0}
      <p class="text-muted">No forms yet. <a href="{$base_url|escape}&action=edit">Create your first form.</a></p>
    {else}
    <table class="table tableDnD">
      <thead>
        <tr>
          <th>ID</th>
          <th>Name</th>
          <th>Slug</th>
          <th>Submissions</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        {foreach $forms as $f}
        <tr>
          <td>{$f.id_form|intval}</td>
          <td><strong>{$f.name|escape}</strong></td>
          <td><code>{$f.slug|escape}</code></td>
          <td>{$f.submission_count|intval}</td>
          <td>
            {if $f.status == 'active'}
              <span class="label label-success">Active</span>
            {else}
              <span class="label label-default">Draft</span>
            {/if}
          </td>
          <td>
            <a href="{$base_url|escape}&action=edit&id_form={$f.id_form|intval}" class="btn btn-default btn-xs">
              <i class="icon-pencil"></i> Edit
            </a>
            <a href="{$base_url|escape}&action=delete&id_form={$f.id_form|intval}"
               class="btn btn-danger btn-xs"
               onclick="return confirm('Delete this form and all its submissions?')">
              <i class="icon-trash"></i> Delete
            </a>
          </td>
        </tr>
        {/foreach}
      </tbody>
    </table>
    {/if}
  </div>
</div>
{/block}
```

- [ ] **Step 3: Create views/templates/admin/forms/edit.tpl**

```smarty
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
```

- [ ] **Step 4: Create views/templates/admin/forms/webhook-form.tpl**

```smarty
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
```

- [ ] **Step 5: Commit**

```bash
git add controllers/admin/AdminPrestaFormFormsController.php views/templates/admin/forms/
git commit -m "feat: add AdminPrestaFormFormsController and form editor templates (5 tabs)"
```

---

### Task 14: Submissions Controller

**Files:**
- Create: `controllers/admin/AdminPrestaFormSubmissionsController.php`
- Create: `views/templates/admin/submissions/list.tpl`
- Create: `views/templates/admin/submissions/view.tpl`

- [ ] **Step 1: Create controllers/admin/AdminPrestaFormSubmissionsController.php**

```php
<?php
declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

class AdminPrestaFormSubmissionsController extends ModuleAdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->bootstrap  = true;
        $this->meta_title = 'PrestaForm — Submissions';
    }

    public function renderList(): string
    {
        $subRepo  = new \PrestaForm\Repository\SubmissionRepository();
        $formRepo = new \PrestaForm\Repository\FormRepository();

        $filters = [
            'id_form'   => (int) Tools::getValue('id_form') ?: null,
            'date_from' => Tools::getValue('date_from') ?: null,
            'date_to'   => Tools::getValue('date_to')   ?: null,
        ];

        $page       = max(1, (int) Tools::getValue('page', 1));
        $perPage    = 50;
        $total      = $subRepo->countAll(array_filter($filters));
        $submissions = $subRepo->findAll(array_filter($filters), $perPage, ($page - 1) * $perPage);

        $this->context->smarty->assign([
            'submissions' => $submissions,
            'forms'       => $formRepo->findAll(),
            'filters'     => $filters,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'pages'       => (int) ceil($total / $perPage),
            'base_url'    => $this->context->link->getAdminLink('AdminPrestaFormSubmissions'),
        ]);

        return $this->context->smarty->fetch(
            _PS_MODULE_DIR_ . 'prestaform/views/templates/admin/submissions/list.tpl'
        );
    }

    public function postProcess(): void
    {
        $action = Tools::getValue('action');

        if ($action === 'view') {
            $this->renderView();
            return;
        }

        if ($action === 'delete') {
            $id   = (int) Tools::getValue('id_submission');
            $repo = new \PrestaForm\Repository\SubmissionRepository();
            $repo->delete($id);
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminPrestaFormSubmissions'));
        }

        if ($action === 'export_csv') {
            $this->exportCsv();
        }

        parent::postProcess();
    }

    private function renderView(): void
    {
        $id   = (int) Tools::getValue('id_submission');
        $repo = new \PrestaForm\Repository\SubmissionRepository();
        $sub  = $repo->findById($id);

        if (!$sub) {
            $this->errors[] = 'Submission not found.';
            return;
        }

        $this->context->smarty->assign([
            'submission' => $sub,
            'base_url'   => $this->context->link->getAdminLink('AdminPrestaFormSubmissions'),
        ]);

        echo $this->context->smarty->fetch(
            _PS_MODULE_DIR_ . 'prestaform/views/templates/admin/submissions/view.tpl'
        );
        exit;
    }

    private function exportCsv(): void
    {
        $formId = (int) Tools::getValue('id_form');
        $repo   = new \PrestaForm\Repository\SubmissionRepository();
        $rows   = $formId
            ? $repo->findAllForExport($formId)
            : $repo->findAll([], 10000, 0);

        // Collect all unique field keys across submissions
        $keys = ['id_submission', 'date_add', 'ip_address'];
        foreach ($rows as $row) {
            foreach (array_keys($row['data']) as $k) {
                if (!in_array($k, $keys, true)) {
                    $keys[] = $k;
                }
            }
        }

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="prestaform-submissions-' . date('Y-m-d') . '.csv"');

        $out = fopen('php://output', 'w');
        fputs($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
        fputcsv($out, $keys);

        foreach ($rows as $row) {
            $line = [];
            foreach ($keys as $k) {
                if (in_array($k, ['id_submission', 'date_add', 'ip_address'], true)) {
                    $line[] = $row[$k] ?? '';
                } else {
                    $v = $row['data'][$k] ?? '';
                    $line[] = is_array($v) ? implode(', ', $v) : $v;
                }
            }
            fputcsv($out, $line);
        }

        fclose($out);
        exit;
    }
}
```

- [ ] **Step 2: Create views/templates/admin/submissions/list.tpl**

```smarty
{extends file="helpers/view/view.tpl"}
{block name="override_tpl"}
<div class="panel">
  <div class="panel-heading"><i class="icon-inbox"></i> PrestaForm — Submissions</div>
  <div class="panel-body">
    <form method="get" action="{$base_url|escape}" class="form-inline" style="margin-bottom:15px">
      <input type="hidden" name="controller" value="AdminPrestaFormSubmissions">
      <select name="id_form" class="form-control">
        <option value="">All forms</option>
        {foreach $forms as $f}
          <option value="{$f.id_form|intval}" {if $filters.id_form==$f.id_form}selected{/if}>{$f.name|escape}</option>
        {/foreach}
      </select>
      &nbsp;
      <input type="date" name="date_from" class="form-control" value="{$filters.date_from|escape}" placeholder="From">
      <input type="date" name="date_to"   class="form-control" value="{$filters.date_to|escape}"   placeholder="To">
      &nbsp;
      <button type="submit" class="btn btn-default"><i class="icon-search"></i> Filter</button>
      <a href="{$base_url|escape}&action=export_csv&id_form={$filters.id_form|intval}"
         class="btn btn-default pull-right">
        <i class="icon-download"></i> Export CSV
      </a>
    </form>

    <p class="text-muted">{$total|intval} submission(s) found.</p>

    <table class="table tableDnD">
      <thead>
        <tr>
          <th>#</th><th>Form</th><th>Date</th><th>IP</th><th>Preview</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        {foreach $submissions as $s}
        <tr>
          <td>{$s.id_submission|intval}</td>
          <td>{$s.form_name|escape}</td>
          <td>{$s.date_add|escape}</td>
          <td>{$s.ip_address|escape}</td>
          <td>
            {foreach $s.data as $k => $v}
              {if $k@index < 3}
                <span class="label label-default">{$k|escape}</span>: {if is_array($v)}{$v|@implode:', '|escape}{else}{$v|escape|truncate:40}{/if}
                &nbsp;
              {/if}
            {/foreach}
          </td>
          <td>
            <a href="{$base_url|escape}&action=view&id_submission={$s.id_submission|intval}"
               class="btn btn-default btn-xs"><i class="icon-eye"></i> View</a>
            <a href="{$base_url|escape}&action=delete&id_submission={$s.id_submission|intval}"
               class="btn btn-danger btn-xs"
               onclick="return confirm('Delete this submission?')"><i class="icon-trash"></i></a>
          </td>
        </tr>
        {foreachelse}
        <tr><td colspan="6" class="text-muted text-center">No submissions found.</td></tr>
        {/foreach}
      </tbody>
    </table>

    {if $pages > 1}
    <ul class="pagination">
      {section name=p loop=$pages start=1}
        <li class="{if $page == $smarty.section.p.index}active{/if}">
          <a href="{$base_url|escape}&page={$smarty.section.p.index}">{$smarty.section.p.index}</a>
        </li>
      {/section}
    </ul>
    {/if}
  </div>
</div>
{/block}
```

- [ ] **Step 3: Create views/templates/admin/submissions/view.tpl**

```smarty
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
```

- [ ] **Step 4: Commit**

```bash
git add controllers/admin/AdminPrestaFormSubmissionsController.php views/templates/admin/submissions/
git commit -m "feat: add submissions list, detail view, and CSV export"
```

---

### Task 15: Global Settings Controller

**Files:**
- Create: `controllers/admin/AdminPrestaFormSettingsController.php`
- Create: `views/templates/admin/settings/index.tpl`

- [ ] **Step 1: Create controllers/admin/AdminPrestaFormSettingsController.php**

```php
<?php
declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

class AdminPrestaFormSettingsController extends ModuleAdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->bootstrap  = true;
        $this->meta_title = 'PrestaForm — Settings';
    }

    private function getSettings(): array
    {
        $rows = \Db::getInstance()->executeS(
            'SELECT setting_key, setting_value FROM `' . _DB_PREFIX_ . 'pf_settings`'
        ) ?: [];
        return array_column($rows, 'setting_value', 'setting_key');
    }

    public function renderList(): string
    {
        $this->context->smarty->assign(['settings' => $this->getSettings()]);
        return $this->context->smarty->fetch(
            _PS_MODULE_DIR_ . 'prestaform/views/templates/admin/settings/index.tpl'
        );
    }

    public function postProcess(): void
    {
        if (Tools::isSubmit('save_settings')) {
            $keys = [
                'recaptcha_v2_site_key', 'recaptcha_v2_secret_key',
                'recaptcha_v3_site_key', 'recaptcha_v3_secret_key',
                'turnstile_site_key',    'turnstile_secret_key',
                'default_retention_days',
            ];
            foreach ($keys as $key) {
                \Db::getInstance()->update(
                    'pf_settings',
                    ['setting_value' => pSQL((string) Tools::getValue($key, ''))],
                    'setting_key = \'' . pSQL($key) . '\''
                );
            }
            $this->confirmations[] = 'Settings saved.';
        }
        parent::postProcess();
    }
}
```

- [ ] **Step 2: Create views/templates/admin/settings/index.tpl**

```smarty
{extends file="helpers/view/view.tpl"}
{block name="override_tpl"}
<div class="panel">
  <div class="panel-heading"><i class="icon-cog"></i> PrestaForm — Global Settings</div>
  <div class="panel-body">
    <form method="post">
      <input type="hidden" name="save_settings" value="1">

      <h4>Google reCAPTCHA</h4>
      <div class="row">
        <div class="col-md-6">
          <div class="form-group">
            <label>reCAPTCHA v2 Site Key</label>
            <input type="text" name="recaptcha_v2_site_key" class="form-control"
                   value="{$settings.recaptcha_v2_site_key|default:''|escape}">
          </div>
        </div>
        <div class="col-md-6">
          <div class="form-group">
            <label>reCAPTCHA v2 Secret Key</label>
            <input type="text" name="recaptcha_v2_secret_key" class="form-control"
                   value="{$settings.recaptcha_v2_secret_key|default:''|escape}">
          </div>
        </div>
        <div class="col-md-6">
          <div class="form-group">
            <label>reCAPTCHA v3 Site Key</label>
            <input type="text" name="recaptcha_v3_site_key" class="form-control"
                   value="{$settings.recaptcha_v3_site_key|default:''|escape}">
          </div>
        </div>
        <div class="col-md-6">
          <div class="form-group">
            <label>reCAPTCHA v3 Secret Key</label>
            <input type="text" name="recaptcha_v3_secret_key" class="form-control"
                   value="{$settings.recaptcha_v3_secret_key|default:''|escape}">
          </div>
        </div>
      </div>

      <h4>Cloudflare Turnstile</h4>
      <div class="row">
        <div class="col-md-6">
          <div class="form-group">
            <label>Turnstile Site Key</label>
            <input type="text" name="turnstile_site_key" class="form-control"
                   value="{$settings.turnstile_site_key|default:''|escape}">
          </div>
        </div>
        <div class="col-md-6">
          <div class="form-group">
            <label>Turnstile Secret Key</label>
            <input type="text" name="turnstile_secret_key" class="form-control"
                   value="{$settings.turnstile_secret_key|default:''|escape}">
          </div>
        </div>
      </div>

      <h4>Defaults</h4>
      <div class="form-group" style="max-width:250px">
        <label>Default Submission Retention (days) <small>leave blank for forever</small></label>
        <input type="number" name="default_retention_days" class="form-control" min="1"
               value="{$settings.default_retention_days|default:''|escape}">
      </div>

      <button type="submit" class="btn btn-primary"><i class="icon-save"></i> Save Settings</button>
    </form>
  </div>
</div>
{/block}
```

- [ ] **Step 3: Commit**

```bash
git add controllers/admin/AdminPrestaFormSettingsController.php views/templates/admin/settings/
git commit -m "feat: add global settings controller and template"
```
