/**
 * PrestaForm admin JS — form builder tag generator and conditions/webhooks UI.
 */
(function () {
  'use strict';

  const TAG_PARAMS = {
    text:     [{ name: 'placeholder', label: 'Placeholder', type: 'text' }, { name: 'maxlength', label: 'Max length', type: 'number' }],
    email:    [{ name: 'placeholder', label: 'Placeholder', type: 'text' }],
    tel:      [{ name: 'placeholder', label: 'Placeholder', type: 'text' }],
    number:   [{ name: 'min', label: 'Min', type: 'number' }, { name: 'max', label: 'Max', type: 'number' }, { name: 'step', label: 'Step', type: 'number' }],
    date:     [{ name: 'min', label: 'Min date (or "today")', type: 'text' }, { name: 'max', label: 'Max date', type: 'text' }],
    textarea: [{ name: 'placeholder', label: 'Placeholder', type: 'text' }, { name: 'rows', label: 'Rows', type: 'number' }],
    select:   [],
    checkbox: [],
    radio:    [],
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

    document.querySelectorAll('.pf-tag-param').forEach(function (el) {
      if (el.value.trim()) {
        parts.push(el.dataset.param + ' "' + el.value.trim() + '"');
      }
    });

    const ib = document.getElementById('pfTagIncludeBlank');
    if (ib && ib.checked) parts.push('include_blank');

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

  document.querySelectorAll('.pf-tag-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      const type = btn.dataset.type;
      document.getElementById('pfTagModalTitle').textContent = type;
      document.getElementById('pfTagModalBody').innerHTML  = buildModalBody(type);
      document.getElementById('pfTagPreview').textContent  = '[' + type + ']';

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
    if (e.target.closest('.pf-remove-cg')) {
      e.target.closest('.pf-cg').remove();
    }
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
    if (e.target.closest('.pf-remove-rule')) {
      e.target.closest('.pf-rule').remove();
    }
  });

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

  document.addEventListener('click', function (e) {
    if (!e.target.closest('.pf-wh-test')) return;
    const panel  = e.target.closest('.pf-webhook-item');
    const id     = panel ? panel.dataset.id : null;
    const result = panel ? panel.querySelector('.pf-wh-test-result') : null;

    if (!id || !result) return;
    result.textContent = 'Testing\u2026';

    fetch(window.pfAdminUrl + '&action=test_webhook&id_webhook=' + id)
      .then(function (r) { return r.json(); })
      .then(function (data) {
        result.textContent = data.success
          ? '\u2713 ' + data.status + ' OK'
          : '\u2717 ' + (data.status || 'Error') + ' \u2014 ' + (data.body || '');
        result.style.color = data.success ? 'green' : 'red';
      });
  });

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
