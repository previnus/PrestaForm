# PrestaForm Module Implementation Plan — Part 1: Foundation & Services

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the foundation of the `prestaform` PS9 module — scaffold, SQL schema, module bootstrap, entity classes, and all pure-PHP service classes (ShortcodeParser, FormRenderer, ConditionEvaluator, EmailRouter) with full unit test coverage.

**Architecture:** Layered PS9 module. Service classes under `src/Service/` have zero PS9 dependencies and are fully unit-testable with PHPUnit. Entity classes extend PS9's `ObjectModel`. Repositories use `Db::getInstance()`.

**Tech Stack:** PHP 8.1+, PrestaShop 9, PHPUnit 10, PSR-4 autoloading via Composer.

---

### Task 1: Composer + PHPUnit Scaffold

**Files:**
- Create: `composer.json`
- Create: `phpunit.xml`
- Create: `tests/bootstrap.php`
- Create: `tests/Unit/.gitkeep`

- [ ] **Step 1: Create composer.json**

```json
{
    "name": "prestaform/prestaform",
    "description": "CF7-style form builder for PrestaShop 9",
    "type": "prestashop-module",
    "require": {
        "php": ">=8.1"
    },
    "require-dev": {
        "phpunit/phpunit": "^10.5"
    },
    "autoload": {
        "psr-4": {
            "PrestaForm\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "PrestaForm\\Tests\\": "tests/"
        }
    },
    "config": {
        "optimize-autoloader": true
    }
}
```

- [ ] **Step 2: Create phpunit.xml**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="tests/bootstrap.php"
         colors="true"
         stopOnFailure="false">
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

- [ ] **Step 3: Create tests/bootstrap.php**

```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
```

- [ ] **Step 4: Install dependencies**

```bash
cd /path/to/prestaform
composer install
```

Expected: `vendor/` directory created, `vendor/bin/phpunit` exists.

- [ ] **Step 5: Verify PHPUnit works**

```bash
./vendor/bin/phpunit --version
```

Expected output: `PHPUnit 10.x.x`

- [ ] **Step 6: Commit**

```bash
git init
git add composer.json phpunit.xml tests/bootstrap.php
git commit -m "feat: scaffold composer autoloading and PHPUnit"
```

---

### Task 2: SQL Schema

**Files:**
- Create: `sql/install.sql`
- Create: `sql/uninstall.sql`

- [ ] **Step 1: Create sql/install.sql**

```sql
CREATE TABLE IF NOT EXISTS `PREFIX_pf_forms` (
    `id_form`          INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`             VARCHAR(255) NOT NULL,
    `slug`             VARCHAR(255) NOT NULL,
    `template`         LONGTEXT NOT NULL,
    `custom_css`       TEXT NOT NULL DEFAULT '',
    `success_message`  TEXT NOT NULL DEFAULT '',
    `status`           ENUM('active','draft') NOT NULL DEFAULT 'draft',
    `captcha_provider` ENUM('none','recaptcha_v2','recaptcha_v3','turnstile') NOT NULL DEFAULT 'none',
    `retention_days`   INT(11) UNSIGNED NULL DEFAULT NULL,
    `date_add`         DATETIME NOT NULL,
    `date_upd`         DATETIME NOT NULL,
    PRIMARY KEY (`id_form`),
    UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `PREFIX_pf_submissions` (
    `id_submission` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_form`       INT(11) UNSIGNED NOT NULL,
    `data`          JSON NOT NULL,
    `ip_address`    VARCHAR(45) NOT NULL DEFAULT '',
    `date_add`      DATETIME NOT NULL,
    PRIMARY KEY (`id_submission`),
    KEY `id_form` (`id_form`),
    KEY `date_add` (`date_add`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `PREFIX_pf_webhooks` (
    `id_webhook`       INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_form`          INT(11) UNSIGNED NOT NULL,
    `name`             VARCHAR(255) NOT NULL,
    `url`              TEXT NOT NULL,
    `method`           ENUM('POST','GET','PUT') NOT NULL DEFAULT 'POST',
    `headers`          JSON NOT NULL,
    `field_map`        JSON NULL DEFAULT NULL,
    `retry_count`      TINYINT(3) UNSIGNED NOT NULL DEFAULT 3,
    `timeout_seconds`  TINYINT(3) UNSIGNED NOT NULL DEFAULT 10,
    `active`           TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id_webhook`),
    KEY `id_form` (`id_form`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `PREFIX_pf_webhook_log` (
    `id_log`        INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_webhook`    INT(11) UNSIGNED NOT NULL,
    `id_submission` INT(11) UNSIGNED NOT NULL,
    `attempt`       TINYINT(3) UNSIGNED NOT NULL DEFAULT 1,
    `http_status`   SMALLINT(5) NULL DEFAULT NULL,
    `response_body` TEXT NULL DEFAULT NULL,
    `success`       TINYINT(1) NOT NULL DEFAULT 0,
    `date_add`      DATETIME NOT NULL,
    PRIMARY KEY (`id_log`),
    KEY `id_webhook` (`id_webhook`),
    KEY `id_submission` (`id_submission`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `PREFIX_pf_conditions` (
    `id_condition_group` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_form`            INT(11) UNSIGNED NOT NULL,
    `target_field`       VARCHAR(255) NOT NULL,
    `action`             ENUM('show','hide') NOT NULL DEFAULT 'show',
    `logic`              ENUM('AND','OR') NOT NULL DEFAULT 'AND',
    `rules`              JSON NOT NULL,
    PRIMARY KEY (`id_condition_group`),
    KEY `id_form` (`id_form`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `PREFIX_pf_email_routes` (
    `id_route`          INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_form`           INT(11) UNSIGNED NOT NULL,
    `type`              ENUM('admin','confirmation') NOT NULL,
    `enabled`           TINYINT(1) NOT NULL DEFAULT 1,
    `notify_addresses`  JSON NOT NULL,
    `reply_to`          VARCHAR(255) NULL DEFAULT NULL,
    `subject`           VARCHAR(500) NOT NULL DEFAULT '',
    `body`              LONGTEXT NOT NULL,
    `routing_rules`     JSON NULL DEFAULT NULL,
    PRIMARY KEY (`id_route`),
    KEY `id_form` (`id_form`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `PREFIX_pf_settings` (
    `setting_key`   VARCHAR(100) NOT NULL,
    `setting_value` TEXT NOT NULL DEFAULT '',
    PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `PREFIX_pf_settings` (`setting_key`, `setting_value`) VALUES
    ('recaptcha_v2_site_key', ''),
    ('recaptcha_v2_secret_key', ''),
    ('recaptcha_v3_site_key', ''),
    ('recaptcha_v3_secret_key', ''),
    ('turnstile_site_key', ''),
    ('turnstile_secret_key', ''),
    ('default_retention_days', '');
```

- [ ] **Step 2: Create sql/uninstall.sql**

```sql
DROP TABLE IF EXISTS `PREFIX_pf_settings`;
DROP TABLE IF EXISTS `PREFIX_pf_email_routes`;
DROP TABLE IF EXISTS `PREFIX_pf_conditions`;
DROP TABLE IF EXISTS `PREFIX_pf_webhook_log`;
DROP TABLE IF EXISTS `PREFIX_pf_webhooks`;
DROP TABLE IF EXISTS `PREFIX_pf_submissions`;
DROP TABLE IF EXISTS `PREFIX_pf_forms`;
```

- [ ] **Step 3: Commit**

```bash
git add sql/
git commit -m "feat: add SQL install/uninstall schema"
```

---

### Task 3: Module Bootstrap

**Files:**
- Create: `prestaform.php`

- [ ] **Step 1: Create prestaform.php**

```php
<?php
declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

class Prestaform extends Module
{
    public function __construct()
    {
        $this->name = 'prestaform';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'PrestaForm';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('PrestaForm');
        $this->description = $this->l('CF7-style form builder for PrestaShop 9');
        $this->ps_versions_compliancy = ['min' => '8.0.0', 'max' => _PS_VERSION_];
    }

    public function install(): bool
    {
        return parent::install()
            && $this->installDb()
            && $this->installTabs()
            && $this->registerHook('displayHeader')
            && $this->registerHook('displayHome')
            && $this->registerHook('displayCMSPageContent')
            && $this->registerHook('displayFooterBefore')
            && $this->registerHook('actionCronJob');
    }

    public function uninstall(): bool
    {
        return $this->uninstallDb()
            && $this->uninstallTabs()
            && parent::uninstall();
    }

    private function installDb(): bool
    {
        $sql = file_get_contents(__DIR__ . '/sql/install.sql');
        $sql = str_replace('PREFIX_', _DB_PREFIX_, $sql);
        // Split on semicolons to run multiple statements
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $query) {
            if (!Db::getInstance()->execute($query)) {
                return false;
            }
        }
        return true;
    }

    private function uninstallDb(): bool
    {
        $sql = file_get_contents(__DIR__ . '/sql/uninstall.sql');
        $sql = str_replace('PREFIX_', _DB_PREFIX_, $sql);
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $query) {
            Db::getInstance()->execute($query);
        }
        return true;
    }

    private function installTabs(): bool
    {
        $langs = Language::getLanguages(false);

        // Parent tab
        $parent = new Tab();
        $parent->class_name = 'AdminPrestaFormMain';
        $parent->module = $this->name;
        $parent->id_parent = 0;
        $parent->icon = 'library_books';
        foreach ($langs as $lang) {
            $parent->name[$lang['id_lang']] = 'PrestaForm';
        }
        if (!$parent->add()) {
            return false;
        }

        $children = [
            ['AdminPrestaFormForms',       'Forms'],
            ['AdminPrestaFormSubmissions', 'Submissions'],
            ['AdminPrestaFormSettings',    'Settings'],
        ];

        foreach ($children as [$class, $label]) {
            $tab = new Tab();
            $tab->class_name = $class;
            $tab->module = $this->name;
            $tab->id_parent = (int) $parent->id;
            foreach ($langs as $lang) {
                $tab->name[$lang['id_lang']] = $label;
            }
            if (!$tab->add()) {
                return false;
            }
        }

        return true;
    }

    private function uninstallTabs(): bool
    {
        $classes = [
            'AdminPrestaFormForms',
            'AdminPrestaFormSubmissions',
            'AdminPrestaFormSettings',
            'AdminPrestaFormMain',
        ];
        foreach ($classes as $class) {
            $id = Tab::getIdFromClassName($class);
            if ($id) {
                (new Tab($id))->delete();
            }
        }
        return true;
    }

    // ── Hooks ────────────────────────────────────────────────────────────────

    public function hookDisplayHeader(): void
    {
        // Register Smarty output filter to process {prestaform} shortcodes
        $this->context->smarty->registerFilter('output', [$this, 'processShortcodes']);
        $this->context->controller->addJS($this->_path . 'views/js/front/prestaform.js');
        $this->context->controller->addCSS($this->_path . 'views/css/prestaform.css');
    }

    /**
     * Smarty output filter: replace {prestaform id="X"} / {prestaform name="slug"}
     * tokens anywhere in the rendered page HTML.
     */
    public function processShortcodes(string $output, \Smarty_Internal_Template $template): string
    {
        return preg_replace_callback(
            '/\{prestaform\s+([^}]+)\}/i',
            function (array $m): string {
                $attrs = $this->parseShortcodeAttrs($m[1]);
                $formRepo = new \PrestaForm\Repository\FormRepository();

                if (!empty($attrs['id'])) {
                    $form = $formRepo->findById((int) $attrs['id']);
                } elseif (!empty($attrs['name'])) {
                    $form = $formRepo->findBySlug($attrs['name']);
                } else {
                    return '';
                }

                if (!$form || $form['status'] !== 'active') {
                    return '';
                }

                if (!empty($attrs['title'])) {
                    $form['name'] = $attrs['title'];
                }

                $condRepo  = new \PrestaForm\Repository\ConditionRepository();
                $renderer  = new \PrestaForm\Service\FormRenderer(new \PrestaForm\Service\ShortcodeParser());
                $actionUrl = $this->context->link->getModuleLink('prestaform', 'submit');
                $token     = \Tools::getToken(false);

                return $renderer->render($form, $actionUrl, $token, $condRepo->findByForm((int) $form['id_form']));
            },
            $output
        );
    }

    private function parseShortcodeAttrs(string $attrString): array
    {
        $attrs = [];
        preg_match_all('/(\w+)=["\']?([^"\'}\s]+)["\']?/', $attrString, $matches, PREG_SET_ORDER);
        foreach ($matches as $m) {
            $attrs[$m[1]] = $m[2];
        }
        return $attrs;
    }

    public function hookActionCronJob(): void
    {
        (new \PrestaForm\Service\RetentionService())->purgeExpired();
        (new \PrestaForm\Service\WebhookDispatcher())->retryPending();
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add prestaform.php
git commit -m "feat: add module bootstrap with install/uninstall and hooks"
```

---

### Task 4: Entity Classes

**Files:**
- Create: `src/Entity/PfForm.php`
- Create: `src/Entity/PfSubmission.php`
- Create: `src/Entity/PfWebhook.php`
- Create: `src/Entity/PfConditionGroup.php`
- Create: `src/Entity/PfEmailRoute.php`

- [ ] **Step 1: Create src/Entity/PfForm.php**

```php
<?php
declare(strict_types=1);

namespace PrestaForm\Entity;

class PfForm extends \ObjectModel
{
    public int    $id_form        = 0;
    public string $name           = '';
    public string $slug           = '';
    public string $template       = '';
    public string $custom_css     = '';
    public string $success_message = '';
    public string $status         = 'draft';
    public string $captcha_provider = 'none';
    public ?int   $retention_days  = null;
    public string $date_add       = '';
    public string $date_upd       = '';

    /** @var array<string, mixed> */
    public static $definition = [
        'table'   => 'pf_forms',
        'primary' => 'id_form',
        'fields'  => [
            'name'             => ['type' => self::TYPE_STRING,  'required' => true,  'size' => 255],
            'slug'             => ['type' => self::TYPE_STRING,  'required' => true,  'size' => 255],
            'template'         => ['type' => self::TYPE_HTML,    'required' => false],
            'custom_css'       => ['type' => self::TYPE_STRING,  'required' => false],
            'success_message'  => ['type' => self::TYPE_STRING,  'required' => false],
            'status'           => ['type' => self::TYPE_STRING,  'required' => false, 'size' => 10],
            'captcha_provider' => ['type' => self::TYPE_STRING,  'required' => false, 'size' => 20],
            'retention_days'   => ['type' => self::TYPE_INT,     'required' => false, 'allow_null' => true],
            'date_add'         => ['type' => self::TYPE_DATE,    'required' => false],
            'date_upd'         => ['type' => self::TYPE_DATE,    'required' => false],
        ],
    ];
}
```

- [ ] **Step 2: Create src/Entity/PfSubmission.php**

```php
<?php
declare(strict_types=1);

namespace PrestaForm\Entity;

class PfSubmission extends \ObjectModel
{
    public int    $id_submission = 0;
    public int    $id_form       = 0;
    public string $data          = '{}';
    public string $ip_address    = '';
    public string $date_add      = '';

    /** @var array<string, mixed> */
    public static $definition = [
        'table'   => 'pf_submissions',
        'primary' => 'id_submission',
        'fields'  => [
            'id_form'    => ['type' => self::TYPE_INT,    'required' => true],
            'data'       => ['type' => self::TYPE_STRING, 'required' => true],
            'ip_address' => ['type' => self::TYPE_STRING, 'required' => false, 'size' => 45],
            'date_add'   => ['type' => self::TYPE_DATE,   'required' => false],
        ],
    ];
}
```

- [ ] **Step 3: Create src/Entity/PfWebhook.php**

```php
<?php
declare(strict_types=1);

namespace PrestaForm\Entity;

class PfWebhook extends \ObjectModel
{
    public int    $id_webhook      = 0;
    public int    $id_form         = 0;
    public string $name            = '';
    public string $url             = '';
    public string $method          = 'POST';
    public string $headers         = '[]';
    public ?string $field_map      = null;
    public int    $retry_count     = 3;
    public int    $timeout_seconds = 10;
    public int    $active          = 1;

    /** @var array<string, mixed> */
    public static $definition = [
        'table'   => 'pf_webhooks',
        'primary' => 'id_webhook',
        'fields'  => [
            'id_form'         => ['type' => self::TYPE_INT,    'required' => true],
            'name'            => ['type' => self::TYPE_STRING, 'required' => true,  'size' => 255],
            'url'             => ['type' => self::TYPE_STRING, 'required' => true],
            'method'          => ['type' => self::TYPE_STRING, 'required' => false, 'size' => 6],
            'headers'         => ['type' => self::TYPE_STRING, 'required' => false],
            'field_map'       => ['type' => self::TYPE_STRING, 'required' => false, 'allow_null' => true],
            'retry_count'     => ['type' => self::TYPE_INT,    'required' => false],
            'timeout_seconds' => ['type' => self::TYPE_INT,    'required' => false],
            'active'          => ['type' => self::TYPE_INT,    'required' => false],
        ],
    ];
}
```

- [ ] **Step 4: Create src/Entity/PfConditionGroup.php**

```php
<?php
declare(strict_types=1);

namespace PrestaForm\Entity;

class PfConditionGroup extends \ObjectModel
{
    public int    $id_condition_group = 0;
    public int    $id_form            = 0;
    public string $target_field       = '';
    public string $action             = 'show';
    public string $logic              = 'AND';
    public string $rules              = '[]';

    /** @var array<string, mixed> */
    public static $definition = [
        'table'   => 'pf_conditions',
        'primary' => 'id_condition_group',
        'fields'  => [
            'id_form'      => ['type' => self::TYPE_INT,    'required' => true],
            'target_field' => ['type' => self::TYPE_STRING, 'required' => true, 'size' => 255],
            'action'       => ['type' => self::TYPE_STRING, 'required' => false, 'size' => 4],
            'logic'        => ['type' => self::TYPE_STRING, 'required' => false, 'size' => 3],
            'rules'        => ['type' => self::TYPE_STRING, 'required' => false],
        ],
    ];
}
```

- [ ] **Step 5: Create src/Entity/PfEmailRoute.php**

```php
<?php
declare(strict_types=1);

namespace PrestaForm\Entity;

class PfEmailRoute extends \ObjectModel
{
    public int    $id_route          = 0;
    public int    $id_form           = 0;
    public string $type              = 'admin';
    public int    $enabled           = 1;
    public string $notify_addresses  = '[]';
    public ?string $reply_to         = null;
    public string $subject           = '';
    public string $body              = '';
    public ?string $routing_rules    = null;

    /** @var array<string, mixed> */
    public static $definition = [
        'table'   => 'pf_email_routes',
        'primary' => 'id_route',
        'fields'  => [
            'id_form'          => ['type' => self::TYPE_INT,    'required' => true],
            'type'             => ['type' => self::TYPE_STRING, 'required' => true,  'size' => 12],
            'enabled'          => ['type' => self::TYPE_INT,    'required' => false],
            'notify_addresses' => ['type' => self::TYPE_STRING, 'required' => false],
            'reply_to'         => ['type' => self::TYPE_STRING, 'required' => false, 'allow_null' => true, 'size' => 255],
            'subject'          => ['type' => self::TYPE_STRING, 'required' => false, 'size' => 500],
            'body'             => ['type' => self::TYPE_HTML,   'required' => false],
            'routing_rules'    => ['type' => self::TYPE_STRING, 'required' => false, 'allow_null' => true],
        ],
    ];
}
```

- [ ] **Step 6: Commit**

```bash
git add src/Entity/
git commit -m "feat: add ObjectModel entity classes"
```

---

### Task 5: ShortcodeParser — TDD

**Files:**
- Create: `src/Service/ShortcodeParser.php`
- Create: `tests/Unit/Service/ShortcodeParserTest.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/Service/ShortcodeParserTest.php`:

```php
<?php
declare(strict_types=1);

namespace PrestaForm\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use PrestaForm\Service\ShortcodeParser;

class ShortcodeParserTest extends TestCase
{
    private ShortcodeParser $parser;

    protected function setUp(): void
    {
        $this->parser = new ShortcodeParser();
    }

    public function testParsesSimpleTextField(): void
    {
        $fields = $this->parser->parse('[text* your-name]');
        $this->assertCount(1, $fields);
        $this->assertSame('text', $fields[0]['type']);
        $this->assertTrue($fields[0]['required']);
        $this->assertSame('your-name', $fields[0]['name']);
    }

    public function testParsesOptionalField(): void
    {
        $fields = $this->parser->parse('[email your-email]');
        $this->assertCount(1, $fields);
        $this->assertFalse($fields[0]['required']);
        $this->assertSame('email', $fields[0]['type']);
    }

    public function testParsesKeyValueParam(): void
    {
        $fields = $this->parser->parse('[text* your-name maxlength:200]');
        $this->assertSame('200', $fields[0]['params']['maxlength']);
    }

    public function testParsesNamedParamWithQuotedValue(): void
    {
        $fields = $this->parser->parse('[text* your-name placeholder "Your full name"]');
        $this->assertSame('Your full name', $fields[0]['params']['placeholder']);
    }

    public function testParsesSelectWithOptions(): void
    {
        $fields = $this->parser->parse('[select department "Sales" "Support" "Billing"]');
        $this->assertSame('select', $fields[0]['type']);
        $this->assertSame('department', $fields[0]['name']);
        $this->assertCount(3, $fields[0]['options']);
        $this->assertSame('Sales',   $fields[0]['options'][0]['label']);
        $this->assertSame('Sales',   $fields[0]['options'][0]['value']);
        $this->assertSame('Support', $fields[0]['options'][1]['label']);
    }

    public function testParsesSelectWithValueLabelPairs(): void
    {
        $fields = $this->parser->parse('[select dept "Sales|sales" "Support|support"]');
        $this->assertSame('Sales',   $fields[0]['options'][0]['label']);
        $this->assertSame('sales',   $fields[0]['options'][0]['value']);
        $this->assertSame('support', $fields[0]['options'][1]['value']);
    }

    public function testParsesBooleanFlag(): void
    {
        $fields = $this->parser->parse('[select dept include_blank "A" "B"]');
        $this->assertContains('include_blank', $fields[0]['flags']);
    }

    public function testParsesSubmitWithoutName(): void
    {
        $fields = $this->parser->parse('[submit "Send Message"]');
        $this->assertSame('submit', $fields[0]['type']);
        $this->assertSame('',       $fields[0]['name']);
        $this->assertSame('Send Message', $fields[0]['options'][0]['label']);
    }

    public function testParsesMultipleFieldsFromTemplate(): void
    {
        $template = <<<TPL
<label>Name</label>
[text* your-name placeholder "Full name"]

<label>Email</label>
[email* your-email]

[submit "Go"]
TPL;
        $fields = $this->parser->parse($template);
        $this->assertCount(3, $fields);
        $this->assertSame('text',   $fields[0]['type']);
        $this->assertSame('email',  $fields[1]['type']);
        $this->assertSame('submit', $fields[2]['type']);
    }

    public function testParsesCombinedKeyValueAndPlaceholder(): void
    {
        $fields = $this->parser->parse('[text* your-name placeholder "Name" maxlength:200]');
        $this->assertSame('Name', $fields[0]['params']['placeholder']);
        $this->assertSame('200',  $fields[0]['params']['maxlength']);
    }

    public function testParsesNumberFieldWithMinMax(): void
    {
        $fields = $this->parser->parse('[number qty min:1 max:100 step:1]');
        $this->assertSame('number', $fields[0]['type']);
        $this->assertSame('1',   $fields[0]['params']['min']);
        $this->assertSame('100', $fields[0]['params']['max']);
        $this->assertSame('1',   $fields[0]['params']['step']);
    }

    public function testParsesFileFieldWithAcceptAndLimit(): void
    {
        $fields = $this->parser->parse('[file attachment accept:.pdf,.docx limit:5mb]');
        $this->assertSame('file',       $fields[0]['type']);
        $this->assertSame('.pdf,.docx', $fields[0]['params']['accept']);
        $this->assertSame('5mb',        $fields[0]['params']['limit']);
    }
}
```

- [ ] **Step 2: Run tests to confirm they fail**

```bash
./vendor/bin/phpunit tests/Unit/Service/ShortcodeParserTest.php
```

Expected: FAIL — `Class "PrestaForm\Service\ShortcodeParser" not found`

- [ ] **Step 3: Implement ShortcodeParser**

Create `src/Service/ShortcodeParser.php`:

```php
<?php
declare(strict_types=1);

namespace PrestaForm\Service;

class ShortcodeParser
{
    /**
     * Parse a form template string into an array of field definitions.
     *
     * @return list<array{type: string, required: bool, name: string, params: array<string, string>, options: list<array{label: string, value: string}>, flags: list<string>}>
     */
    public function parse(string $template): array
    {
        $fields = [];
        preg_match_all('/\[([^\]]+)\]/', $template, $matches);

        foreach ($matches[1] as $inner) {
            $field = $this->parseTag($inner);
            if ($field !== null) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    /**
     * Parse a single tag's inner content (everything between [ and ]).
     *
     * @return array{type: string, required: bool, name: string, params: array<string, string>, options: list<array{label: string, value: string}>, flags: list<string>}|null
     */
    public function parseTag(string $inner): ?array
    {
        $tokens = $this->tokenize($inner);
        if (empty($tokens)) {
            return null;
        }

        // First token: type with optional * suffix
        $typeToken = array_shift($tokens);
        $required  = str_ends_with($typeToken, '*');
        $type      = strtolower(rtrim($typeToken, '*'));

        // Second token: field name (slug-like identifier, not a quoted string)
        $name = '';
        if (!empty($tokens) && preg_match('/^[a-z][a-z0-9_-]*$/i', $tokens[0])) {
            $name = array_shift($tokens);
        }

        ['params' => $params, 'options' => $options, 'flags' => $flags] =
            $this->parseAttributes($tokens);

        return [
            'type'     => $type,
            'required' => $required,
            'name'     => $name,
            'params'   => $params,
            'options'  => $options,
            'flags'    => $flags,
        ];
    }

    /**
     * @param list<string> $tokens
     * @return array{params: array<string, string>, options: list<array{label: string, value: string}>, flags: list<string>}
     */
    private function parseAttributes(array $tokens): array
    {
        $params  = [];
        $options = [];
        $flags   = [];
        $i       = 0;
        $count   = count($tokens);

        while ($i < $count) {
            $token = $tokens[$i];

            if (str_contains($token, ':')) {
                // key:value — value may or may not be quoted
                [$key, $value] = explode(':', $token, 2);
                $params[trim($key)] = trim($value, '"\'');
                $i++;
            } elseif ($this->isQuoted($token)) {
                // Bare quoted string → option
                $value = trim($token, '"\'');
                if (str_contains($value, '|')) {
                    [$label, $val] = explode('|', $value, 2);
                    $options[] = ['label' => $label, 'value' => $val];
                } else {
                    $options[] = ['label' => $value, 'value' => $value];
                }
                $i++;
            } elseif (isset($tokens[$i + 1]) && $this->isQuoted($tokens[$i + 1])) {
                // bare_word "quoted value" → named param
                $params[$token] = trim($tokens[$i + 1], '"\'');
                $i += 2;
            } else {
                // Bare keyword → boolean flag
                $flags[] = $token;
                $i++;
            }
        }

        return ['params' => $params, 'options' => $options, 'flags' => $flags];
    }

    /** @return list<string> */
    private function tokenize(string $str): array
    {
        preg_match_all('/"[^"]*"|\'[^\']*\'|[^\s]+/', $str, $matches);
        return $matches[0];
    }

    private function isQuoted(string $token): bool
    {
        return (str_starts_with($token, '"') && str_ends_with($token, '"'))
            || (str_starts_with($token, "'") && str_ends_with($token, "'"));
    }
}
```

- [ ] **Step 4: Run tests — all must pass**

```bash
./vendor/bin/phpunit tests/Unit/Service/ShortcodeParserTest.php -v
```

Expected: All 11 tests PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Service/ShortcodeParser.php tests/Unit/Service/ShortcodeParserTest.php
git commit -m "feat: implement ShortcodeParser with full test coverage"
```

---

### Task 6: FormRenderer — TDD

**Files:**
- Create: `src/Service/FormRenderer.php`
- Create: `tests/Unit/Service/FormRendererTest.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/Service/FormRendererTest.php`:

```php
<?php
declare(strict_types=1);

namespace PrestaForm\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use PrestaForm\Service\FormRenderer;
use PrestaForm\Service\ShortcodeParser;

class FormRendererTest extends TestCase
{
    private FormRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new FormRenderer(new ShortcodeParser());
    }

    private function makeForm(array $overrides = []): array
    {
        return array_merge([
            'id_form'          => 1,
            'name'             => 'Test Form',
            'template'         => '',
            'custom_css'       => '',
            'success_message'  => 'Thank you!',
            'captcha_provider' => 'none',
        ], $overrides);
    }

    public function testRendersTextInput(): void
    {
        $form = $this->makeForm(['template' => '[text* your-name]']);
        $html = $this->renderer->render($form, '/submit', 'tok123');

        $this->assertStringContainsString('<input', $html);
        $this->assertStringContainsString('type="text"', $html);
        $this->assertStringContainsString('name="your-name"', $html);
        $this->assertStringContainsString('required', $html);
    }

    public function testRendersOptionalInputWithoutRequired(): void
    {
        $form = $this->makeForm(['template' => '[text your-name]']);
        $html = $this->renderer->render($form, '/submit', 'tok');

        $this->assertStringNotContainsString('required', $html);
    }

    public function testRendersPlaceholder(): void
    {
        $form = $this->makeForm(['template' => '[text* n placeholder "Enter name"]']);
        $html = $this->renderer->render($form, '/submit', 'tok');

        $this->assertStringContainsString('placeholder="Enter name"', $html);
    }

    public function testRendersSelectWithOptions(): void
    {
        $form = $this->makeForm(['template' => '[select dept "Sales" "Support"]']);
        $html = $this->renderer->render($form, '/submit', 'tok');

        $this->assertStringContainsString('<select', $html);
        $this->assertStringContainsString('name="dept"', $html);
        $this->assertStringContainsString('<option value="Sales">Sales</option>', $html);
        $this->assertStringContainsString('<option value="Support">Support</option>', $html);
    }

    public function testRendersSelectWithIncludeBlank(): void
    {
        $form = $this->makeForm(['template' => '[select dept include_blank "A" "B"]']);
        $html = $this->renderer->render($form, '/submit', 'tok');

        $this->assertStringContainsString('<option value="">---</option>', $html);
    }

    public function testRendersTextarea(): void
    {
        $form = $this->makeForm(['template' => '[textarea msg rows:5]']);
        $html = $this->renderer->render($form, '/submit', 'tok');

        $this->assertStringContainsString('<textarea', $html);
        $this->assertStringContainsString('name="msg"', $html);
        $this->assertStringContainsString('rows="5"', $html);
    }

    public function testRendersSubmitButton(): void
    {
        $form = $this->makeForm(['template' => '[submit "Send"]']);
        $html = $this->renderer->render($form, '/submit', 'tok');

        $this->assertStringContainsString('<button type="submit"', $html);
        $this->assertStringContainsString('Send', $html);
    }

    public function testWrapsFormInContainer(): void
    {
        $form = $this->makeForm(['id_form' => 5, 'template' => '[submit "Go"]']);
        $html = $this->renderer->render($form, '/submit', 'tok');

        $this->assertStringContainsString('id="prestaform-5"', $html);
        $this->assertStringContainsString('<form ', $html);
        $this->assertStringContainsString('action="/submit"', $html);
    }

    public function testInjectsHiddenFormIdAndToken(): void
    {
        $form = $this->makeForm(['id_form' => 7, 'template' => '[submit "Go"]']);
        $html = $this->renderer->render($form, '/submit', 'mytoken');

        $this->assertStringContainsString('name="pf_form_id" value="7"', $html);
        $this->assertStringContainsString('name="token" value="mytoken"', $html);
    }

    public function testInjectsScopedCustomCss(): void
    {
        $form = $this->makeForm([
            'id_form'    => 3,
            'template'   => '[submit "Go"]',
            'custom_css' => '.pf-field { color: red; }',
        ]);
        $html = $this->renderer->render($form, '/submit', 'tok');

        $this->assertStringContainsString('<style>', $html);
        $this->assertStringContainsString('#prestaform-3', $html);
        $this->assertStringContainsString('.pf-field { color: red; }', $html);
    }

    public function testPreservesHtmlOutsideTags(): void
    {
        $form = $this->makeForm([
            'template' => '<label>Name</label>' . "\n" . '[text* your-name]',
        ]);
        $html = $this->renderer->render($form, '/submit', 'tok');

        $this->assertStringContainsString('<label>Name</label>', $html);
        $this->assertStringContainsString('<input', $html);
    }

    public function testInjectsConditionsJson(): void
    {
        $form = $this->makeForm(['id_form' => 2, 'template' => '[submit "Go"]']);
        $conditions = [
            ['action' => 'show', 'target_field' => 'msg', 'logic' => 'AND', 'rules' => []],
        ];
        $html = $this->renderer->render($form, '/submit', 'tok', $conditions);

        $this->assertStringContainsString('pfConditions', $html);
        $this->assertStringContainsString('"target_field":"msg"', $html);
    }
}
```

- [ ] **Step 2: Run tests to confirm they fail**

```bash
./vendor/bin/phpunit tests/Unit/Service/FormRendererTest.php
```

Expected: FAIL — `Class "PrestaForm\Service\FormRenderer" not found`

- [ ] **Step 3: Implement FormRenderer**

Create `src/Service/FormRenderer.php`:

```php
<?php
declare(strict_types=1);

namespace PrestaForm\Service;

class FormRenderer
{
    public function __construct(private readonly ShortcodeParser $parser) {}

    /**
     * Render a form array to HTML.
     *
     * @param array<string, mixed>                     $form
     * @param list<array<string, mixed>>               $conditionRules
     */
    public function render(
        array  $form,
        string $actionUrl,
        string $token,
        array  $conditionRules = []
    ): string {
        $formId   = (int) $form['id_form'];
        $template = (string) ($form['template'] ?? '');

        // Replace [tag] tokens in-place within the template HTML
        $body = preg_replace_callback(
            '/\[([^\]]+)\]/',
            fn(array $m): string => $this->renderTag($m[1]),
            $template
        ) ?? $template;

        $css         = $this->renderCss((string) ($form['custom_css'] ?? ''), $formId);
        $condJson    = json_encode($conditionRules, JSON_UNESCAPED_UNICODE);
        $enctype     = str_contains($template, '[file') ? ' enctype="multipart/form-data"' : '';
        $captchaScript = $this->renderCaptchaScript((string) ($form['captcha_provider'] ?? 'none'), $formId);

        return <<<HTML
<div id="prestaform-{$formId}" class="prestaform-wrapper">
{$css}
<script>
window.pfConditions = window.pfConditions || {};
window.pfConditions[{$formId}] = {$condJson};
</script>
{$captchaScript}
<form action="{$actionUrl}" method="post"{$enctype} data-pf-id="{$formId}" novalidate>
<input type="hidden" name="pf_form_id" value="{$formId}">
<input type="hidden" name="token" value="{$token}">
{$body}
</form>
</div>
HTML;
    }

    private function renderCss(string $css, int $formId): string
    {
        if ($css === '') {
            return '';
        }
        return "<style>\n#prestaform-{$formId} { {$css} }\n</style>";
    }

    private function renderCaptchaScript(string $provider, int $formId): string
    {
        return match ($provider) {
            'recaptcha_v2' =>
                '<script src="https://www.google.com/recaptcha/api.js" async defer></script>',
            'recaptcha_v3' =>
                // site key injected at render time — FormRenderer needs it passed in
                // For now emit a data attribute; the real key is appended by the caller
                '<script src="https://www.google.com/recaptcha/api.js" async defer></script>',
            'turnstile' =>
                '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>',
            default => '',
        };
    }

    private function renderTag(string $inner): string
    {
        $field = $this->parser->parseTag($inner);
        if ($field === null) {
            return "[{$inner}]";
        }
        return $this->renderField($field);
    }

    /** @param array{type: string, required: bool, name: string, params: array<string, string>, options: list<array{label: string, value: string}>, flags: list<string>} $field */
    private function renderField(array $field): string
    {
        $wrap = fn(string $html): string =>
            "<div class=\"pf-field pf-field-{$field['type']}\" data-pf-name=\"{$field['name']}\">{$html}</div>";

        return match ($field['type']) {
            'text', 'email', 'tel', 'number', 'date', 'hidden' => $wrap($this->renderInput($field)),
            'textarea'  => $wrap($this->renderTextarea($field)),
            'select'    => $wrap($this->renderSelect($field)),
            'checkbox'  => $wrap($this->renderCheckbox($field)),
            'radio'     => $wrap($this->renderRadio($field)),
            'file'      => $wrap($this->renderFile($field)),
            'recaptcha' => $wrap($this->renderRecaptcha($field)),
            'submit'    => $this->renderSubmit($field),
            default     => "[{$field['type']}]",
        };
    }

    /** @param array{type: string, required: bool, name: string, params: array<string, string>, options: list<array{label: string, value: string}>, flags: list<string>} $field */
    private function renderInput(array $field): string
    {
        $type     = htmlspecialchars($field['type']);
        $name     = htmlspecialchars($field['name']);
        $required = $field['required'] ? ' required' : '';
        $attrs    = $this->buildAttrs($field['params'], ['placeholder', 'maxlength', 'min', 'max', 'step', 'default']);

        if (isset($field['params']['default'])) {
            $attrs .= ' value="' . htmlspecialchars($field['params']['default']) . '"';
        }

        return "<input type=\"{$type}\" name=\"{$name}\"{$attrs}{$required}>";
    }

    /** @param array{type: string, required: bool, name: string, params: array<string, string>, options: list<array{label: string, value: string}>, flags: list<string>} $field */
    private function renderTextarea(array $field): string
    {
        $name     = htmlspecialchars($field['name']);
        $required = $field['required'] ? ' required' : '';
        $attrs    = $this->buildAttrs($field['params'], ['rows', 'cols', 'placeholder']);

        return "<textarea name=\"{$name}\"{$attrs}{$required}></textarea>";
    }

    /** @param array{type: string, required: bool, name: string, params: array<string, string>, options: list<array{label: string, value: string}>, flags: list<string>} $field */
    private function renderSelect(array $field): string
    {
        $name     = htmlspecialchars($field['name']);
        $required = $field['required'] ? ' required' : '';
        $options  = '';

        if (in_array('include_blank', $field['flags'], true)) {
            $options .= '<option value="">---</option>';
        }

        foreach ($field['options'] as $opt) {
            $label = htmlspecialchars($opt['label']);
            $value = htmlspecialchars($opt['value']);
            $options .= "<option value=\"{$value}\">{$label}</option>";
        }

        return "<select name=\"{$name}\"{$required}>{$options}</select>";
    }

    /** @param array{type: string, required: bool, name: string, params: array<string, string>, options: list<array{label: string, value: string}>, flags: list<string>} $field */
    private function renderCheckbox(array $field): string
    {
        $name     = htmlspecialchars($field['name']);
        $required = $field['required'] ? ' required' : '';
        $html     = '';

        if (count($field['options']) === 1) {
            $label = htmlspecialchars($field['options'][0]['label']);
            $value = htmlspecialchars($field['options'][0]['value']);
            $html  = "<label><input type=\"checkbox\" name=\"{$name}\" value=\"{$value}\"{$required}> {$label}</label>";
        } else {
            foreach ($field['options'] as $i => $opt) {
                $label = htmlspecialchars($opt['label']);
                $value = htmlspecialchars($opt['value']);
                $html .= "<label><input type=\"checkbox\" name=\"{$name}[]\" value=\"{$value}\"> {$label}</label>";
            }
        }

        return $html;
    }

    /** @param array{type: string, required: bool, name: string, params: array<string, string>, options: list<array{label: string, value: string}>, flags: list<string>} $field */
    private function renderRadio(array $field): string
    {
        $name     = htmlspecialchars($field['name']);
        $required = $field['required'] ? ' required' : '';
        $html     = '';

        foreach ($field['options'] as $opt) {
            $label = htmlspecialchars($opt['label']);
            $value = htmlspecialchars($opt['value']);
            $html .= "<label><input type=\"radio\" name=\"{$name}\" value=\"{$value}\"{$required}> {$label}</label>";
        }

        return $html;
    }

    /** @param array{type: string, required: bool, name: string, params: array<string, string>, options: list<array{label: string, value: string}>, flags: list<string>} $field */
    private function renderFile(array $field): string
    {
        $name     = htmlspecialchars($field['name']);
        $required = $field['required'] ? ' required' : '';
        $accept   = isset($field['params']['accept'])
            ? ' accept="' . htmlspecialchars($field['params']['accept']) . '"'
            : '';

        return "<input type=\"file\" name=\"{$name}\"{$accept}{$required}>";
    }

    /** @param array{type: string, required: bool, name: string, params: array<string, string>, options: list<array{label: string, value: string}>, flags: list<string>} $field */
    private function renderRecaptcha(array $field): string
    {
        // Placeholder div — actual widget script is injected by render() based on captcha_provider
        return '<div class="pf-recaptcha" data-pf-captcha="1"></div>';
    }

    /** @param array{type: string, required: bool, name: string, params: array<string, string>, options: list<array{label: string, value: string}>, flags: list<string>} $field */
    private function renderSubmit(array $field): string
    {
        $label = $field['options'][0]['label'] ?? 'Submit';
        return '<div class="pf-field pf-field-submit"><button type="submit" class="pf-submit">'
            . htmlspecialchars($label) . '</button></div>';
    }

    /** @param array<string, string> $params */
    private function buildAttrs(array $params, array $allowedKeys): string
    {
        $out = '';
        foreach ($allowedKeys as $key) {
            if (isset($params[$key])) {
                $out .= ' ' . $key . '="' . htmlspecialchars($params[$key]) . '"';
            }
        }
        return $out;
    }
}
```

- [ ] **Step 4: Run tests — all must pass**

```bash
./vendor/bin/phpunit tests/Unit/Service/FormRendererTest.php -v
```

Expected: All 13 tests PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Service/FormRenderer.php tests/Unit/Service/FormRendererTest.php
git commit -m "feat: implement FormRenderer with full test coverage"
```

---

### Task 7: ConditionEvaluator — TDD

**Files:**
- Create: `src/Service/ConditionEvaluator.php`
- Create: `tests/Unit/Service/ConditionEvaluatorTest.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/Service/ConditionEvaluatorTest.php`:

```php
<?php
declare(strict_types=1);

namespace PrestaForm\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use PrestaForm\Service\ConditionEvaluator;

class ConditionEvaluatorTest extends TestCase
{
    private ConditionEvaluator $evaluator;

    protected function setUp(): void
    {
        $this->evaluator = new ConditionEvaluator();
    }

    /** Build a condition group array */
    private function group(string $target, string $action, string $logic, array $rules): array
    {
        return [
            'target_field' => $target,
            'action'       => $action,
            'logic'        => $logic,
            'rules'        => $rules,
        ];
    }

    /** Build a single rule array */
    private function rule(string $field, string $operator, string $value): array
    {
        return ['field' => $field, 'operator' => $operator, 'value' => $value];
    }

    public function testFieldVisibleWhenNoGroups(): void
    {
        $visible = $this->evaluator->getVisibleFields(
            ['name', 'email', 'msg'],
            [],
            []
        );
        $this->assertSame(['name', 'email', 'msg'], $visible);
    }

    public function testShowActionWithEqualsRuleMatching(): void
    {
        $groups = [
            $this->group('other-dept', 'show', 'AND', [
                $this->rule('department', 'equals', 'Other'),
            ]),
        ];
        $data   = ['department' => 'Other'];

        $visible = $this->evaluator->getVisibleFields(
            ['department', 'other-dept'],
            $groups,
            $data
        );

        $this->assertContains('other-dept', $visible);
    }

    public function testShowActionWithEqualsRuleNotMatching(): void
    {
        $groups = [
            $this->group('other-dept', 'show', 'AND', [
                $this->rule('department', 'equals', 'Other'),
            ]),
        ];
        $data   = ['department' => 'Sales'];

        $visible = $this->evaluator->getVisibleFields(
            ['department', 'other-dept'],
            $groups,
            $data
        );

        $this->assertNotContains('other-dept', $visible);
    }

    public function testHideActionWithMatchingRule(): void
    {
        $groups = [
            $this->group('phone', 'hide', 'AND', [
                $this->rule('contact-pref', 'equals', 'Email'),
            ]),
        ];
        $data   = ['contact-pref' => 'Email'];

        $visible = $this->evaluator->getVisibleFields(
            ['contact-pref', 'phone'],
            $groups,
            $data
        );

        $this->assertNotContains('phone', $visible);
    }

    public function testAndLogicRequiresAllRulesTrue(): void
    {
        $groups = [
            $this->group('extra', 'show', 'AND', [
                $this->rule('dept', 'equals', 'Other'),
                $this->rule('pref', 'equals', 'Phone'),
            ]),
        ];

        // Only first rule matches
        $data    = ['dept' => 'Other', 'pref' => 'Email'];
        $visible = $this->evaluator->getVisibleFields(['extra', 'dept', 'pref'], $groups, $data);
        $this->assertNotContains('extra', $visible);

        // Both rules match
        $data2   = ['dept' => 'Other', 'pref' => 'Phone'];
        $visible2 = $this->evaluator->getVisibleFields(['extra', 'dept', 'pref'], $groups, $data2);
        $this->assertContains('extra', $visible2);
    }

    public function testOrLogicRequiresAnyRuleTrue(): void
    {
        $groups = [
            $this->group('extra', 'show', 'OR', [
                $this->rule('dept', 'equals', 'Other'),
                $this->rule('pref', 'equals', 'Phone'),
            ]),
        ];

        // Only second rule matches — should still show
        $data    = ['dept' => 'Sales', 'pref' => 'Phone'];
        $visible = $this->evaluator->getVisibleFields(['extra', 'dept', 'pref'], $groups, $data);
        $this->assertContains('extra', $visible);

        // Neither matches
        $data2   = ['dept' => 'Sales', 'pref' => 'Email'];
        $visible2 = $this->evaluator->getVisibleFields(['extra', 'dept', 'pref'], $groups, $data2);
        $this->assertNotContains('extra', $visible2);
    }

    public function testOperatorNotEquals(): void
    {
        $groups = [
            $this->group('extra', 'show', 'AND', [
                $this->rule('dept', 'not_equals', 'Sales'),
            ]),
        ];

        $data    = ['dept' => 'Support'];
        $visible = $this->evaluator->getVisibleFields(['extra', 'dept'], $groups, $data);
        $this->assertContains('extra', $visible);

        $data2   = ['dept' => 'Sales'];
        $visible2 = $this->evaluator->getVisibleFields(['extra', 'dept'], $groups, $data2);
        $this->assertNotContains('extra', $visible2);
    }

    public function testOperatorContains(): void
    {
        $groups = [
            $this->group('extra', 'show', 'AND', [
                $this->rule('msg', 'contains', 'urgent'),
            ]),
        ];

        $data    = ['msg' => 'This is urgent please help'];
        $visible = $this->evaluator->getVisibleFields(['extra', 'msg'], $groups, $data);
        $this->assertContains('extra', $visible);
    }

    public function testOperatorIsEmpty(): void
    {
        $groups = [
            $this->group('hint', 'show', 'AND', [
                $this->rule('phone', 'is_empty', ''),
            ]),
        ];

        $visible  = $this->evaluator->getVisibleFields(['hint', 'phone'], $groups, ['phone' => '']);
        $this->assertContains('hint', $visible);

        $visible2 = $this->evaluator->getVisibleFields(['hint', 'phone'], $groups, ['phone' => '555-1234']);
        $this->assertNotContains('hint', $visible2);
    }

    public function testOperatorIsNotEmpty(): void
    {
        $groups = [
            $this->group('clear-btn', 'show', 'AND', [
                $this->rule('phone', 'is_not_empty', ''),
            ]),
        ];

        $visible  = $this->evaluator->getVisibleFields(['clear-btn', 'phone'], $groups, ['phone' => '555']);
        $this->assertContains('clear-btn', $visible);

        $visible2 = $this->evaluator->getVisibleFields(['clear-btn', 'phone'], $groups, ['phone' => '']);
        $this->assertNotContains('clear-btn', $visible2);
    }
}
```

- [ ] **Step 2: Run tests to confirm they fail**

```bash
./vendor/bin/phpunit tests/Unit/Service/ConditionEvaluatorTest.php
```

Expected: FAIL — `Class "PrestaForm\Service\ConditionEvaluator" not found`

- [ ] **Step 3: Implement ConditionEvaluator**

Create `src/Service/ConditionEvaluator.php`:

```php
<?php
declare(strict_types=1);

namespace PrestaForm\Service;

class ConditionEvaluator
{
    /**
     * Return the list of field names that should be visible, given condition groups and submitted data.
     *
     * Fields not targeted by any rule are always visible.
     * A 'show' rule makes the field visible only when its conditions pass.
     * A 'hide' rule makes the field hidden when its conditions pass (visible otherwise).
     *
     * @param list<string>                 $allFields     All field names in the form
     * @param list<array<string, mixed>>   $groups        Condition groups from pf_conditions
     * @param array<string, mixed>         $data          Submitted (or current) field values
     * @return list<string>
     */
    public function getVisibleFields(array $allFields, array $groups, array $data): array
    {
        // Build a map: target_field → visibility override
        // null = no rule targets this field (always visible)
        // true = visible, false = hidden
        $visibility = [];

        foreach ($groups as $group) {
            $target     = (string) $group['target_field'];
            $action     = (string) $group['action'];
            $logic      = strtoupper((string) $group['logic']);
            $rules      = is_string($group['rules'])
                ? (json_decode($group['rules'], true) ?? [])
                : (array) $group['rules'];

            $conditionMet = $this->evaluateGroup($rules, $logic, $data);

            if ($action === 'show') {
                // Visible only when condition is met
                $visibility[$target] = $conditionMet;
            } else {
                // Hidden when condition is met, visible otherwise
                $visibility[$target] = !$conditionMet;
            }
        }

        $visible = [];
        foreach ($allFields as $field) {
            if (!array_key_exists($field, $visibility) || $visibility[$field] === true) {
                $visible[] = $field;
            }
        }

        return $visible;
    }

    /**
     * @param list<array<string, string>> $rules
     * @param array<string, mixed>        $data
     */
    private function evaluateGroup(array $rules, string $logic, array $data): bool
    {
        if (empty($rules)) {
            return true;
        }

        $results = array_map(fn(array $rule) => $this->evaluateRule($rule, $data), $rules);

        return $logic === 'OR'
            ? in_array(true, $results, true)
            : !in_array(false, $results, true);
    }

    /** @param array<string, string> $rule */
    private function evaluateRule(array $rule, array $data): bool
    {
        $field    = $rule['field']    ?? '';
        $operator = $rule['operator'] ?? 'equals';
        $target   = $rule['value']    ?? '';
        $actual   = (string) ($data[$field] ?? '');

        return match ($operator) {
            'equals'       => $actual === $target,
            'not_equals'   => $actual !== $target,
            'contains'     => str_contains($actual, $target),
            'is_empty'     => $actual === '',
            'is_not_empty' => $actual !== '',
            default        => false,
        };
    }
}
```

- [ ] **Step 4: Run tests — all must pass**

```bash
./vendor/bin/phpunit tests/Unit/Service/ConditionEvaluatorTest.php -v
```

Expected: All 10 tests PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Service/ConditionEvaluator.php tests/Unit/Service/ConditionEvaluatorTest.php
git commit -m "feat: implement ConditionEvaluator with AND/OR logic and full test coverage"
```

---

### Task 8: EmailRouter — TDD

**Files:**
- Create: `src/Service/EmailRouter.php`
- Create: `tests/Unit/Service/EmailRouterTest.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/Service/EmailRouterTest.php`:

```php
<?php
declare(strict_types=1);

namespace PrestaForm\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use PrestaForm\Service\EmailRouter;

class EmailRouterTest extends TestCase
{
    private EmailRouter $router;

    protected function setUp(): void
    {
        $this->router = new EmailRouter();
    }

    public function testRenderTemplateSubstitutesFieldVars(): void
    {
        $result = $this->router->renderTemplate(
            'Hello [your-name], your email is [your-email].',
            ['your-name' => 'Jane', 'your-email' => 'jane@example.com'],
            []
        );
        $this->assertSame('Hello Jane, your email is jane@example.com.', $result);
    }

    public function testRenderTemplateSubstitutesSystemVars(): void
    {
        $result = $this->router->renderTemplate(
            'Form: [_form_title] submitted on [_date].',
            [],
            ['name' => 'Contact Form']
        );
        $this->assertStringContainsString('Form: Contact Form submitted on', $result);
    }

    public function testRenderTemplateHandlesMissingVar(): void
    {
        $result = $this->router->renderTemplate(
            'Hello [missing-var].',
            [],
            []
        );
        $this->assertSame('Hello [missing-var].', $result);
    }

    public function testRenderTemplateHandlesArrayFieldValue(): void
    {
        $result = $this->router->renderTemplate(
            'Interests: [interests].',
            ['interests' => ['PHP', 'PS9', 'Forms']],
            []
        );
        $this->assertSame('Interests: PHP, PS9, Forms.', $result);
    }

    public function testResolveAddressesReturnsBaseWhenNoRules(): void
    {
        $route = [
            'notify_addresses' => ['admin@store.com', 'boss@store.com'],
            'routing_rules'    => null,
        ];
        $addresses = $this->router->resolveAdminAddresses($route, []);
        $this->assertSame(['admin@store.com', 'boss@store.com'], $addresses);
    }

    public function testResolveAddressesOverridesWhenRuleMatches(): void
    {
        $route = [
            'notify_addresses' => ['admin@store.com'],
            'routing_rules'    => [
                ['field' => 'department', 'value' => 'Sales', 'email' => 'sales@store.com'],
            ],
        ];
        $addresses = $this->router->resolveAdminAddresses($route, ['department' => 'Sales']);
        $this->assertSame(['sales@store.com'], $addresses);
    }

    public function testResolveAddressesKeepsBaseWhenRuleDoesNotMatch(): void
    {
        $route = [
            'notify_addresses' => ['admin@store.com'],
            'routing_rules'    => [
                ['field' => 'department', 'value' => 'Sales', 'email' => 'sales@store.com'],
            ],
        ];
        $addresses = $this->router->resolveAdminAddresses($route, ['department' => 'Support']);
        $this->assertSame(['admin@store.com'], $addresses);
    }

    public function testResolveAddressesSubstitutesFieldVarInAddress(): void
    {
        $route = [
            'notify_addresses' => ['[your-email]', 'admin@store.com'],
            'routing_rules'    => null,
        ];
        $addresses = $this->router->resolveAdminAddresses($route, ['your-email' => 'user@example.com']);
        $this->assertSame(['user@example.com', 'admin@store.com'], $addresses);
    }

    public function testResolveAddressesDecodesJsonString(): void
    {
        $route = [
            'notify_addresses' => json_encode(['a@b.com', 'c@d.com']),
            'routing_rules'    => null,
        ];
        $addresses = $this->router->resolveAdminAddresses($route, []);
        $this->assertSame(['a@b.com', 'c@d.com'], $addresses);
    }
}
```

- [ ] **Step 2: Run tests to confirm they fail**

```bash
./vendor/bin/phpunit tests/Unit/Service/EmailRouterTest.php
```

Expected: FAIL — `Class "PrestaForm\Service\EmailRouter" not found`

- [ ] **Step 3: Implement EmailRouter**

Create `src/Service/EmailRouter.php`:

```php
<?php
declare(strict_types=1);

namespace PrestaForm\Service;

class EmailRouter
{
    /**
     * Render a subject/body template, substituting [field] and system variables.
     *
     * @param array<string, mixed> $submission  Field name → value
     * @param array<string, mixed> $form        Form row (needs 'name' key)
     */
    public function renderTemplate(string $template, array $submission, array $form): string
    {
        $vars = [];

        foreach ($submission as $key => $value) {
            $vars["[{$key}]"] = is_array($value) ? implode(', ', $value) : (string) $value;
        }

        $vars['[_form_title]'] = (string) ($form['name'] ?? '');
        $vars['[_date]']       = date('Y-m-d H:i:s');
        $vars['[_ip]']         = $_SERVER['REMOTE_ADDR'] ?? '';

        return strtr($template, $vars);
    }

    /**
     * Resolve the final list of notify email addresses, applying conditional routing rules
     * and substituting [field] variables in address strings.
     *
     * @param array<string, mixed> $route       Row from pf_email_routes
     * @param array<string, mixed> $submission  Submitted field values
     * @return list<string>
     */
    public function resolveAdminAddresses(array $route, array $submission): array
    {
        $addresses = $this->decodeAddresses($route['notify_addresses']);

        // Apply conditional routing rules
        $rules = $route['routing_rules'];
        if (is_string($rules)) {
            $rules = json_decode($rules, true) ?? [];
        }
        if (!empty($rules)) {
            foreach ((array) $rules as $rule) {
                $field = $rule['field'] ?? '';
                $value = $rule['value'] ?? '';
                $email = $rule['email'] ?? '';

                if (isset($submission[$field]) && (string) $submission[$field] === $value) {
                    $addresses = [$email];
                    break; // First matching rule wins
                }
            }
        }

        // Substitute [field] variables in address strings
        return array_map(
            fn(string $addr): string => $this->renderTemplate($addr, $submission, []),
            $addresses
        );
    }

    /**
     * Dispatch admin notification emails. Calls PS9's Mail::Send().
     * Not unit-tested (PS9 dependency); tested manually.
     *
     * @param array<string, mixed> $form
     * @param array<string, mixed> $route      Row from pf_email_routes (type='admin')
     * @param array<string, mixed> $submission
     */
    public function dispatchAdmin(array $form, array $route, array $submission): void
    {
        $addresses = $this->resolveAdminAddresses($route, $submission);
        $subject   = $this->renderTemplate((string) ($route['subject'] ?? ''), $submission, $form);
        $body      = $this->renderTemplate((string) ($route['body']    ?? ''), $submission, $form);

        foreach ($addresses as $address) {
            if (!$address || !filter_var($address, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            \Mail::Send(
                (int) \Configuration::get('PS_LANG_DEFAULT'),
                'prestaform_notification',
                $subject,
                [
                    '{message}' => nl2br(htmlspecialchars($body)),
                ],
                $address,
                null,
                (string) \Configuration::get('PS_SHOP_EMAIL'),
                (string) \Configuration::get('PS_SHOP_NAME'),
                null,
                null,
                __DIR__ . '/../../views/templates/mail/'
            );
        }
    }

    /**
     * Dispatch submitter confirmation email.
     *
     * @param array<string, mixed> $form
     * @param array<string, mixed> $route      Row from pf_email_routes (type='confirmation')
     * @param array<string, mixed> $submission
     */
    public function dispatchConfirmation(array $form, array $route, array $submission): void
    {
        if (!(int) ($route['enabled'] ?? 1)) {
            return;
        }

        // Find submitter email from submission data (look for first email-type value)
        $toEmail = '';
        foreach ($submission as $value) {
            if (is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $toEmail = $value;
                break;
            }
        }

        if (!$toEmail) {
            return;
        }

        $subject = $this->renderTemplate((string) ($route['subject'] ?? ''), $submission, $form);
        $body    = $this->renderTemplate((string) ($route['body']    ?? ''), $submission, $form);
        $replyTo = $route['reply_to'] ?: \Configuration::get('PS_SHOP_EMAIL');

        \Mail::Send(
            (int) \Configuration::get('PS_LANG_DEFAULT'),
            'prestaform_confirmation',
            $subject,
            ['{message}' => nl2br(htmlspecialchars($body))],
            $toEmail,
            null,
            $replyTo,
            (string) \Configuration::get('PS_SHOP_NAME'),
            null,
            null,
            __DIR__ . '/../../views/templates/mail/'
        );
    }

    /** @return list<string> */
    private function decodeAddresses(mixed $raw): array
    {
        if (is_array($raw)) {
            return array_values(array_filter($raw, 'is_string'));
        }
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return array_values(array_filter($decoded, 'is_string'));
            }
        }
        return [];
    }
}
```

- [ ] **Step 4: Run tests — all must pass**

```bash
./vendor/bin/phpunit tests/Unit/Service/EmailRouterTest.php -v
```

Expected: All 9 tests PASS.

- [ ] **Step 5: Run full test suite**

```bash
./vendor/bin/phpunit -v
```

Expected: All tests (ShortcodeParser + FormRenderer + ConditionEvaluator + EmailRouter) PASS.

- [ ] **Step 6: Commit**

```bash
git add src/Service/EmailRouter.php tests/Unit/Service/EmailRouterTest.php
git commit -m "feat: implement EmailRouter with template rendering, routing rules, full test coverage"
```
