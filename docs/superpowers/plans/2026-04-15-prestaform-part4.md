# PrestaForm Module Implementation Plan — Part 4: Front-End, JS, CSS & Mail Templates

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the front-end form template, all JavaScript (admin form builder + front-end conditions/AJAX), CSS, and email templates.

**Prerequisites:** Parts 1, 2, 3 complete.

**Tech Stack:** Vanilla JS (ES2020), Smarty, CSS.

---

### Task 16: Front-End Form Template

**Files:**
- Create: `views/templates/front/form.tpl`

- [ ] **Step 1: Create views/templates/front/form.tpl**

The front-end form rendering is done entirely by `FormRenderer::render()` which returns the full HTML string. This template is only used as a fallback wrapper when calling `$this->setTemplate()` in a dedicated front controller (not needed for shortcode embedding). The actual shortcode output is produced by `FormRenderer` directly.

Create the file as a minimal passthrough for any standalone form page:

```smarty
{extends file=$layout}
{block name="content"}
  <section id="main">
    <div class="container">
      <h1>{$form_title|escape}</h1>
      {if $pf_success}
        <div class="alert alert-success">{$success_message|escape}</div>
      {elseif $pf_error}
        <div class="alert alert-danger">There was a problem submitting the form. Please try again.</div>
      {/if}
      {$form_html nofilter}
    </div>
  </section>
{/block}
```

- [ ] **Step 2: Create views/templates/mail/prestaform_notification.html**

```html
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>{subject}</title></head>
<body style="font-family:Arial,sans-serif;font-size:14px;color:#333">
  <div style="max-width:600px;margin:0 auto;padding:20px">
    <h2 style="color:#25b9d7">New Form Submission</h2>
    <div style="background:#f9f9f9;padding:16px;border-radius:4px;line-height:1.8">
      {message}
    </div>
    <p style="font-size:11px;color:#aaa;margin-top:24px">Sent by PrestaForm</p>
  </div>
</body>
</html>
```

- [ ] **Step 3: Create views/templates/mail/prestaform_notification.txt**

```
New Form Submission
===================

{message}

-- Sent by PrestaForm
```

- [ ] **Step 4: Create views/templates/mail/prestaform_confirmation.html**

```html
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>{subject}</title></head>
<body style="font-family:Arial,sans-serif;font-size:14px;color:#333">
  <div style="max-width:600px;margin:0 auto;padding:20px">
    <h2 style="color:#25b9d7">Thank you for your message</h2>
    <div style="background:#f9f9f9;padding:16px;border-radius:4px;line-height:1.8">
      {message}
    </div>
    <p style="font-size:11px;color:#aaa;margin-top:24px">This is an automated confirmation.</p>
  </div>
</body>
</html>
```

- [ ] **Step 5: Create views/templates/mail/prestaform_confirmation.txt**

```
{message}

-- This is an automated confirmation.
```

- [ ] **Step 6: Commit**

```bash
git add views/templates/front/ views/templates/mail/
git commit -m "feat: add front-end form template and email HTML/text templates"
```

---

### Task 17: Front-End CSS

**Files:**
- Create: `views/css/prestaform.css`
- Create: `views/css/admin.css`

- [ ] **Step 1: Create views/css/prestaform.css**

```css
/* PrestaForm — front-end styles */

.prestaform-wrapper {
  max-width: 700px;
}

.pf-field {
  margin-bottom: 16px;
}

.pf-field input[type="text"],
.pf-field input[type="email"],
.pf-field input[type="tel"],
.pf-field input[type="number"],
.pf-field input[type="date"],
.pf-field input[type="file"],
.pf-field textarea,
.pf-field select {
  width: 100%;
  padding: 8px 12px;
  border: 1px solid #ccc;
  border-radius: 4px;
  font-size: 14px;
  box-sizing: border-box;
  transition: border-color 0.2s;
}

.pf-field input:focus,
.pf-field textarea:focus,
.pf-field select:focus {
  border-color: #25b9d7;
  outline: none;
  box-shadow: 0 0 0 2px rgba(37, 185, 215, 0.15);
}

.pf-field input.pf-error,
.pf-field textarea.pf-error,
.pf-field select.pf-error {
  border-color: #e74c3c;
}

.pf-field .pf-error-msg {
  color: #e74c3c;
  font-size: 12px;
  margin-top: 4px;
  display: block;
}

.pf-field.pf-hidden {
  display: none !important;
}

.pf-submit {
  padding: 10px 28px;
  background: #25b9d7;
  color: #fff;
  border: none;
  border-radius: 4px;
  font-size: 15px;
  cursor: pointer;
  transition: background 0.2s;
}

.pf-submit:hover {
  background: #1da5c0;
}

.pf-submit:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.pf-success-message {
  padding: 16px;
  background: #d4edda;
  border: 1px solid #c3e6cb;
  border-radius: 4px;
  color: #155724;
  font-size: 15px;
}

.pf-global-error {
  padding: 12px;
  background: #f8d7da;
  border: 1px solid #f5c6cb;
  border-radius: 4px;
  color: #721c24;
  margin-bottom: 12px;
  font-size: 14px;
}
```

- [ ] **Step 2: Create views/css/admin.css**

```css
/* PrestaForm — admin styles */

#pf-tag-panel .list-group-item {
  padding: 6px 12px;
  font-size: 13px;
  cursor: pointer;
}

#pf-tag-panel .list-group-item:hover {
  background: #e8f4f8;
  color: #25b9d7;
}

#pf-template {
  min-height: 300px;
  line-height: 1.6;
}

.pf-cg {
  margin-bottom: 12px;
}

.pf-rule {
  background: #f9f9f9;
  padding: 6px;
  border-radius: 4px;
  margin-bottom: 6px;
}

.pf-webhook-item .panel-heading {
  background: #f9f9f9;
}
```

- [ ] **Step 3: Commit**

```bash
git add views/css/
git commit -m "feat: add front-end and admin CSS"
```

---

### Task 18: Front-End JavaScript (Conditions + AJAX Submit)

**Files:**
- Create: `views/js/front/prestaform.js`

- [ ] **Step 1: Create views/js/front/prestaform.js**

```javascript
/**
 * PrestaForm front-end JS
 * - Evaluates condition rules and shows/hides fields
 * - Handles AJAX form submission
 * - Handles file-upload forms (multipart, non-AJAX)
 */
(function () {
  'use strict';

  /**
   * Evaluate a single condition rule against current form values.
   * @param {HTMLFormElement} form
   * @param {{field: string, operator: string, value: string}} rule
   * @returns {boolean}
   */
  function evalRule(form, rule) {
    const el = form.querySelector('[name="' + rule.field + '"]') ||
               form.querySelector('[name="' + rule.field + '[]"]');
    const actual = el ? (el.type === 'checkbox' ? (el.checked ? el.value : '') : el.value) : '';

    switch (rule.operator) {
      case 'equals':       return actual === rule.value;
      case 'not_equals':   return actual !== rule.value;
      case 'contains':     return actual.includes(rule.value);
      case 'is_empty':     return actual === '';
      case 'is_not_empty': return actual !== '';
      default:             return false;
    }
  }

  /**
   * Apply all condition groups for a form wrapper.
   * @param {HTMLElement} wrapper  The #prestaform-N div
   */
  function applyConditions(wrapper) {
    const formId     = wrapper.dataset.pfId || wrapper.id.replace('prestaform-', '');
    const conditions = (window.pfConditions && window.pfConditions[formId]) || [];
    const form       = wrapper.querySelector('form');
    if (!form) return;

    conditions.forEach(function (group) {
      const target  = wrapper.querySelector('[data-pf-name="' + group.target_field + '"]');
      if (!target) return;

      const rules   = group.rules || [];
      const logic   = (group.logic || 'AND').toUpperCase();
      const results = rules.map(function (rule) { return evalRule(form, rule); });

      let conditionMet;
      if (logic === 'OR') {
        conditionMet = results.some(Boolean);
      } else {
        conditionMet = results.every(Boolean);
      }

      const shouldShow = group.action === 'show' ? conditionMet : !conditionMet;
      target.classList.toggle('pf-hidden', !shouldShow);

      // Disable hidden required fields so they don't block submission
      target.querySelectorAll('[required]').forEach(function (el) {
        el.disabled = !shouldShow;
      });
    });
  }

  /**
   * Show inline field error.
   * @param {HTMLElement} fieldWrapper  .pf-field div
   * @param {string}      message
   */
  function showError(fieldWrapper, message) {
    fieldWrapper.querySelector('input, textarea, select')?.classList.add('pf-error');
    let msg = fieldWrapper.querySelector('.pf-error-msg');
    if (!msg) {
      msg = document.createElement('span');
      msg.className = 'pf-error-msg';
      fieldWrapper.appendChild(msg);
    }
    msg.textContent = message;
  }

  /**
   * Clear all errors on a form wrapper.
   * @param {HTMLElement} wrapper
   */
  function clearErrors(wrapper) {
    wrapper.querySelectorAll('.pf-error').forEach(function (el) {
      el.classList.remove('pf-error');
    });
    wrapper.querySelectorAll('.pf-error-msg').forEach(function (el) {
      el.remove();
    });
    const globalErr = wrapper.querySelector('.pf-global-error');
    if (globalErr) globalErr.remove();
  }

  /**
   * Handle AJAX form submission.
   * @param {HTMLElement} wrapper
   * @param {HTMLFormElement} form
   * @param {SubmitEvent} e
   */
  function handleAjaxSubmit(wrapper, form, e) {
    e.preventDefault();
    clearErrors(wrapper);

    const submitBtn = form.querySelector('.pf-submit');
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.dataset.origText = submitBtn.textContent;
      submitBtn.textContent = 'Sending…';
    }

    fetch(form.action, {
      method: 'POST',
      body: new FormData(form),
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
    })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        if (data.success) {
          // Replace form with success message
          const msg = document.createElement('div');
          msg.className = 'pf-success-message';
          msg.textContent = wrapper.dataset.successMessage ||
            'Thank you! Your message has been sent.';
          wrapper.innerHTML = '';
          wrapper.appendChild(msg);
        } else {
          // Re-enable submit button
          if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = submitBtn.dataset.origText || 'Submit';
          }

          // Show field-level errors
          const errors = data.errors || {};
          if (errors._form || errors._captcha) {
            const div = document.createElement('div');
            div.className = 'pf-global-error';
            div.textContent = errors._form || errors._captcha;
            form.prepend(div);
          }

          Object.keys(errors).forEach(function (fieldName) {
            if (fieldName.startsWith('_')) return;
            const fieldWrap = wrapper.querySelector('[data-pf-name="' + fieldName + '"]');
            if (fieldWrap) {
              showError(fieldWrap, errors[fieldName]);
            }
          });
        }
      })
      .catch(function () {
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = submitBtn.dataset.origText || 'Submit';
        }
        const div = document.createElement('div');
        div.className = 'pf-global-error';
        div.textContent = 'A network error occurred. Please try again.';
        form.prepend(div);
      });
  }

  /**
   * Initialise all .prestaform-wrapper elements on the page.
   */
  function initForms() {
    document.querySelectorAll('.prestaform-wrapper').forEach(function (wrapper) {
      const form = wrapper.querySelector('form');
      if (!form) return;

      // Store success message from a data attribute written by FormRenderer
      const formId = wrapper.id.replace('prestaform-', '');

      // Apply initial condition state
      applyConditions(wrapper);

      // Re-apply conditions on every input change
      form.addEventListener('input',  function () { applyConditions(wrapper); });
      form.addEventListener('change', function () { applyConditions(wrapper); });

      // AJAX submit (skip if form has file input — handled as multipart redirect)
      const hasFile = form.querySelector('input[type="file"]');
      if (!hasFile) {
        form.addEventListener('submit', function (e) {
          handleAjaxSubmit(wrapper, form, e);
        });
      }

      // Handle redirect-based success/error (for file forms)
      const params = new URLSearchParams(window.location.search);
      if (params.get('pf_success') === '1') {
        const msg = document.createElement('div');
        msg.className = 'pf-success-message';
        msg.textContent = 'Thank you! Your message has been sent.';
        wrapper.prepend(msg);
        form.style.display = 'none';
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initForms);
  } else {
    initForms();
  }
})();
```

- [ ] **Step 2: Commit**

```bash
git add views/js/front/prestaform.js
git commit -m "feat: add front-end conditions evaluator and AJAX submit handler"
```

---

### Task 19: Admin JavaScript (Form Builder + Tag Generator)

**Files:**
- Create: `views/js/admin/form-builder.js`

- [ ] **Step 1: Create views/js/admin/form-builder.js**

```javascript
/**
 * PrestaForm admin JS — form builder tag generator and conditions/webhooks UI.
 */
(function () {
  'use strict';

  // ── Tag Generator ──────────────────────────────────────────────────────────

  /** Field parameter definitions per tag type */
  const TAG_PARAMS = {
    text:     [{ name: 'placeholder', label: 'Placeholder', type: 'text' }, { name: 'maxlength', label: 'Max length', type: 'number' }],
    email:    [{ name: 'placeholder', label: 'Placeholder', type: 'text' }],
    tel:      [{ name: 'placeholder', label: 'Placeholder', type: 'text' }],
    number:   [{ name: 'min', label: 'Min', type: 'number' }, { name: 'max', label: 'Max', type: 'number' }, { name: 'step', label: 'Step', type: 'number' }],
    date:     [{ name: 'min', label: 'Min date (or "today")', type: 'text' }, { name: 'max', label: 'Max date', type: 'text' }],
    textarea: [{ name: 'placeholder', label: 'Placeholder', type: 'text' }, { name: 'rows', label: 'Rows', type: 'number' }],
    select:   [], // options handled separately
    checkbox: [], // options handled separately
    radio:    [], // options handled separately
    file:     [{ name: 'accept', label: 'Accepted extensions (e.g. .pdf,.docx)', type: 'text' }, { name: 'limit', label: 'Max size (e.g. 5mb)', type: 'text' }],
    hidden:   [{ name: 'default', label: 'Default value', type: 'text' }],
    recaptcha:[],
    submit:   [],
  };

  const HAS_OPTIONS = ['select', 'checkbox', 'radio', 'submit'];
  const NO_NAME     = ['recaptcha', 'submit'];

  function buildModalBody(type) {
    const params = TAG_PARAMS[type] || [];
    let html = '';

    if (!NO_NAME.includes(type)) {
      html += `<div class="form-group">
        <label>Field name (slug) <small class="text-muted">letters, numbers, hyphens</small></label>
        <input type="text" class="form-control" id="pfTagName" placeholder="your-field-name">
      </div>`;

      if (!['hidden', 'file'].includes(type)) {
        html += `<div class="form-group">
          <label><input type="checkbox" id="pfTagRequired"> Required field</label>
        </div>`;
      }
    }

    params.forEach(function (p) {
      html += `<div class="form-group">
        <label>${p.label}</label>
        <input type="${p.type}" class="form-control pf-tag-param" data-param="${p.name}" placeholder="${p.label}">
      </div>`;
    });

    if (HAS_OPTIONS.includes(type)) {
      const label = type === 'submit' ? 'Button label' : 'Options (one per line, or label|value)';
      const placeholder = type === 'submit' ? 'Send Message' : 'Option A\nOption B\nOption C|c';
      html += `<div class="form-group">
        <label>${label}</label>
        <textarea class="form-control" id="pfTagOptions" rows="${type === 'submit' ? 2 : 5}"
                  placeholder="${placeholder}"></textarea>
      </div>`;

      if (['select'].includes(type)) {
        html += `<div class="form-group">
          <label><input type="checkbox" id="pfTagIncludeBlank"> Include blank option</label>
        </div>`;
      }
    }

    return html;
  }

  function buildTagString(type) {
    const parts = [type];

    const required = document.getElementById('pfTagRequired');
    if (required && required.checked) parts[0] = type + '*';

    const name = (document.getElementById('pfTagName') || {}).value;
    if (name) parts.push(name);

    // Named params
    document.querySelectorAll('.pf-tag-param').forEach(function (el) {
      if (el.value.trim()) {
        parts.push(el.dataset.param + ' "' + el.value.trim() + '"');
      }
    });

    // include_blank flag
    const ib = document.getElementById('pfTagIncludeBlank');
    if (ib && ib.checked) parts.push('include_blank');

    // Options / labels
    const optEl = document.getElementById('pfTagOptions');
    if (optEl && optEl.value.trim()) {
      optEl.value.trim().split('\n').forEach(function (line) {
        line = line.trim();
        if (line) parts.push('"' + line + '"');
      });
    }

    return '[' + parts.join(' ') + ']';
  }

  function insertIntoTextarea(text) {
    const ta = document.getElementById('pf-template');
    if (!ta) return;
    const pos = ta.selectionStart;
    const before = ta.value.substring(0, pos);
    const after  = ta.value.substring(ta.selectionEnd);
    ta.value = before + text + after;
    ta.selectionStart = ta.selectionEnd = pos + text.length;
    ta.focus();
    ta.dispatchEvent(new Event('input'));
  }

  // Bind tag type buttons
  document.querySelectorAll('.pf-tag-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      const type = btn.dataset.type;
      document.getElementById('pfTagModalTitle').textContent = type;
      document.getElementById('pfTagModalBody').innerHTML  = buildModalBody(type);
      document.getElementById('pfTagPreview').textContent  = '[' + type + ']';

      // Live preview
      document.getElementById('pfTagModalBody').addEventListener('input', function () {
        document.getElementById('pfTagPreview').textContent = buildTagString(type);
      });

      document.getElementById('pfInsertTag').onclick = function () {
        insertIntoTextarea(buildTagString(type));
        jQuery('#pfTagModal').modal('hide');
      };

      jQuery('#pfTagModal').modal('show');
    });
  });

  // ── Conditions UI ──────────────────────────────────────────────────────────

  const fieldNames = Array.from(
    document.querySelectorAll('[data-pf-name]'),
  ).map(function (el) { return el.dataset.pfName; });

  function makeFieldSelect(cls, selected) {
    let opts = fieldNames.map(function (n) {
      return '<option value="' + n + '"' + (n === selected ? ' selected' : '') + '>' + n + '</option>';
    }).join('');
    return '<select class="form-control ' + cls + '">' + opts + '</select>';
  }

  const OPERATORS = ['equals', 'not_equals', 'contains', 'is_empty', 'is_not_empty'];

  function makeOperatorSelect(selected) {
    let opts = OPERATORS.map(function (op) {
      return '<option value="' + op + '"' + (op === selected ? ' selected' : '') + '>' + op.replace('_', ' ') + '</option>';
    }).join('');
    return '<select class="form-control pf-rule-operator">' + opts + '</select>';
  }

  const addCgBtn = document.getElementById('pf-add-cg');
  if (addCgBtn) {
    addCgBtn.addEventListener('click', function () {
      const tpl = `
      <div class="panel panel-default pf-cg">
        <div class="panel-heading">
          Rule:
          <select class="pf-cg-action form-control" style="display:inline;width:auto">
            <option value="show">Show</option><option value="hide">Hide</option>
          </select>
          field: ${makeFieldSelect('pf-cg-target', fieldNames[0])}
          when
          <select class="pf-cg-logic form-control" style="display:inline;width:auto">
            <option value="AND">ALL</option><option value="OR">ANY</option>
          </select>
          of:
          <button type="button" class="btn btn-danger btn-xs pull-right pf-remove-cg"><i class="icon-trash"></i></button>
        </div>
        <div class="panel-body">
          <div class="pf-cg-rules"></div>
          <button type="button" class="btn btn-default btn-xs pf-add-rule">+ Add condition</button>
        </div>
      </div>`;
      document.getElementById('pf-condition-groups').insertAdjacentHTML('beforeend', tpl);
    });
  }

  document.addEventListener('click', function (e) {
    // Remove condition group
    if (e.target.closest('.pf-remove-cg')) {
      e.target.closest('.pf-cg').remove();
    }
    // Add rule row
    if (e.target.closest('.pf-add-rule')) {
      const rulesContainer = e.target.closest('.pf-cg').querySelector('.pf-cg-rules');
      rulesContainer.insertAdjacentHTML('beforeend', `
        <div class="pf-rule row" style="margin-bottom:8px">
          <div class="col-sm-4">${makeFieldSelect('pf-rule-field', fieldNames[0])}</div>
          <div class="col-sm-3">${makeOperatorSelect('equals')}</div>
          <div class="col-sm-4"><input type="text" class="form-control pf-rule-value" placeholder="value"></div>
          <div class="col-sm-1"><button type="button" class="btn btn-danger btn-xs pf-remove-rule"><i class="icon-trash"></i></button></div>
        </div>`);
    }
    // Remove rule row
    if (e.target.closest('.pf-remove-rule')) {
      e.target.closest('.pf-rule').remove();
    }
  });

  // Serialize conditions to JSON before save
  const condForm = document.querySelector('#tab-conditions form');
  if (condForm) {
    condForm.addEventListener('submit', function () {
      const groups = [];
      document.querySelectorAll('.pf-cg').forEach(function (cg) {
        const rules = [];
        cg.querySelectorAll('.pf-rule').forEach(function (rule) {
          rules.push({
            field:    rule.querySelector('.pf-rule-field').value,
            operator: rule.querySelector('.pf-rule-operator').value,
            value:    rule.querySelector('.pf-rule-value').value,
          });
        });
        groups.push({
          target_field: cg.querySelector('.pf-cg-target').value,
          action:       cg.querySelector('.pf-cg-action').value,
          logic:        cg.querySelector('.pf-cg-logic').value,
          rules:        rules,
        });
      });
      document.getElementById('conditions_json').value = JSON.stringify(groups);
    });
  }

  // ── Webhook UI ─────────────────────────────────────────────────────────────

  // Save webhook
  document.addEventListener('click', function (e) {
    if (!e.target.closest('.pf-wh-save')) return;
    const panel = e.target.closest('.pf-webhook-item, .pf-webhook-new');
    const formId = document.querySelector('[name="id_form"]').value;

    const fieldCheckboxes = panel.querySelectorAll('.pf-wh-field-chk:checked');
    const useAllFields    = panel.querySelector('.pf-wh-fields-all').checked;

    const headersRaw = panel.querySelector('.pf-wh-headers').value.trim();
    const headers    = headersRaw.split('\n').filter(Boolean).map(function (line) {
      const [key, ...rest] = line.split(':');
      return { key: key.trim(), value: rest.join(':').trim() };
    }).filter(function (h) { return h.key; });

    const payload = {
      id_webhook:      parseInt(panel.querySelector('.pf-wh-id').value || '0', 10),
      id_form:         parseInt(formId, 10),
      name:            panel.querySelector('.pf-wh-name').value,
      url:             panel.querySelector('.pf-wh-url').value,
      method:          panel.querySelector('.pf-wh-method').value,
      headers:         headers,
      field_map:       useAllFields ? null : Array.from(fieldCheckboxes).map(function (cb) { return cb.value; }),
      retry_count:     parseInt(panel.querySelector('.pf-wh-retry').value, 10),
      timeout_seconds: parseInt(panel.querySelector('.pf-wh-timeout').value, 10),
      active:          parseInt(panel.querySelector('.pf-wh-active').value, 10),
    };

    fetch(window.pfAdminUrl + '&action=save_webhooks', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id_form: formId, webhooks_json: JSON.stringify([payload]) }),
    }).then(function () {
      location.reload();
    });
  });

  // Test webhook
  document.addEventListener('click', function (e) {
    if (!e.target.closest('.pf-wh-test')) return;
    const panel  = e.target.closest('.pf-webhook-item');
    const id     = panel ? panel.dataset.id : null;
    const result = panel ? panel.querySelector('.pf-wh-test-result') : null;

    if (!id || !result) return;
    result.textContent = 'Testing…';

    fetch(window.pfAdminUrl + '&action=test_webhook&id_webhook=' + id)
      .then(function (r) { return r.json(); })
      .then(function (data) {
        result.textContent = data.success
          ? '✓ ' + data.status + ' OK'
          : '✗ ' + (data.status || 'Error') + ' — ' + (data.body || '');
        result.style.color = data.success ? 'green' : 'red';
      });
  });

  // Add webhook button
  const addWhBtn = document.getElementById('pf-add-webhook');
  if (addWhBtn) {
    addWhBtn.addEventListener('click', function () {
      const tplEl  = document.getElementById('pf-webhook-tpl');
      const newDiv = document.createElement('div');
      newDiv.className = 'panel panel-default pf-webhook-new';
      newDiv.innerHTML = `
        <div class="panel-heading">New Webhook</div>
        <div class="panel-body">
          <input type="hidden" class="pf-wh-id" value="0">
          ${tplEl.innerHTML}
        </div>`;
      document.getElementById('pf-webhook-list').prepend(newDiv);
    });
  }

  // Delete webhook
  document.addEventListener('click', function (e) {
    if (!e.target.closest('.pf-wh-delete')) return;
    if (!confirm('Delete this webhook?')) return;
    const panel = e.target.closest('.pf-webhook-item');
    if (panel) {
      const id = panel.dataset.id;
      fetch(window.pfAdminUrl + '&action=delete_webhook&id_webhook=' + id, { method: 'POST' })
        .then(function () { panel.remove(); });
    } else {
      e.target.closest('.pf-webhook-new').remove();
    }
  });

  // Toggle field selector visibility
  document.addEventListener('change', function (e) {
    if (!e.target.classList.contains('pf-wh-fields-sel') &&
        !e.target.classList.contains('pf-wh-fields-all')) return;
    const panel   = e.target.closest('[class*="pf-webhook"]');
    const showSel = panel.querySelector('.pf-wh-fields-sel').checked;
    panel.querySelector('.pf-wh-field-checkboxes').style.display = showSel ? '' : 'none';
  });

  // ── Mail routing rows ──────────────────────────────────────────────────────

  const addRouteBtn = document.getElementById('pf-add-route');
  if (addRouteBtn) {
    addRouteBtn.addEventListener('click', function () {
      const fieldOpts = fieldNames.map(function (n) {
        return '<option value="' + n + '">' + n + '</option>';
      }).join('');
      document.getElementById('pf-routing-rows').insertAdjacentHTML('beforeend', `
        <tr class="pf-routing-row">
          <td><select class="form-control pf-route-field">${fieldOpts}</select></td>
          <td><input type="text" class="form-control pf-route-value" placeholder="value"></td>
          <td><input type="text" class="form-control pf-route-email" placeholder="email@store.com"></td>
          <td><button type="button" class="btn btn-danger btn-xs pf-remove-route"><i class="icon-trash"></i></button></td>
        </tr>`);
    });
  }

  document.addEventListener('click', function (e) {
    if (e.target.closest('.pf-remove-route')) {
      e.target.closest('.pf-routing-row').remove();
    }
  });

  // Serialize mail routes before save
  const mailForm = document.querySelector('#tab-mail form');
  if (mailForm) {
    mailForm.addEventListener('submit', function () {
      const routes = [];
      ['admin', 'confirmation'].forEach(function (type) {
        const panel   = document.getElementById('mail-' + type);
        if (!panel) return;

        const addrs   = panel.querySelector('.pf-notify-addresses').value.trim()
                             .split('\n').map(function (s) { return s.trim(); }).filter(Boolean);
        const subject = panel.querySelector('.pf-subject').value;
        const body    = panel.querySelector('.pf-body').value;
        const enabled = type === 'admin' ? 1 : (panel.querySelector('.pf-mail-enabled').checked ? 1 : 0);
        const replyEl = panel.querySelector('.pf-reply-to');
        const routing = [];

        if (type === 'admin') {
          document.querySelectorAll('.pf-routing-row').forEach(function (row) {
            routing.push({
              field: row.querySelector('.pf-route-field').value,
              value: row.querySelector('.pf-route-value').value,
              email: row.querySelector('.pf-route-email').value,
            });
          });
        }

        routes.push({
          type:              type,
          enabled:           enabled,
          notify_addresses:  addrs,
          reply_to:          replyEl ? replyEl.value : null,
          subject:           subject,
          body:              body,
          routing_rules:     routing,
        });
      });
      document.getElementById('mail_routes_json').value = JSON.stringify(routes);
    });
  }

  // ── Retention custom input ─────────────────────────────────────────────────

  const retentionSel = document.getElementById('retention-select');
  if (retentionSel) {
    retentionSel.addEventListener('change', function () {
      const input = document.getElementById('retention-custom-input');
      if (retentionSel.value === 'custom') {
        input.style.display = '';
        // Rename to retention_days so it's submitted
        input.name = 'retention_days';
        retentionSel.name = '';
      } else {
        input.style.display = 'none';
        input.name = '';
        retentionSel.name = 'retention_days';
      }
    });
  }

  // ── Copy embed shortcode ───────────────────────────────────────────────────

  window.pfCopyEmbed = function () {
    const el = document.getElementById('pf-embed-code');
    if (!el) return;
    navigator.clipboard.writeText(el.value).then(function () {
      alert('Shortcode copied to clipboard.');
    });
  };

})();
```

- [ ] **Step 2: Commit**

```bash
git add views/js/admin/form-builder.js
git commit -m "feat: add admin form-builder JS (tag generator, conditions UI, webhook UI, mail UI)"
```

---

### Task 20: Final Wiring & Self-Test Checklist

**Files:**
- Create: `.gitignore`
- Create: `index.php` (PS security file)
- Create: `uploads/.htaccess`

- [ ] **Step 1: Create .gitignore**

```gitignore
/vendor/
/uploads/
/.superpowers/
*.DS_Store
```

- [ ] **Step 2: Create index.php (required in PS module root and all subdirs)**

Create `index.php` in the module root:

```php
<?php
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Location: ../');
exit;
```

Also create `index.php` with identical content in each subdirectory:
`config/`, `controllers/`, `controllers/admin/`, `controllers/front/`, `src/`, `src/Repository/`, `src/Service/`, `src/Entity/`, `views/`, `views/css/`, `views/js/`, `views/js/admin/`, `views/js/front/`, `views/templates/`, `views/templates/admin/`, `views/templates/front/`, `views/templates/mail/`, `sql/`, `translations/`

- [ ] **Step 3: Create uploads/.htaccess (prevent execution of uploaded files)**

```apache
<FilesMatch "\.(php|phtml|php3|php4|php5|pl|py|jsp|asp|sh|cgi)$">
  Order Allow,Deny
  Deny from all
</FilesMatch>
Options -ExecCGI -Indexes
```

- [ ] **Step 4: Run full test suite one final time**

```bash
./vendor/bin/phpunit -v
```

Expected output:
```
PrestaForm\Tests\Unit\Service\ShortcodeParserTest
  ✓ Parses simple text field
  ✓ Parses optional field
  ... (11 tests)

PrestaForm\Tests\Unit\Service\FormRendererTest
  ✓ Renders text input
  ... (13 tests)

PrestaForm\Tests\Unit\Service\ConditionEvaluatorTest
  ✓ Field visible when no groups
  ... (10 tests)

PrestaForm\Tests\Unit\Service\EmailRouterTest
  ✓ Render template substitutes field vars
  ... (9 tests)

OK (43 tests, XX assertions)
```

- [ ] **Step 5: Final commit**

```bash
git add .gitignore index.php uploads/.htaccess
git add $(find . -name "index.php" -not -path "./vendor/*")
git commit -m "feat: add security index.php files, .gitignore, uploads .htaccess"
```

---

### Task 21: Manual Smoke Test Checklist

No code to write — this is a checklist for when you have a PS9 environment.

- [ ] Install module from Back Office → Modules
- [ ] Verify 7 database tables created: `pf_forms`, `pf_submissions`, `pf_webhooks`, `pf_webhook_log`, `pf_conditions`, `pf_email_routes`, `pf_settings`
- [ ] Verify 3 menu items appear: PrestaForm > Forms, Submissions, Settings
- [ ] Create a new form with template:
  ```
  <label>Name</label>
  [text* your-name placeholder "Your name"]
  <label>Email</label>
  [email* your-email]
  <label>Department</label>
  [select department "Sales" "Support" "Other"]
  <label>Other (if Other selected)</label>
  [text other-details]
  [submit "Send"]
  ```
- [ ] Add a condition: show `other-details` when `department` equals `Other`
- [ ] Set status to Active
- [ ] Embed `{prestaform id="1"}` in a CMS page and verify the form renders
- [ ] Submit the form and verify:
  - Submission appears in Submissions view
  - Admin notification email is received
  - Webhook fires (if configured) and log entry appears
- [ ] Verify `other-details` field is hidden until department = Other
- [ ] Test CSV export from Submissions view
- [ ] Uninstall module and verify all 7 tables are dropped
