/**
 * PrestaForm admin JS — form builder tag generator and conditions/webhooks UI.
 */
(function () {
  'use strict';

  // ── Tag parameter definitions ──────────────────────────────────────────────
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
    if (required && required.checked) { parts[0] = type + '*'; }

    const nameEl = document.getElementById('pfTagName');
    const name = nameEl ? nameEl.value.trim() : '';
    if (name) {
      if (!/^[a-z][a-z0-9_-]*$/.test(name)) {
        alert('Field name must start with a letter and contain only lowercase letters, numbers, hyphens, and underscores.');
        return null;
      }
      parts.push(name);
    }

    document.querySelectorAll('.pf-tag-param').forEach(function (el) {
      if (el.value.trim()) {
        parts.push(el.dataset.param + ' "' + el.value.trim().replace(/"/g, '\\"') + '"');
      }
    });

    const ib = document.getElementById('pfTagIncludeBlank');
    if (ib && ib.checked) { parts.push('include_blank'); }

    const optEl = document.getElementById('pfTagOptions');
    if (optEl && optEl.value.trim()) {
      optEl.value.trim().split('\n').forEach(function (line) {
        line = line.trim();
        if (line) { parts.push('"' + line.replace(/"/g, '\\"') + '"'); }
      });
    }

    return '[' + parts.join(' ') + ']';
  }

  function insertIntoTextarea(text) {
    const ta = document.getElementById('pf-template');
    if (!ta) { return; }
    const pos    = ta.selectionStart;
    const before = ta.value.substring(0, pos);
    const after  = ta.value.substring(ta.selectionEnd);
    ta.value = before + text + after;
    ta.selectionStart = ta.selectionEnd = pos + text.length;
    ta.focus();
    ta.dispatchEvent(new Event('input'));
  }

  // ── Inline tag configurator — event delegation (robust, timing-independent) ─
  //
  // Instead of attaching listeners in a DOMContentLoaded callback (which can miss
  // if the PS9 admin theme defers script execution), we register a single document-
  // level delegated listener immediately when the IIFE runs.  The handler looks up
  // panel elements at click-time so it never depends on DOM readiness at setup-time.

  var pfTagCurrentType = '';

  function pfTagShow(type) {
    var panel  = document.getElementById('pf-tag-config');
    var title  = document.getElementById('pf-tag-config-title');
    var body   = document.getElementById('pf-tag-config-body');
    var prev   = document.getElementById('pf-tag-preview');
    if (!panel) { return; }
    pfTagCurrentType = type;
    if (title) { title.textContent = 'Configure [' + type + ']'; }
    if (body)  { body.innerHTML    = buildModalBody(type); }
    if (prev)  { prev.textContent  = '[' + type + ']'; }
    panel.style.display = 'block';
  }

  function pfTagHide() {
    pfTagCurrentType = '';
    var panel = document.getElementById('pf-tag-config');
    if (panel) { panel.style.display = 'none'; }
  }

  // Single delegated click handler — works even before DOMContentLoaded
  document.addEventListener('click', function (e) {
    // Tag type button
    var tagBtn = e.target.closest('.pf-tag-btn');
    if (tagBtn) {
      var type  = tagBtn.getAttribute('data-type');
      var panel = document.getElementById('pf-tag-config');
      if (pfTagCurrentType === type && panel && panel.style.display !== 'none') {
        pfTagHide();
      } else {
        pfTagShow(type);
      }
      return;
    }
    // Insert Tag button
    if (e.target.closest('#pf-tag-insert')) {
      if (pfTagCurrentType) {
        var tagStr = buildTagString(pfTagCurrentType);
        if (tagStr !== null) {
          insertIntoTextarea(tagStr);
          pfTagHide();
        }
      }
      return;
    }
    // Close (×) button
    if (e.target.closest('#pf-tag-config-close')) {
      pfTagHide();
      return;
    }
  });

  // Live preview while user edits options in the config panel
  document.addEventListener('input', function (e) {
    if (!e.target.closest('#pf-tag-config-body')) { return; }
    var prev = document.getElementById('pf-tag-preview');
    if (prev) {
      var nameEl = document.getElementById('pfTagName');
      var name = nameEl ? nameEl.value.trim() : '';
      if (name && !/^[a-z][a-z0-9_-]*$/.test(name)) {
        prev.textContent = 'Invalid field name — use lowercase letters, numbers, hyphens, underscores';
        prev.style.color = '#c0392b';
      } else {
        prev.style.color = '';
        var tagStr = buildTagString(pfTagCurrentType);
        if (tagStr !== null) { prev.textContent = tagStr; }
      }
    }
  });

  // initTagButtons is kept as a no-op so the DOMContentLoaded call below is harmless
  function initTagButtons() { /* delegation wired above — nothing to do here */ }

  // ── Conditions UI ──────────────────────────────────────────────────────────
  //
  // fieldNames and DOM-dependent setup live inside initConditionsUI() which is
  // called from initAll() (after DOMContentLoaded) so elements are guaranteed
  // to exist regardless of where in the <head>/<body> the script tag appears.

  var fieldNames = []; // populated in initConditionsUI()

  const OPERATORS = ['equals', 'not_equals', 'contains', 'is_empty', 'is_not_empty'];

  function makeFieldSelect(cls, selected) {
    var opts = fieldNames.map(function (n) {
      return '<option value="' + n + '"' + (n === selected ? ' selected' : '') + '>' + n + '</option>';
    }).join('');
    return '<select class="form-control ' + cls + '">' + opts + '</select>';
  }

  function makeOperatorSelect(selected) {
    var opts = OPERATORS.map(function (op) {
      return '<option value="' + op + '"' + (op === selected ? ' selected' : '') + '>' + op.replace('_', ' ') + '</option>';
    }).join('');
    return '<select class="form-control pf-rule-operator">' + opts + '</select>';
  }

  // Delegated handlers for add/remove rule buttons — safe to wire at IIFE time
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

  function initConditionsUI() {
    // Collect field names now that DOM is ready
    fieldNames = Array.from(document.querySelectorAll('[data-pf-name]'))
      .map(function (el) { return el.dataset.pfName; });

    var addCgBtn = document.getElementById('pf-add-cg');
    if (addCgBtn) {
      addCgBtn.addEventListener('click', function () {
        var tpl = `
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

    var condForm = document.querySelector('#tab-conditions form');
    if (condForm) {
      condForm.addEventListener('submit', function () {
        var groups = [];
        document.querySelectorAll('.pf-cg').forEach(function (cg) {
          var rules = [];
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
  }

  // ── Webhook UI ─────────────────────────────────────────────────────────────

  document.addEventListener('click', function (e) {
    if (!e.target.closest('.pf-wh-save')) { return; }
    const panel  = e.target.closest('.pf-webhook-item, .pf-webhook-new');
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

    var params = new URLSearchParams();
    params.set('id_form', formId);
    params.set('webhooks_json', JSON.stringify([payload]));
    fetch(window.pfAdminUrl + '&action=save_webhooks', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: params.toString(),
    }).then(function () {
      location.reload();
    });
  });

  document.addEventListener('click', function (e) {
    if (!e.target.closest('.pf-wh-test')) { return; }
    const panel  = e.target.closest('.pf-webhook-item');
    const id     = panel ? panel.dataset.id : null;
    const result = panel ? panel.querySelector('.pf-wh-test-result') : null;

    if (!id || !result) { return; }
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

  // addWhBtn wired in initAll() — DOM not ready at IIFE time

  document.addEventListener('click', function (e) {
    if (!e.target.closest('.pf-wh-delete')) { return; }
    if (!confirm('Delete this webhook?')) { return; }
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
        !e.target.classList.contains('pf-wh-fields-all')) { return; }
    const panel   = e.target.closest('[class*="pf-webhook"]');
    const showSel = panel.querySelector('.pf-wh-fields-sel').checked;
    panel.querySelector('.pf-wh-field-checkboxes').style.display = showSel ? '' : 'none';
  });

  // ── Mail (2) enable/disable toggle ────────────────────────────────────────

  document.addEventListener('change', function (e) {
    if (!e.target.classList.contains('pf-mail-enabled')) { return; }
    var body = e.target.closest('.panel').querySelector('.pf-mail2-body');
    if (body) { body.style.display = e.target.checked ? '' : 'none'; }
  });

  // ── Mail routing rows / form serialiser / retention — wired in initAll() ──

  // ── Copy embed shortcode ───────────────────────────────────────────────────

  window.pfCopyEmbed = function () {
    const el = document.getElementById('pf-embed-code');
    if (!el) { return; }
    navigator.clipboard.writeText(el.value).then(function () {
      alert('Shortcode copied to clipboard.');
    });
  };

  // ── Vanilla JS tab switching (Bootstrap JS may not be present in PS9) ──────

  function initTabs() {
    document.querySelectorAll('[data-pf-tabs]').forEach(function (nav) {
      nav.querySelectorAll('a[href^="#"]').forEach(function (link) {
        link.addEventListener('click', function (e) {
          e.preventDefault();
          var targetId = link.getAttribute('href');
          var container = nav.closest('.panel') || document.body;

          // Deactivate all tabs and panes in this panel
          nav.querySelectorAll('li').forEach(function (li) { li.classList.remove('active'); });
          container.querySelectorAll('.tab-pane').forEach(function (pane) {
            pane.classList.remove('active', 'in');
          });

          // Activate the clicked tab and matching pane
          link.closest('li').classList.add('active');
          var pane = document.querySelector(targetId);
          if (pane) { pane.classList.add('active', 'in'); }
        });
      });
    });
  }

  // ── Initialise tag buttons (safe after DOM ready) ──────────────────────────

  function initWebhookUI() {
    var addWhBtn = document.getElementById('pf-add-webhook');
    if (addWhBtn) {
      addWhBtn.addEventListener('click', function () {
        var tplEl  = document.getElementById('pf-webhook-tpl');
        var newDiv = document.createElement('div');
        newDiv.className = 'panel panel-default pf-webhook-new';
        newDiv.innerHTML = '<div class="panel-heading">New Webhook</div>'
          + '<div class="panel-body">'
          + '<input type="hidden" class="pf-wh-id" value="0">'
          + tplEl.innerHTML
          + '</div>';
        document.getElementById('pf-webhook-list').prepend(newDiv);
      });
    }
  }

  function initMailUI() {
    // ── Add routing rule row ──────────────────────────────────────────────────
    var addRouteBtn = document.getElementById('pf-add-route');
    if (addRouteBtn) {
      addRouteBtn.addEventListener('click', function () {
        var fieldOpts = fieldNames.map(function (n) {
          return '<option value="' + n + '">' + n + '</option>';
        }).join('');
        document.getElementById('pf-routing-rows').insertAdjacentHTML('beforeend',
          '<tr class="pf-routing-row">'
          + '<td><select class="form-control pf-route-field">' + fieldOpts + '</select></td>'
          + '<td><input type="text" class="form-control pf-route-value" placeholder="value to match"></td>'
          + '<td><input type="text" class="form-control pf-route-email" placeholder="recipient@example.com"></td>'
          + '<td><button type="button" class="btn btn-danger btn-xs pf-remove-route"><i class="icon-trash"></i></button></td>'
          + '</tr>');
      });
    }

    // ── Serialise only routing rules → hidden JSON before submit ─────────────
    // All other mail fields now have real `name` attributes and are submitted
    // directly as POST params — no JS serialisation needed for those.
    var mailForm = document.querySelector('#tab-mail form');
    if (mailForm) {
      mailForm.addEventListener('submit', function () {
        var routing = [];
        document.querySelectorAll('.pf-routing-row').forEach(function (row) {
          routing.push({
            field: (row.querySelector('.pf-route-field') || {}).value || '',
            value: (row.querySelector('.pf-route-value') || {}).value || '',
            email: (row.querySelector('.pf-route-email') || {}).value || '',
          });
        });
        document.getElementById('mail_routing_json').value = JSON.stringify(routing);
      });
    }
  }

  function initSettingsUI() {
    // ── Retention custom-days input toggle ────────────────────────────────────
    var retentionSel = document.getElementById('retention-select');
    var retentionInput = document.getElementById('retention-custom-input');
    if (retentionSel && retentionInput) {
      function applyRetentionState() {
        var isCustom = retentionSel.value === 'custom';
        retentionInput.style.display = isCustom ? '' : 'none';
        retentionInput.disabled = !isCustom;
        retentionInput.name = isCustom ? 'retention_days' : 'retention_days_custom';
        retentionSel.name = isCustom ? '' : 'retention_days';
      }
      retentionSel.addEventListener('change', applyRetentionState);

      // On load: if the saved value is not one of the preset options, switch to
      // custom mode so the value isn't silently lost when the user saves.
      var presets = ['', '30', '90', '180', '365', 'custom'];
      if (retentionInput.value && !presets.includes(retentionSel.value)) {
        retentionSel.value = 'custom';
        applyRetentionState();
      } else {
        applyRetentionState();
      }
    }
  }

  // ── Tab memory — restore the active tab after a server-side save/redirect ──
  // After saving, the PHP handler appends &pf_tab=<id> to the redirect URL.
  // The controller passes that value to JS via Media::addJsDef as `pfActiveTab`.
  // On load we activate the matching pane so the user lands back where they were.
  function initTabMemory() {
    var pfTab = (typeof pfActiveTab !== 'undefined' && pfActiveTab) ? pfActiveTab : '';

    // Fallback: also check the URL query string directly
    if (!pfTab) {
      var params = new URLSearchParams(window.location.search);
      pfTab = params.get('pf_tab') || '';
    }

    if (pfTab) {
      var target = document.getElementById('tab-' + pfTab);
      var nav    = document.getElementById('pfFormTabs');
      if (target && nav) {
        nav.querySelectorAll('li').forEach(function (li) { li.classList.remove('active'); });
        document.querySelectorAll('.tab-pane').forEach(function (p) { p.classList.remove('active', 'in'); });
        target.classList.add('active', 'in');
        var link = nav.querySelector('a[href="#tab-' + pfTab + '"]');
        if (link) { link.closest('li').classList.add('active'); }
      }
    }
  }

  function initAll() {
    initTagButtons();
    initConditionsUI();
    initWebhookUI();
    initMailUI();
    initSettingsUI();
    initTabMemory();
    initTabs();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAll);
  } else {
    initAll();
  }

})();
