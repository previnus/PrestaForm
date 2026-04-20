/**
 * PrestaForm front-end JS
 * - Evaluates condition rules and shows/hides fields
 * - Handles AJAX form submission
 * - Handles file-upload forms (multipart, non-AJAX)
 */
(function () {
  'use strict';

  function evalRule(form, rule) {
    // Checkbox groups use name="field[]" and can have multiple checked values
    const groupEls = form.querySelectorAll('[name="' + rule.field + '[]"]');
    if (groupEls.length > 0) {
      const checked = Array.prototype.filter.call(groupEls, function (cb) { return cb.checked; })
                           .map(function (cb) { return cb.value; });
      switch (rule.operator) {
        case 'equals':       return checked.indexOf(rule.value) !== -1;
        case 'not_equals':   return checked.indexOf(rule.value) === -1;
        case 'contains':     return checked.some(function (v) { return v.indexOf(rule.value) !== -1; });
        case 'is_empty':     return checked.length === 0;
        case 'is_not_empty': return checked.length > 0;
        default:             return false;
      }
    }

    const el = form.querySelector('[name="' + rule.field + '"]');
    const actual = el ? (el.type === 'checkbox' ? (el.checked ? el.value : '') : el.value) : '';

    switch (rule.operator) {
      case 'equals':       return actual === rule.value;
      case 'not_equals':   return actual !== rule.value;
      case 'contains':     return actual.indexOf(rule.value) !== -1;
      case 'is_empty':     return actual === '';
      case 'is_not_empty': return actual !== '';
      default:             return false;
    }
  }

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

      target.querySelectorAll('[required]').forEach(function (el) {
        el.disabled = !shouldShow;
      });
    });
  }

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

  function handleAjaxSubmit(wrapper, form, e) {
    e.preventDefault();
    clearErrors(wrapper);

    const submitBtn = form.querySelector('.pf-submit');
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.dataset.origText = submitBtn.textContent;
      submitBtn.textContent = 'Sending\u2026';
    }

    fetch(form.action, {
      method: 'POST',
      body: new FormData(form),
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
    })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        if (data.success) {
          const msg = document.createElement('div');
          msg.className = 'pf-success-message';
          msg.textContent = wrapper.dataset.successMessage ||
            'Thank you! Your message has been sent.';
          wrapper.innerHTML = '';
          wrapper.appendChild(msg);
        } else {
          if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = submitBtn.dataset.origText || 'Submit';
          }

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

  function initForms() {
    document.querySelectorAll('.prestaform-wrapper').forEach(function (wrapper) {
      const form = wrapper.querySelector('form');
      if (!form) return;

      applyConditions(wrapper);

      form.addEventListener('input',  function () { applyConditions(wrapper); });
      form.addEventListener('change', function () { applyConditions(wrapper); });

      const hasFile = form.querySelector('input[type="file"]');
      if (!hasFile) {
        form.addEventListener('submit', function (e) {
          handleAjaxSubmit(wrapper, form, e);
        });
      }

      const params = new URLSearchParams(window.location.search);
      if (params.get('pf_success') === '1') {
        const msg = document.createElement('div');
        msg.className = 'pf-success-message';
        msg.textContent = wrapper.dataset.successMessage || 'Thank you! Your message has been sent.';
        wrapper.innerHTML = '';
        wrapper.appendChild(msg);
      } else if (params.get('pf_error') === '1') {
        // File-upload form failed validation — show a generic error at the top.
        const div = document.createElement('div');
        div.className = 'pf-global-error';
        div.textContent = 'There was a problem submitting the form. Please check your input and try again.';
        form.prepend(div);
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initForms);
  } else {
    initForms();
  }
})();
