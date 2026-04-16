<?php
declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

class AdminPrestaFormSettingsController extends ModuleAdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->bootstrap  = true;
        $this->meta_title = 'PrestaForm — Settings';
    }

    private function getSettings(): array
    {
        $rows = \Db::getInstance()->executeS(
            'SELECT setting_key, setting_value FROM `' . _DB_PREFIX_ . 'pf_settings`'
        ) ?: [];
        return array_column($rows, 'setting_value', 'setting_key');
    }

    public function renderList(): string
    {
        $this->context->smarty->assign(['settings' => $this->getSettings()]);
        return $this->context->smarty->fetch(
            _PS_MODULE_DIR_ . 'prestaform/views/templates/admin/settings/index.tpl'
        );
    }

    public function postProcess(): void
    {
        if (Tools::isSubmit('save_settings')) {
            $keys = [
                'recaptcha_v2_site_key', 'recaptcha_v2_secret_key',
                'recaptcha_v3_site_key', 'recaptcha_v3_secret_key',
                'turnstile_site_key',    'turnstile_secret_key',
                'default_retention_days',
            ];
            foreach ($keys as $key) {
                \Db::getInstance()->update(
                    'pf_settings',
                    ['setting_value' => pSQL((string) Tools::getValue($key, ''))],
                    'setting_key = \'' . pSQL($key) . '\''
                );
            }
            $this->confirmations[] = 'Settings saved.';
        }
        parent::postProcess();
    }
}
