# Condition Builder UI Improvements — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Improve the condition builder admin UI with friendly operator labels, smart value inputs (dropdowns for select/radio/checkbox fields), hidden value column for blank-check operators, and silent filtering of empty groups on save.

**Architecture:** PHP injects `window.pfFieldMeta` (name → {type, options[]}) via `addJsDef` + inline `<script>` in `edit.tpl`. JS reads this map to render context-aware value inputs and toggle visibility. No DB changes, no new endpoints, no front-end (`prestaform.js`) changes.

**Tech Stack:** PHP 8, PrestaShop 9, Smarty 3, vanilla JS (ES5-compatible), Bootstrap 3 grid

---

## File Map

| File | What changes |
|------|-------------|
| `controllers/admin/AdminPrestaFormFormsController.php` | Build `$fieldMeta` array, inject via `addJsDef` + Smarty variable |
| `views/templates/admin/forms/edit.tpl` | Add `<script>window.pfFieldMeta = ...</script>` inline; add `pf-rule-value-col` class to value column in existing rule rows |
| `views/js/admin/form-builder.js` | Operator label map, `escHtml()`, `makeValueInput()`, `syncValueVisibility()`, update `.pf-add-rule` template, delegated change handler, page-load upgrade call |

---

## Task 1: Inject `pfFieldMeta` from PHP + template

**Files:**
- Modify: `controllers/admin/AdminPrestaFormFormsController.php`
- Modify: `views/templates/admin/forms/edit.tpl`

- [ ] **Step 1: Add `$fieldMeta` build + injection in `buildFormHtml()`**

In `AdminPrestaFormFormsController.php`, directly after the existing `$fieldNames` block (around line 117):

```php
// Field metadata for condition builder (type + options per field)
$fieldMeta = [];
foreach ($fields as $f) {
    if ($f['name'] === '') {
        continue;
    }
    $fieldMeta[$f['name']] = [
        'type'    => $f['type'],
        'options' => $f['options'],
    ];
}
\Media::addJsDef(['pfFieldMeta' => $fieldMeta]);
```

- [ ] **Step 2: Pass `field_meta_json` to Smarty**

In the same `buildFormHtml()`, inside the `$this->context->smarty->assign([...])` block, add after `'field_names_json'`:

```php
'field_meta_json' => json_encode($fieldMeta, JSON_UNESCAPED_UNICODE),
```

- [ ] **Step 3: Output inline script in `edit.tpl`**

In `edit.tpl`, directly below the existing `window.pfFieldNames` script tag (which reads `{$field_names_json|default:'[]'}`), add:

```smarty
<script>window.pfFieldMeta = {$field_meta_json|default:'{}'};</script>
```

- [ ] **Step 4: Verify injection manually**

Open any form edit page in the browser. Open DevTools Console and run:
```js
console.log(window.pfFieldMeta)
```
Expected: an object like `{ "your-name": { type: "text", options: [] }, "department": { type: "select", options: [{label:"Sales",value:"sales"}, ...] } }`.

If the result is `{}` or `undefined`, check that the form template has saved fields (save the Form Builder tab first).

- [ ] **Step 5: Commit**

```bash
git add controllers/admin/AdminPrestaFormFormsController.php views/templates/admin/forms/edit.tpl
git commit -m "feat: inject pfFieldMeta for condition builder field-aware inputs"
```

---

## Task 2: Operator labels

**Files:**
- Modify: `views/js/admin/form-builder.js`

- [ ] **Step 1: Add `OPERATOR_LABELS` map and update `makeOperatorSelect()`**

In `form-builder.js`, replace the existing `const OPERATORS` declaration and `makeOperatorSelect()` function (around line 207):

```js
const OPERATORS = ['equals', 'not_equals', 'contains', 'is_empty', 'is_not_empty'];

const OPERATOR_LABELS = {
  equals:       'is',
  not_equals:   'is not',
  contains:     'contains',
  is_empty:     'is blank',
  is_not_empty: 'is not blank',
};

function makeOperatorSelect(selected) {
  var opts = OPERATORS.map(function (op) {
    return '<option value="' + op + '"' + (op === selected ? ' selected' : '') + '>'
      + (OPERATOR_LABELS[op] || op) + '</option>';
  }).join('');
  return '<select class="form-control pf-rule-operator">' + opts + '</select>';
}
```

- [ ] **Step 2: Verify manually**

Go to a form's Conditions tab → click "+ Add Rule" → click "+ Add condition". The operator dropdown should show: **is / is not / contains / is blank / is not blank** instead of raw slugs.

Existing saved condition rows (rendered from PHP) still show raw slugs — that is expected and acceptable for now (they only affect new rules going forward).

- [ ] **Step 3: Commit**

```bash
git add views/js/admin/form-builder.js
git commit -m "feat: human-friendly operator labels in condition builder"
```

---

## Task 3: `escHtml()` helper + `makeValueInput()`

**Files:**
- Modify: `views/js/admin/form-builder.js`

- [ ] **Step 1: Add `escHtml()` helper**

Add this function immediately before `makeFieldSelect()` (around line 209):

```js
function escHtml(str) {
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/"/g, '&quot;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');
}
```

- [ ] **Step 2: Add `makeValueInput()`**

Add this function immediately after `makeOperatorSelect()`:

```js
function makeValueInput(fieldName, currentValue) {
  var meta = (window.pfFieldMeta && window.pfFieldMeta[fieldName]) || null;
  var val  = currentValue != null ? String(currentValue) : '';
  if (meta && ['select', 'radio', 'checkbox'].indexOf(meta.type) !== -1) {
    var opts = (meta.options || []).map(function (o) {
      return '<option value="' + escHtml(o.value) + '"'
        + (o.value === val ? ' selected' : '') + '>'
        + escHtml(o.label) + '</option>';
    }).join('');
    return '<select class="form-control pf-rule-value">' + opts + '</select>';
  }
  return '<input type="text" class="form-control pf-rule-value" placeholder="value" value="' + escHtml(val) + '">';
}
```

- [ ] **Step 3: Add `pf-rule-value-col` class to the value column in the JS rule template**

In the delegated `.pf-add-rule` click handler (inside the `insertAdjacentHTML` template literal), replace the value column:

```js
// OLD:
// <div class="col-sm-4"><input type="text" class="form-control pf-rule-value" placeholder="value"></div>
// NEW:
`<div class="col-sm-4 pf-rule-value-col">${makeValueInput(
  e.target.closest('.pf-cg').querySelector('.pf-cg-target')
    ? e.target.closest('.pf-cg').querySelector('.pf-cg-target').value
    : (fieldNames[0] || ''),
  '')}</div>`
```

The full updated handler block (replace the existing `.pf-add-rule` block inside the delegated click listener):

```js
if (e.target.closest('.pf-add-rule')) {
  var cg             = e.target.closest('.pf-cg');
  var targetField    = cg && cg.querySelector('.pf-cg-target')
    ? cg.querySelector('.pf-cg-target').value
    : (fieldNames[0] || '');
  var rulesContainer = cg.querySelector('.pf-cg-rules');
  rulesContainer.insertAdjacentHTML('beforeend',
    '<div class="pf-rule row" style="margin-bottom:8px">'
    + '<div class="col-sm-4">' + makeFieldSelect('pf-rule-field', targetField) + '</div>'
    + '<div class="col-sm-3">' + makeOperatorSelect('equals') + '</div>'
    + '<div class="col-sm-4 pf-rule-value-col">' + makeValueInput(targetField, '') + '</div>'
    + '<div class="col-sm-1"><button type="button" class="btn btn-danger btn-xs pf-remove-rule">'
    + '<i class="icon-trash"></i></button></div>'
    + '</div>');
}
```

- [ ] **Step 4: Add `pf-rule-value-col` class to existing PHP-rendered rule rows in `edit.tpl`**

In `edit.tpl`, inside the conditions tab, find the value column div in the existing rule loop (the `<div class="col-sm-4">` that wraps the `pf-rule-value` input):

```smarty
{* OLD: *}
<div class="col-sm-4">
  <input type="text" class="form-control pf-rule-value" value="{$rule.value|default:''|escape}" placeholder="value to match">
</div>

{* NEW: *}
<div class="col-sm-4 pf-rule-value-col">
  <input type="text" class="form-control pf-rule-value" value="{$rule.value|default:''|escape}" placeholder="value to match">
</div>
```

- [ ] **Step 5: Verify manually**

Go to a form that has a `select` field (e.g., a field defined as `[select department "Sales" "Marketing" "Support"]`).

1. Conditions tab → "+ Add Rule" → "+ Add condition"
2. In the rule row, change the field selector to `department`
3. The value input should switch to a `<select>` dropdown with "Sales / Marketing / Support"
4. Change the field to a `text` field — should revert to a text input

- [ ] **Step 6: Commit**

```bash
git add views/js/admin/form-builder.js views/templates/admin/forms/edit.tpl
git commit -m "feat: field-aware value inputs in condition builder"
```

---

## Task 4: `syncValueVisibility()` — hide value column for blank-check operators

**Files:**
- Modify: `views/js/admin/form-builder.js`

- [ ] **Step 1: Add `syncValueVisibility()` function**

Add this function immediately after `makeValueInput()`:

```js
function syncValueVisibility(ruleEl) {
  var op  = ruleEl.querySelector('.pf-rule-operator');
  var col = ruleEl.querySelector('.pf-rule-value-col');
  if (!op || !col) { return; }
  var noValue = op.value === 'is_empty' || op.value === 'is_not_empty';
  col.style.display = noValue ? 'none' : '';
  var inp = col.querySelector('.pf-rule-value');
  if (inp) { inp.disabled = noValue; }
}
```

- [ ] **Step 2: Add delegated `change` handler for operator + field changes**

Add a new `document.addEventListener('change', ...)` block alongside the existing change listeners (near the webhook/mail change handlers):

```js
document.addEventListener('change', function (e) {
  var ruleEl = e.target.closest('.pf-rule');
  if (!ruleEl) { return; }

  // Operator changed → toggle value column visibility
  if (e.target.classList.contains('pf-rule-operator')) {
    syncValueVisibility(ruleEl);
  }

  // Field selector changed → rebuild value input to match new field type, then sync
  if (e.target.classList.contains('pf-rule-field')) {
    var col = ruleEl.querySelector('.pf-rule-value-col');
    if (col) {
      col.innerHTML = makeValueInput(e.target.value, '');
    }
    syncValueVisibility(ruleEl);
  }
});
```

- [ ] **Step 3: Call `syncValueVisibility` on page load for all existing rows**

At the end of `initConditionsUI()`, after the `condForm` submit handler block, add:

```js
// Upgrade existing rule rows: hide value column for any saved is_empty / is_not_empty rules
document.querySelectorAll('.pf-rule').forEach(syncValueVisibility);
```

- [ ] **Step 4: Verify manually**

1. Go to Conditions tab, add a rule, add a condition row.
2. Change operator to "is blank" → the value input column should disappear.
3. Change operator to "is" → value input reappears.
4. If any existing saved conditions use `is_empty` / `is_not_empty`, reload the page — their value columns should be hidden on load.

- [ ] **Step 5: Commit**

```bash
git add views/js/admin/form-builder.js
git commit -m "feat: hide value input for is_empty/is_not_empty operators"
```

---

## Task 5: Empty group filtering on save

**Files:**
- Modify: `views/js/admin/form-builder.js`

- [ ] **Step 1: Filter out empty groups in the conditions form submit handler**

In `initConditionsUI()`, inside the `condForm.addEventListener('submit', ...)` handler, update the serialiser to skip groups with zero rules.

Replace:

```js
groups.push({
  target_field: cg.querySelector('.pf-cg-target').value,
  action:       cg.querySelector('.pf-cg-action').value,
  logic:        cg.querySelector('.pf-cg-logic').value,
  rules:        rules,
});
```

With:

```js
if (rules.length > 0) {
  groups.push({
    target_field: cg.querySelector('.pf-cg-target').value,
    action:       cg.querySelector('.pf-cg-action').value,
    logic:        cg.querySelector('.pf-cg-logic').value,
    rules:        rules,
  });
}
```

- [ ] **Step 2: Verify manually**

1. Go to Conditions tab → "+ Add Rule" (creates an empty group with no condition rows).
2. Click "Save Conditions" without adding any condition rows to the group.
3. After page reload, the empty group should not reappear (it was silently dropped).

- [ ] **Step 3: Commit**

```bash
git add views/js/admin/form-builder.js
git commit -m "feat: silently drop empty condition groups on save"
```

---

## Self-Review

**Spec coverage check:**
- ✅ PHP injects `pfFieldMeta` → Task 1
- ✅ Operator labels (`is`, `is not`, `is blank`, `is not blank`) → Task 2
- ✅ `makeValueInput()` — smart select for select/radio/checkbox → Task 3
- ✅ `pf-rule-value-col` class added to both JS template and Smarty template → Task 3 steps 3 & 4
- ✅ `syncValueVisibility()` + operator change handler → Task 4
- ✅ Field change handler → Task 4 step 2
- ✅ Page-load upgrade of existing rows → Task 4 step 3
- ✅ Empty group filtering → Task 5

**Placeholder scan:** No TBDs, no "similar to Task N" patterns, no vague steps.

**Type consistency:**
- `makeValueInput(fieldName, currentValue)` — defined Task 3, called in Task 3 and Task 4 ✅
- `syncValueVisibility(ruleEl)` — defined Task 4, called in Task 4 ✅
- `escHtml(str)` — defined Task 3 step 1, used in Task 3 step 2 ✅
- `pf-rule-value-col` — added Task 3 steps 3 & 4, targeted in Task 4 step 1 ✅
- `OPERATOR_LABELS` — defined Task 2, used in same task ✅
