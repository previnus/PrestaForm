# PrestaForm Module — Design Specification

**Date:** 2026-04-15  
**Module name:** `prestaform`  
**Target platform:** PrestaShop 9  
**Architecture:** Layered module with service classes (Approach B)

---

## 1. Overview

PrestaForm is a PrestaShop 9 module that provides a CF7-style form builder. Admins create forms using a shortcode tag editor, embed them anywhere in the store via a widget shortcode, and configure per-form email routing, webhooks, and conditional field visibility. All submissions are stored in the database with a configurable retention policy.

---

## 2. Module Structure

```
prestaform/
├── prestaform.php                         # Module bootstrap, hook registration
├── config/
│   └── routes.php                         # Admin/front controller routes
├── controllers/
│   ├── admin/
│   │   ├── AdminPrestaFormFormsController.php
│   │   ├── AdminPrestaFormSubmissionsController.php
│   │   └── AdminPrestaFormSettingsController.php
│   └── front/
│       └── SubmitController.php           # Handles form POST submissions
├── src/
│   ├── Repository/
│   │   ├── FormRepository.php
│   │   └── SubmissionRepository.php
│   ├── Service/
│   │   ├── ShortcodeParser.php            # Tokenizes [tag* name param] syntax
│   │   ├── FormRenderer.php               # Renders field definitions to HTML
│   │   ├── ConditionEvaluator.php         # AND/OR visibility rule evaluation
│   │   ├── SubmissionService.php          # Validates + stores submissions
│   │   ├── WebhookDispatcher.php          # HTTP dispatch + retry queue
│   │   └── EmailRouter.php                # Conditional email routing
│   └── Entity/
│       ├── PrestaForm.php
│       ├── PrestaFormSubmission.php
│       ├── PrestaFormWebhook.php
│       ├── PrestaFormCondition.php
│       └── PrestaFormEmailRoute.php
├── views/
│   ├── templates/admin/                   # Smarty templates for back office
│   └── templates/front/                   # Form HTML, confirmation page
├── sql/
│   ├── install.sql
│   └── uninstall.sql
└── translations/
```

---

## 3. Database Schema

### `pf_forms`
| Column | Type | Notes |
|---|---|---|
| `id_form` | INT PK AUTO_INCREMENT | |
| `name` | VARCHAR(255) | Display name |
| `slug` | VARCHAR(255) UNIQUE | Used in `{prestaform name="..."}` |
| `template` | LONGTEXT | Raw shortcode template |
| `custom_css` | TEXT | Scoped to `#prestaform-{id}` on render |
| `success_message` | TEXT | Shown after successful submission |
| `status` | ENUM('active','draft') | Default: draft |
| `captcha_provider` | ENUM('none','recaptcha_v2','recaptcha_v3','turnstile') | |
| `retention_days` | INT NULL | NULL = forever |
| `date_add` | DATETIME | |
| `date_upd` | DATETIME | |

### `pf_submissions`
| Column | Type | Notes |
|---|---|---|
| `id_submission` | INT PK AUTO_INCREMENT | |
| `id_form` | INT FK | |
| `data` | JSON | All submitted field values |
| `ip_address` | VARCHAR(45) | IPv4/IPv6 |
| `date_add` | DATETIME | |

### `pf_webhooks`
| Column | Type | Notes |
|---|---|---|
| `id_webhook` | INT PK AUTO_INCREMENT | |
| `id_form` | INT FK | |
| `name` | VARCHAR(255) | |
| `url` | TEXT | |
| `method` | ENUM('POST','GET','PUT') | Default: POST |
| `headers` | JSON | Array of `{key, value}` pairs |
| `field_map` | JSON NULL | NULL = all fields; array of field names = selected only |
| `retry_count` | TINYINT | Default: 3 |
| `timeout_seconds` | TINYINT | Default: 10 |
| `active` | TINYINT(1) | Default: 1 |

### `pf_webhook_log`
| Column | Type | Notes |
|---|---|---|
| `id_log` | INT PK AUTO_INCREMENT | |
| `id_webhook` | INT FK | |
| `id_submission` | INT FK | |
| `attempt` | TINYINT | 1-based attempt number |
| `http_status` | SMALLINT NULL | NULL if network error |
| `response_body` | TEXT | Truncated to 2000 chars |
| `success` | TINYINT(1) | |
| `date_add` | DATETIME | |

### `pf_conditions`
| Column | Type | Notes |
|---|---|---|
| `id_condition_group` | INT PK AUTO_INCREMENT | |
| `id_form` | INT FK | |
| `target_field` | VARCHAR(255) | Field name to show/hide |
| `action` | ENUM('show','hide') | |
| `logic` | ENUM('AND','OR') | ALL vs ANY |
| `rules` | JSON | Array of `{field, operator, value}` |

### `pf_email_routes`
| Column | Type | Notes |
|---|---|---|
| `id_route` | INT PK AUTO_INCREMENT | |
| `id_form` | INT FK | |
| `type` | ENUM('admin','confirmation') | |
| `enabled` | TINYINT(1) | Default: 1 (confirmation type can be toggled off) |
| `notify_addresses` | JSON | Array of email strings (supports `[field]` vars) |
| `reply_to` | VARCHAR(255) NULL | Reply-to address; NULL = store default |
| `subject` | VARCHAR(500) | Supports template vars |
| `body` | LONGTEXT | Supports template vars |
| `routing_rules` | JSON NULL | Array of `{field, value, email}` conditional overrides; admin type only |

### `pf_settings`
| Column | Type | Notes |
|---|---|---|
| `setting_key` | VARCHAR(100) PK | |
| `setting_value` | TEXT | |

Stores global configuration: `recaptcha_v2_site_key`, `recaptcha_v2_secret_key`, `recaptcha_v3_site_key`, `recaptcha_v3_secret_key`, `turnstile_site_key`, `turnstile_secret_key`, `default_retention_days`.

---

## 4. Shortcode System

### Tag Syntax

```
[type* name param1 param2:value "string value"]
```

- `*` suffix on type = required field
- `name` = field slug (used as `name` attribute in HTML, submission key)
- Parameters are either bare keywords, `key:value` pairs, or quoted strings

### Supported Field Types

| Tag | HTML Output | Key Parameters |
|---|---|---|
| `[text]` | `<input type="text">` | `placeholder`, `maxlength`, `class`, `id` |
| `[email]` | `<input type="email">` | `placeholder` |
| `[tel]` | `<input type="tel">` | `placeholder` |
| `[number]` | `<input type="number">` | `min`, `max`, `step` |
| `[date]` | `<input type="date">` | `min`, `max` (`min:"today"` resolves to current date) |
| `[textarea]` | `<textarea>` | `rows`, `cols`, `placeholder` |
| `[select]` | `<select>` | `include_blank`; options as `"Label"` or `"Label\|value"` |
| `[checkbox]` | `<input type="checkbox">` | Single: label as string; Multi: `multiple` + list of options |
| `[radio]` | `<input type="radio">` group | Options as quoted strings |
| `[file]` | `<input type="file">` | `accept` (extensions), `limit` (e.g. `5mb`) |
| `[hidden]` | `<input type="hidden">` | `default:value` |
| `[recaptcha]` | CAPTCHA widget | Reads CAPTCHA provider from form settings |
| `[submit]` | `<button type="submit">` | Button label as quoted string |

### Widget Embed Shortcode

PS9 has no native shortcode processor. PrestaForm registers on content display hooks (`displayCMSPageContent`, `displayFooter`, `displayHome`, and optionally a custom `displayPrestaFormWidget` hook). Each hook callback receives the rendered HTML content, runs a regex match for `{prestaform ...}` patterns, resolves the form by `id` or `name`, renders it via `FormRenderer`, and replaces the matched token with the rendered HTML.

```
{prestaform id="3"}
{prestaform name="contact-form"}
{prestaform name="contact-form" title="Get in Touch"}
```

Both `id` and `name` are supported interchangeably. `title` overrides the form display name. If no form is found for the given id/name, the shortcode is silently removed (no error output on front-end).

---

## 5. Admin UI

### Forms List
Table showing: Name, Slug, Submission count, Status (Active/Draft), Edit/View actions.

### Form Editor — 5 Tabs

#### Form Builder Tab
- Left panel: tag type buttons (text, email, tel, textarea, select, checkbox, radio, file, hidden, number, date, recaptcha, submit)
- Clicking a tag type opens a **tag generator popup** — fields for all parameters with a live preview of the generated shortcode
- Right panel: full shortcode textarea editor
- Save Form + Preview buttons

#### Mail Tab
Two sub-tabs: **Admin Notifications** and **Submitter Confirmation**.

Admin Notifications:
- `notify_addresses` — textarea, one address per line, supports `[field-name]` variables
- `subject` — input with template variables: `[field-name]`, `[_form_title]`, `[_date]`, `[_ip]`
- `body` — textarea with same variables
- Conditional routing rules table: `if [field] equals [value] → send to [email]`

Submitter Confirmation:
- Toggle on/off
- Reply-to field (defaults to admin email)
- Subject + body with same variable support

#### Webhooks Tab
- List of webhooks for the form: name, URL, method, last dispatch status
- Add/Edit/Delete webhook
- Per-webhook config: name, URL, method (POST/GET/PUT), custom headers (key:value lines), field mapping (all or selected), retry count, timeout
- **Test Webhook** button — fires a test payload and shows HTTP response

#### Conditions Tab
- List of visibility rules, each rule: action (show/hide), target field, logic (ALL/ANY), list of conditions
- Each condition: source field + operator (equals, not equals, contains, is empty, is not empty) + value
- Rules evaluated client-side in JS on field change; re-evaluated server-side during submission validation

#### Settings Tab
- Form slug (editable)
- Status (Active/Draft)
- Success message
- Custom CSS (scoped automatically to `#prestaform-{id}`)
- CAPTCHA provider: None / Google reCAPTCHA v2 / Google reCAPTCHA v3 / Cloudflare Turnstile
- Submission retention: Forever / 30 / 90 / 180 days / 1 year / Custom (days input)
- Embed shortcode display with copy button

### Global Settings Page
- Google reCAPTCHA v2/v3 site key + secret key
- Cloudflare Turnstile site key + secret key
- Global submission retention default

### Submissions View
- Filter by form, free-text search, date range filter
- Table: ID, date, key fields (first 3 field values), Actions (View, Delete)
- Individual submission detail view: full field data, metadata (IP, date, form name)
- Export filtered results to CSV

---

## 6. Service Layer

### `ShortcodeParser`
Parses the raw template string into an array of field definition objects. Each object contains: type, name, required flag, and a key/value map of parameters. Handles quoted strings, bare keywords, and `key:value` pairs. Used at render time and for extracting the field list for conditions/mail/webhook UI.

### `FormRenderer`
Takes the parsed field definition array and renders it to an HTML form. Outputs `<div class="pf-field pf-field-{type}">` wrappers with `data-pf-name` attributes used by the JS condition evaluator. Injects the custom CSS block scoped to `#prestaform-{id}`.

### `ConditionEvaluator`
Takes the form's condition rules (from `pf_conditions`) and a data payload, evaluates each rule's AND/OR logic, and returns a set of fields to show/hide. Used both in PHP (server-side validation of hidden field submission) and compiled to a JS equivalent for live front-end behavior.

### `SubmissionService`
Validates a submitted payload against the parsed form definition (required fields, field types, file constraints, CAPTCHA token). Filters out values for fields that are hidden by condition rules. Saves the submission to `pf_submissions`. Triggers `WebhookDispatcher` and `EmailRouter` after a successful save.

### `WebhookDispatcher`
For each active webhook on a form, applies the field map filter, makes the HTTP request using PS9's HTTP client, logs the result to `pf_webhook_log`. On non-2xx or network error, schedules retry up to `retry_count` times with exponential backoff (using PS9's cron hook or a simple retry flag on the log row polled by a cron controller).

### `EmailRouter`
Resolves the final notify address list by evaluating conditional routing rules against submission data. Renders the subject and body templates by substituting `[field-name]` and system variables. Dispatches via PS9's `Mail::Send()`. Handles both admin notifications and submitter confirmation.

---

## 7. Front-End Behaviour

- Form rendered in a `<div id="prestaform-{id}" class="prestaform-wrapper">` container
- Scoped CSS injected in `<style>` block above the form
- AJAX submission to `SubmitController` — no page reload on success; replaces form with success message
- Condition rules compiled to inline JS data attributes; a small `prestaform.js` script evaluates visibility on every input change event
- File uploads handled via standard multipart form POST (not AJAX) if the form contains a `[file]` field — success message shown on redirect
- reCAPTCHA v2/v3 and Turnstile widgets loaded from their respective CDNs when the `[recaptcha]` tag is present

---

## 8. CAPTCHA Integration

CAPTCHA provider is configured globally (site key + secret) and selected per form. The `[recaptcha]` tag renders the appropriate widget based on the form's `captcha_provider` setting. Server-side token verification happens inside `SubmissionService` before saving.

| Provider | Widget script | Verification endpoint |
|---|---|---|
| Google reCAPTCHA v2 | `https://www.google.com/recaptcha/api.js` | `https://www.google.com/recaptcha/api/siteverify` |
| Google reCAPTCHA v3 | `https://www.google.com/recaptcha/api.js?render={sitekey}` | `https://www.google.com/recaptcha/api/siteverify` |
| Cloudflare Turnstile | `https://challenges.cloudflare.com/turnstile/v0/api.js` | `https://challenges.cloudflare.com/turnstile/v0/siteverify` |

---

## 9. Submission Retention

A PS9 cron hook (`actionCronJob` or similar) runs daily and deletes submissions from `pf_submissions` where `date_add < NOW() - INTERVAL {retention_days} DAY` for forms with a non-null `retention_days`. The per-form setting overrides the global default.

---

## 10. Error Handling

- Invalid or missing required fields: form re-rendered with inline error messages (no page reload via AJAX)
- CAPTCHA failure: submission rejected with a user-visible error message
- Webhook failure: logged to `pf_webhook_log`, retried up to `retry_count` times; failure does not block the submission success response to the user
- Email failure: logged to PS9's error log; does not block submission success
- File upload errors (size/type): caught in `SubmissionService`, returned as field-level validation error

---

## 11. Out of Scope (v1)

- Multi-step / wizard forms
- Payment integration
- Form analytics / conversion tracking
- Import/export of form definitions
- Front-end form builder (drag-and-drop)
