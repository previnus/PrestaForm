# Condition Builder UI Improvements — Design Spec
**Date:** 2026-04-21
**Approach:** A — Inject field metadata, rebuild value inputs in JS

---

## Problem

The condition builder admin UI is functionally correct but has several UX gaps:

1. Operator labels are raw slugs (`not_equals`, `is_empty`) instead of human-friendly text
2. The value input is always visible even for `is_empty` / `is_not_empty` (which need no value)
3. For `select` / `radio` / `checkbox` fields, the value input is a free-text box — no way to pick from known options
4. Empty condition groups (groups with zero rules) silently save as no-ops
5. ~~"No conditions" message doesn't sync with JS state~~ — fixed as part of bug work

---

## Architecture

PHP injects `window.pfFieldMeta` (a `name → {type, options[]}` map) alongside the already-working `window.pfFieldNames`. JS uses `pfFieldMeta` for field-aware value inputs. No new DB schema, no new endpoints, no new template variables beyond what's needed.

`pfFieldMeta` follows the exact same injection pattern as `pfFieldNames`:  
`AdminPrestaFormFormsController::buildFormHtml()` → `Media::addJsDef` + inline `<script>` in `edit.tpl`.

---

## Components

### 1. PHP — field metadata injection (`AdminPrestaFormFormsController.php`)

After `$fields = $parser->parse(...)`, build a name-keyed map and inject alongside `pfFieldNames`:

```php
$fieldMeta = [];
foreach ($fields as $f) {
    if ($f['name'] === '') continue;
    $fieldMeta[$f['name']] = [
        'type'    => $f['type'],
        'options' => $f['options'],   // [{label, value}, ...]
    ];
}
// Via addJsDef and inline <script> in edit.tpl (same dual-injection as pfFieldNames)
```

`pfFieldMeta` shape:
```js
window.pfFieldMeta = {
  "department": { type: "select",   options: [{label:"Sales",value:"sales"}, ...] },
  "agree":      { type: "checkbox", options: [{label:"I agree",value:"1"}] },
  "message":    { type: "textarea", options: [] }
}
```

---

### 2. JS — operator labels (`form-builder.js`)

Add a display-label map; update `makeOperatorSelect()` to use it:

| Stored value    | Displayed as  |
|-----------------|---------------|
| `equals`        | is            |
| `not_equals`    | is not        |
| `contains`      | contains      |
| `is_empty`      | is blank      |
| `is_not_empty`  | is not blank  |

Stored values (used in serialised JSON and front-end evaluator) are unchanged.

---

### 3. JS — value input factory (`form-builder.js`)

New `makeValueInput(fieldName, currentValue)`:
- Looks up `window.pfFieldMeta[fieldName]`
- If type is `select` / `radio` / `checkbox`: returns `<select class="pf-rule-value">` pre-populated with that field's options
- Otherwise: returns `<input type="text" class="pf-rule-value">`
- Always pre-selects / pre-fills `currentValue`

Used when:
- Adding a new rule row (via "+ Add condition" button)
- The field selector on an existing row changes

Existing rule rows loaded from DB keep their text inputs on first render; they upgrade to smart inputs if the user changes the field selector.

---

### 4. JS — value visibility toggle (`form-builder.js`)

New `syncValueVisibility(ruleEl)`:
- Reads the current `.pf-rule-operator` value
- Hides the value `col-sm-4` column (and disables the input to prevent POST) when operator is `is_empty` or `is_not_empty`
- Shows it otherwise

Called on:
- Operator `change` event (via delegation)
- Field `change` event (after rebuilding the value input)
- Page load — for all existing `.pf-rule` rows (to fix any saved `is_empty` rows that currently show a spurious text box)

---

### 5. JS — empty group filtering (`form-builder.js`)

In the conditions form `submit` handler, silently skip condition groups where `rules.length === 0`. Empty groups are no-ops and should not persist to the DB.

---

## Files changed

| File | Change |
|------|--------|
| `controllers/admin/AdminPrestaFormFormsController.php` | Build `$fieldMeta`, inject via `addJsDef` + Smarty |
| `views/templates/admin/forms/edit.tpl` | Add inline `<script>window.pfFieldMeta = ...` |
| `views/js/admin/form-builder.js` | Operator labels, `makeValueInput()`, `syncValueVisibility()`, empty-group filter, field-change handler |

No DB changes. No front-end (`prestaform.js`) changes — the evaluator already works correctly.
