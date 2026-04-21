# PrestaForm

A CF7-style form builder module for PrestaShop 9. Create custom forms with a drag-and-drop field editor, conditional logic, CAPTCHA support, email notifications, and a full submissions manager — all from the PrestaShop admin.

## Features

- **Visual form builder** — drag-and-drop shortcode editor for text, email, textarea, select, radio, checkbox, file upload, and CAPTCHA fields
- **Conditional logic** — show/hide fields based on other field values, with AND/OR rule groups
- **Email routing** — send confirmation emails to submitters and notification emails to admins, with configurable CC/BCC
- **CAPTCHA support** — reCAPTCHA v2, reCAPTCHA v3, and Cloudflare Turnstile
- **Submissions manager** — paginated list with preview, detail view with friendly field labels, and JSON export
- **Form export/import** — export forms as JSON and re-import on another shop

## Requirements

- PrestaShop 8.0 or higher (tested on PS9)
- PHP 8.0+
- MySQL 5.7+ / MariaDB 10.3+

## Installation

1. Download the latest release ZIP (`prestaform.zip`)
2. Go to **Modules > Module Manager** in your PrestaShop admin
3. Click **Upload a module** and select the ZIP
4. Click **Install**

The module creates four database tables on install:
- `pf_forms` — form definitions (name, shortcode template, settings)
- `pf_submissions` — submitted form data (JSON payload)
- `pf_email_routes` — email routing rules per form
- `pf_settings` — global module settings (CAPTCHA keys, etc.)

## Usage

### Creating a form

1. Go to **PrestaForm > Forms** in the admin sidebar
2. Click **Add new form**
3. Build your form using the **Form Builder** tab — drag fields from the palette, configure labels, placeholders, and validation
4. Configure email routing in the **Email Routing** tab
5. Add conditional logic in the **Conditions** tab
6. Save the form and copy the generated shortcode

### Embedding a form

Paste the shortcode anywhere in a CMS page, category description, or custom HTML block:

```
[prestaform id="1"]
```

### CAPTCHA setup

Go to **PrestaForm > Settings** and enter your API keys:

- **reCAPTCHA v2/v3** — get keys at [google.com/recaptcha](https://www.google.com/recaptcha)
- **Cloudflare Turnstile** — get keys at [dash.cloudflare.com](https://dash.cloudflare.com/?to=/:account/turnstile)

Per-form CAPTCHA provider is set in the form's **Settings** tab.

## Shortcode field syntax

The form builder generates shortcodes automatically, but you can also write them manually:

```
[text* name "Placeholder" class:my-class]
[email* email "Your email"]
[textarea message "Message" rows:5]
[select department "Department" "Sales" "Marketing" "Support"]
[radio color "Color" "Red" "Blue" "Green"]
[checkbox agree "I agree to the terms"]
[file attachment "Attach a file" accept:pdf,doc maxsize:5]
[recaptcha]
[submit "Send Message"]
```

Field modifiers: `*` makes a field required. Named options like `class:`, `rows:`, `accept:`, `maxsize:` are supported per field type.

## Development

```bash
# Clone into your PrestaShop modules directory
git clone https://github.com/previnus/PrestaForm.git prestaform

# Run tests
composer install --dev
./vendor/bin/phpunit
```

### Building a release ZIP

```bash
bash tools/build.sh
```

This produces `prestaform.zip` in the project root, ready for upload to PrestaShop.

## License

MIT
