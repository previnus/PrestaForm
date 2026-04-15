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
