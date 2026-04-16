<?php
declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

class AdminPrestaFormFormsController extends ModuleAdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->bootstrap  = true;
        $this->meta_title = 'PrestaForm — Forms';
    }

    public function initContent(): void
    {
        $this->addJS(_MODULE_DIR_ . 'prestaform/views/js/admin/form-builder.js');
        $this->addCSS(_MODULE_DIR_ . 'prestaform/views/css/admin.css');
        parent::initContent();
    }

    public function renderList(): string
    {
        $repo  = new \PrestaForm\Repository\FormRepository();
        $forms = $repo->findAll();
        $this->context->smarty->assign(['forms' => $forms, 'base_url' => $this->context->link->getAdminLink('AdminPrestaFormForms')]);
        return $this->context->smarty->fetch(_PS_MODULE_DIR_ . 'prestaform/views/templates/admin/forms/list.tpl');
    }

    public function renderForm(): string
    {
        $repo   = new \PrestaForm\Repository\FormRepository();
        $wRepo  = new \PrestaForm\Repository\WebhookRepository();
        $cRepo  = new \PrestaForm\Repository\ConditionRepository();
        $eRepo  = new \PrestaForm\Repository\EmailRouteRepository();
        $id     = (int) Tools::getValue('id_form');
        $form   = $id ? $repo->findById($id) : $this->emptyForm();
        $parser = new \PrestaForm\Service\ShortcodeParser();
        $fields = $form ? $parser->parse((string) $form['template']) : [];

        $this->context->smarty->assign([
            'form'        => $form ?? $this->emptyForm(),
            'fields'      => $fields,
            'webhooks'    => $id ? $wRepo->findByForm($id) : [],
            'conditions'  => $id ? $cRepo->findByForm($id) : [],
            'email_routes'=> $id ? $eRepo->findByForm($id) : [
                ['type' => 'admin',        'enabled' => 1, 'notify_addresses' => [], 'reply_to' => null, 'subject' => 'New submission: [_form_title]', 'body' => '', 'routing_rules' => []],
                ['type' => 'confirmation', 'enabled' => 0, 'notify_addresses' => [], 'reply_to' => null, 'subject' => 'We received your message', 'body' => '', 'routing_rules' => []],
            ],
            'base_url'    => $this->context->link->getAdminLink('AdminPrestaFormForms'),
            'captcha_providers' => ['none' => 'None', 'recaptcha_v2' => 'reCAPTCHA v2', 'recaptcha_v3' => 'reCAPTCHA v3', 'turnstile' => 'Cloudflare Turnstile'],
        ]);

        return $this->context->smarty->fetch(_PS_MODULE_DIR_ . 'prestaform/views/templates/admin/forms/edit.tpl');
    }

    public function postProcess(): void
    {
        $action = Tools::getValue('action');

        if ($action === 'save') {
            $this->processSave();
        } elseif ($action === 'delete') {
            $this->processDelete();
        } elseif ($action === 'save_webhooks') {
            $this->processSaveWebhooks();
        } elseif ($action === 'save_conditions') {
            $this->processSaveConditions();
        } elseif ($action === 'save_mail') {
            $this->processSaveMail();
        } elseif ($action === 'test_webhook') {
            $this->processTestWebhook();
        } elseif ($action === 'delete_webhook') {
            $this->processDeleteWebhook();
        }

        parent::postProcess();
    }

    private function processSave(): void
    {
        $repo = new \PrestaForm\Repository\FormRepository();
        $slug = Tools::getValue('slug');
        $id   = (int) Tools::getValue('id_form');

        if ($repo->slugExists($slug, $id)) {
            $this->errors[] = 'Slug is already in use. Please choose another.';
            return;
        }

        $validStatuses   = ['draft', 'active'];
        $validCaptchas   = ['none', 'recaptcha_v2', 'recaptcha_v3', 'turnstile'];
        $status          = Tools::getValue('status');
        $captchaProvider = Tools::getValue('captcha_provider');

        if (!in_array($status, $validStatuses, true)) {
            $this->errors[] = 'Invalid status value.';
            return;
        }
        if (!in_array($captchaProvider, $validCaptchas, true)) {
            $this->errors[] = 'Invalid CAPTCHA provider.';
            return;
        }

        $retentionRaw = Tools::getValue('retention_days');
        if ($retentionRaw === 'custom') {
            $retentionRaw = Tools::getValue('retention_days_custom');
        }
        $repo->save([
            'id_form'          => $id ?: null,
            'name'             => Tools::getValue('name'),
            'slug'             => $slug,
            'template'         => Tools::getValue('template'),
            'custom_css'       => Tools::getValue('custom_css'),
            'success_message'  => Tools::getValue('success_message'),
            'status'           => $status,
            'captcha_provider' => $captchaProvider,
            'retention_days'   => $retentionRaw !== '' ? (int) $retentionRaw : null,
        ]);

        $this->confirmations[] = 'Form saved.';
    }

    private function processDelete(): void
    {
        $id   = (int) Tools::getValue('id_form');
        $repo = new \PrestaForm\Repository\FormRepository();
        $repo->delete($id);
        $this->confirmations[] = 'Form deleted.';
    }

    private function processSaveWebhooks(): void
    {
        $id       = (int) Tools::getValue('id_form');
        $repo     = new \PrestaForm\Repository\WebhookRepository();
        $webhooks = json_decode(Tools::getValue('webhooks_json', '[]'), true) ?? [];

        foreach ($webhooks as $w) {
            $w['id_form'] = $id;
            $repo->save($w);
        }
        $this->confirmations[] = 'Webhooks saved.';
    }

    private function processSaveConditions(): void
    {
        $id      = (int) Tools::getValue('id_form');
        $repo    = new \PrestaForm\Repository\ConditionRepository();
        $groups  = json_decode(Tools::getValue('conditions_json', '[]'), true) ?? [];
        $repo->saveForForm($id, $groups);
        $this->confirmations[] = 'Conditions saved.';
    }

    private function processSaveMail(): void
    {
        $id     = (int) Tools::getValue('id_form');
        $repo   = new \PrestaForm\Repository\EmailRouteRepository();
        $routes = json_decode(Tools::getValue('mail_routes_json', '[]'), true) ?? [];
        $repo->saveForForm($id, $routes);
        $this->confirmations[] = 'Mail settings saved.';
    }

    private function processTestWebhook(): void
    {
        $webhookId  = (int) Tools::getValue('id_webhook');
        $wRepo      = new \PrestaForm\Repository\WebhookRepository();
        $webhook    = $wRepo->findById($webhookId);

        if (!$webhook) {
            $this->outputJson(['success' => false, 'message' => 'Webhook not found.']);
            return;
        }

        $url = $webhook['url'];
        if (!preg_match('#^https?://#i', $url)) {
            $this->outputJson(['success' => false, 'message' => 'Invalid webhook URL scheme.']);
            return;
        }

        $testPayload = ['_test' => true, 'timestamp' => date('c')];
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => (int) $webhook['timeout_seconds'],
            CURLOPT_CUSTOMREQUEST  => $webhook['method'],
            CURLOPT_POSTFIELDS     => json_encode($testPayload),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        ]);
        $body   = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->outputJson(['success' => $status >= 200 && $status < 300, 'status' => $status, 'body' => mb_substr((string) $body, 0, 500)]);
    }

    private function processDeleteWebhook(): void
    {
        $id   = (int) Tools::getValue('id_webhook');
        $repo = new \PrestaForm\Repository\WebhookRepository();
        $repo->delete($id);
        $this->outputJson(['success' => true]);
    }

    private function outputJson(array $data): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    private function emptyForm(): array
    {
        return [
            'id_form'          => 0,
            'name'             => '',
            'slug'             => '',
            'template'         => '',
            'custom_css'       => '',
            'success_message'  => 'Thank you! Your message has been sent.',
            'status'           => 'draft',
            'captcha_provider' => 'none',
            'retention_days'   => null,
        ];
    }
}
