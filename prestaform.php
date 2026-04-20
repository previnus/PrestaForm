<?php
declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

// PSR-4 autoloader for PrestaForm\* — no composer vendor needed in production
spl_autoload_register(static function (string $class): void {
    if (strncmp($class, 'PrestaForm\\', 11) !== 0) {
        return;
    }
    $file = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, 11)) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

class Prestaform extends Module implements WidgetInterface
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
            && $this->migrateSchema()
            && $this->installTabs()
            && $this->registerHook('displayHeader')
            && $this->registerHook('actionCronJob');
    }

    /**
     * Safe schema migration — runs on install AND on getContent() so existing
     * installations automatically gain new columns without a reinstall.
     */
    private function migrateSchema(): bool
    {
        $db  = Db::getInstance();
        $tbl = _DB_PREFIX_ . 'pf_email_routes';

        // Guard: table may not exist yet during fresh install (installDb handles creation)
        $exists = $db->getValue('SELECT COUNT(*) FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = \'' . pSQL($tbl) . '\'');
        if (!$exists) {
            return true;
        }

        $cols = array_column(
            $db->executeS('SHOW COLUMNS FROM `' . $tbl . '`') ?: [],
            'Field'
        );

        if (!in_array('from_address', $cols)) {
            $db->execute('ALTER TABLE `' . $tbl . '`
                ADD COLUMN `from_address` VARCHAR(500) NOT NULL DEFAULT \'\'
                AFTER `reply_to`');
        }
        if (!in_array('additional_headers', $cols)) {
            $db->execute('ALTER TABLE `' . $tbl . '`
                ADD COLUMN `additional_headers` TEXT NOT NULL DEFAULT \'\'
                AFTER `from_address`');
        }

        return true;
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
        if ($sql === false) {
            return false;
        }
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
        if ($sql === false) {
            return true; // file missing — nothing to drop, treat as success
        }
        $sql = str_replace('PREFIX_', _DB_PREFIX_, $sql);
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $query) {
            Db::getInstance()->execute($query);
        }
        return true;
    }

    private function installTabs(): bool
    {
        $langs = Language::getLanguages(false);

        // In PS9 the sidebar uses 0 for top-level items; -1 hides from menu.
        // We want a visible top-level "PrestaForm" entry.
        $parent = new Tab();
        $parent->class_name = 'AdminPrestaFormMain';
        $parent->module     = $this->name;
        $parent->id_parent  = 0;
        $parent->active     = 1;
        $parent->icon       = 'library_books';
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
            $tab->module     = $this->name;
            $tab->id_parent  = (int) $parent->id;
            $tab->active     = 1;
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

    /**
     * Adds a "Configure" button on the Modules page that goes straight to Forms.
     */
    public function getContent(): void
    {
        $this->migrateSchema(); // auto-upgrade existing installs
        Tools::redirectAdmin(
            $this->context->link->getAdminLink('AdminPrestaFormForms')
        );
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
                return $this->renderFormByConfig($this->parseShortcodeAttrs($m[1]));
            },
            $output
        ) ?? $output;
    }

    // ── WidgetInterface ──────────────────────────────────────────────────────
    // Lets Creative Elements (and any other PS widget-aware page builder) embed
    // a PrestaForm form via {widget name="prestaform" id_form="X"} or
    // {widget name="prestaform" slug="contact-form"}.

    /**
     * Render a form as a PS widget.
     *
     * Creative Elements passes whatever key=value pairs the editor configured
     * inside the widget element as the $configuration array.
     *
     * @param string               $hookName      Unused — required by interface
     * @param array<string, mixed> $configuration Widget parameters (id_form / slug)
     */
    public function renderWidget(string $hookName, array $configuration): string
    {
        return $this->renderFormByConfig($configuration);
    }

    /**
     * Variables for a widget Smarty sub-template (not used — we render inline).
     *
     * @param string               $hookName
     * @param array<string, mixed> $configuration
     * @return array<string, mixed>
     */
    public function getWidgetVariables(string $hookName, array $configuration): array
    {
        return [];
    }

    /**
     * Shared render helper used by both the Smarty output-filter shortcode path
     * and the WidgetInterface path.
     *
     * Accepts:  id / id_form  →  look up by numeric ID
     *           name / slug   →  look up by slug
     *
     * @param array<string, mixed> $attrs
     */
    private function renderFormByConfig(array $attrs): string
    {
        $formRepo = new \PrestaForm\Repository\FormRepository();

        if (!empty($attrs['id_form'])) {
            $form = $formRepo->findById((int) $attrs['id_form']);
        } elseif (!empty($attrs['id'])) {
            $form = $formRepo->findById((int) $attrs['id']);
        } elseif (!empty($attrs['slug'])) {
            $form = $formRepo->findBySlug((string) $attrs['slug']);
        } elseif (!empty($attrs['name'])) {
            $form = $formRepo->findBySlug((string) $attrs['name']);
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
