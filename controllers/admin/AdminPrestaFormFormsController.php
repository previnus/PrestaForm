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
        \Media::addJsDef([
            'pfAdminUrl'   => $this->context->link->getAdminLink('AdminPrestaFormForms'),
            'pfActiveTab'  => (string) Tools::getValue('pf_tab', ''),
        ]);

        $action = Tools::getValue('action');

        // Show banners when redirected back after a successful save or delete
        if (Tools::getValue('pf_saved')) {
            $this->confirmations[] = 'Saved successfully.';
        }
        if (Tools::getValue('pf_deleted')) {
            $this->confirmations[] = 'Form deleted.';
        }

        if ($action === 'edit') {
            $this->content = $this->buildFormHtml();
        } else {
            $this->content = $this->buildListHtml();
        }

        parent::initContent();
    }

    private function buildListHtml(): string
    {
        $repo  = new \PrestaForm\Repository\FormRepository();
        $forms = $repo->findAll();
        $this->context->smarty->assign([
            'forms'            => $forms,
            'forms_count'      => count($forms),
            'base_url'         => $this->context->link->getAdminLink('AdminPrestaFormForms'),
            'submissions_url'  => $this->context->link->getAdminLink('AdminPrestaFormSubmissions'),
        ]);
        return $this->context->smarty->fetch(_PS_MODULE_DIR_ . 'prestaform/views/templates/admin/forms/list.tpl');
    }

    private function buildFormHtml(): string
    {
        $repo   = new \PrestaForm\Repository\FormRepository();
        $wRepo  = new \PrestaForm\Repository\WebhookRepository();
        $cRepo  = new \PrestaForm\Repository\ConditionRepository();
        $eRepo  = new \PrestaForm\Repository\EmailRouteRepository();
        $id     = (int) Tools::getValue('id_form');
        $form   = $id ? $repo->findById($id) : $this->emptyForm();
        $parser = new \PrestaForm\Service\ShortcodeParser();
        $fields = $form ? $parser->parse((string) $form['template']) : [];

        $defaultRoutes = [
            [
                'type'               => 'admin',
                'enabled'            => 1,
                'notify_addresses'   => [],
                'from_address'       => '[_shop_name] <[_shop_email]>',
                'additional_headers' => '',
                'reply_to'           => null,
                'subject'            => '[_form_title] — New Submission',
                'body'               => '[_all_fields]',
                'routing_rules'      => [],
            ],
            [
                'type'               => 'confirmation',
                'enabled'            => 0,
                'notify_addresses'   => [],
                'from_address'       => '[_shop_name] <[_shop_email]>',
                'additional_headers' => 'Reply-To: [_shop_email]',
                'reply_to'           => null,
                'subject'            => 'Thank you for contacting us',
                'body'               => "Dear visitor,\n\nThank you for your message. We'll be in touch shortly.\n\nBest regards,\n[_shop_name]",
                'routing_rules'      => [],
            ],
        ];
        // Use DB routes when they exist; fall back to defaults for new forms AND
        // for existing forms that have never had their mail settings saved yet
        // (the DB table is only written by handleSaveMail — not on form create).
        $emailRoutes = $id ? $eRepo->findByForm($id) : [];
        if (empty($emailRoutes)) {
            $emailRoutes = $defaultRoutes;
        }

        // Pre-convert notify_addresses arrays to comma-separated strings (new CF7-style
        // To field) so Smarty never needs |@implode (avoids PS9 strict-type crash).
        foreach ($emailRoutes as &$route) {
            if (is_array($route['notify_addresses'])) {
                $route['notify_addresses'] = implode(', ', $route['notify_addresses']);
            }
            // Ensure new columns exist even when loading old DB rows that predate migration
            $route['from_address']       ??= '';
            $route['additional_headers'] ??= '';
        }
        unset($route);

        $conditions = $id ? $cRepo->findByForm($id) : [];

        // Pre-serialise conditions for the hidden JSON field fallback.
        $conditionsInitJson = json_encode($conditions, JSON_UNESCAPED_UNICODE);

        // Pre-seed mail_routing_json with the admin route's routing_rules so
        // a JS failure cannot wipe existing conditional-routing config.
        $adminRoute = null;
        foreach ($emailRoutes as $r) {
            if (($r['type'] ?? '') === 'admin') {
                $adminRoute = $r;
                break;
            }
        }
        // Restore notify_addresses to array for the routing-rules seed
        $adminRoutingRules = [];
        if ($adminRoute) {
            $rules = $adminRoute['routing_rules'] ?? [];
            if (is_string($rules)) {
                $rules = json_decode($rules, true) ?: [];
            }
            $adminRoutingRules = $rules;
        }
        $mailRoutingInitJson = json_encode($adminRoutingRules, JSON_UNESCAPED_UNICODE);

        $this->context->smarty->assign([
            'form'                    => $form ?? $this->emptyForm(),
            'fields'                  => $fields,
            'webhooks'                => $id ? $wRepo->findByForm($id) : [],
            'conditions'              => $conditions,
            'email_routes'            => $emailRoutes,
            'mail_routing_init_json'  => $mailRoutingInitJson,
            'conditions_init_json'    => $conditionsInitJson,
            'base_url'                => $this->context->link->getAdminLink('AdminPrestaFormForms'),
            'submissions_url'         => $this->context->link->getAdminLink('AdminPrestaFormSubmissions'),
            'captcha_providers'       => ['none' => 'None', 'recaptcha_v2' => 'reCAPTCHA v2', 'recaptcha_v3' => 'reCAPTCHA v3', 'turnstile' => 'Cloudflare Turnstile'],
            'pf_tpl_dir'              => _PS_MODULE_DIR_ . 'prestaform/views/templates/admin/forms/',
        ]);

        return $this->context->smarty->fetch(_PS_MODULE_DIR_ . 'prestaform/views/templates/admin/forms/edit.tpl');
    }

    public function postProcess(): void
    {
        $action = Tools::getValue('action');

        // NOTE: method names deliberately do NOT follow the PS `process{Action}` convention.
        // AdminController::postProcess() auto-dispatches to public `process{Action}()` methods,
        // which would cause a second save/delete on every request.  Private `handle*` methods
        // are invisible to that mechanism.
        if ($action === 'save') {
            $this->handleSave();
        } elseif ($action === 'delete') {
            $this->handleDelete();
        } elseif ($action === 'save_webhooks') {
            $this->handleSaveWebhooks();
        } elseif ($action === 'save_conditions') {
            $this->handleSaveConditions();
        } elseif ($action === 'save_mail') {
            $this->handleSaveMail();
        } elseif ($action === 'test_webhook') {
            $this->handleTestWebhook();
        } elseif ($action === 'delete_webhook') {
            $this->handleDeleteWebhook();
        }

        parent::postProcess();
    }

    private function handleSave(): void
    {
        $repo = new \PrestaForm\Repository\FormRepository();
        // Tools::getValue returns false when key is absent — cast to string to
        // satisfy FormRepository::slugExists(string $slug) with strict_types=1
        $slug = (string) (Tools::getValue('slug') ?: '');
        $id   = (int) Tools::getValue('id_form');

        // Auto-generate slug from name when left blank
        if ($slug === '') {
            $name = (string) (Tools::getValue('name') ?: '');
            $slug = $this->slugify($name);
        }

        // Ensure uniqueness — append -N suffix if needed
        if ($slug !== '' && $repo->slugExists($slug, $id)) {
            $base    = $slug;
            $counter = 2;
            while ($repo->slugExists($base . '-' . $counter, $id)) {
                $counter++;
            }
            $slug = $base . '-' . $counter;
        }

        $name = (string) (Tools::getValue('name') ?: '');
        if (trim($name) === '') {
            $this->errors[] = 'Form name is required.';
            return;
        }

        $validStatuses   = ['draft', 'active'];
        $validCaptchas   = ['none', 'recaptcha_v2', 'recaptcha_v3', 'turnstile'];
        $status          = (string) (Tools::getValue('status') ?: 'draft');
        $captchaProvider = (string) (Tools::getValue('captcha_provider') ?: 'none');

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
        $savedId = $repo->save([
            'id_form'          => $id ?: null,
            'name'             => $name,
            'slug'             => $slug,
            'template'         => (string) (Tools::getValue('template') ?: ''),
            'custom_css'       => (string) (Tools::getValue('custom_css') ?: ''),
            'success_message'  => (string) (Tools::getValue('success_message') ?: ''),
            'status'           => $status,
            'captcha_provider' => $captchaProvider,
            'retention_days'   => ($retentionRaw !== '' && $retentionRaw !== false) ? (int) $retentionRaw : null,
        ]);

        // Redirect back to the edit page (PRG pattern — prevents double-submit on refresh,
        // and keeps user in context instead of landing on the forms list).
        // pf_saved=1 triggers the success banner; pf_tab restores the active tab.
        $pfTab = (string) Tools::getValue('pf_tab', '');
        \Tools::redirectAdmin(
            $this->context->link->getAdminLink('AdminPrestaFormForms') .
            '&action=edit&id_form=' . $savedId . '&pf_saved=1' .
            ($pfTab !== '' ? '&pf_tab=' . urlencode($pfTab) : '')
        );
    }

    private function handleDelete(): void
    {
        // Reject GET requests — delete must come from a POST form to prevent
        // CSRF via link/image and accidental deletion from browser pre-fetch.
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $id = (int) Tools::getValue('id_form');
        if (!$id) {
            return;
        }

        // Cascade-delete all child records before removing the form
        (new \PrestaForm\Repository\ConditionRepository())->deleteByForm($id);
        (new \PrestaForm\Repository\EmailRouteRepository())->deleteByForm($id);
        (new \PrestaForm\Repository\WebhookRepository())->deleteByForm($id);
        (new \PrestaForm\Repository\SubmissionRepository())->deleteByForm($id);
        (new \PrestaForm\Repository\FormRepository())->delete($id);

        // PRG: redirect to list with a visible deleted banner
        \Tools::redirectAdmin(
            $this->context->link->getAdminLink('AdminPrestaFormForms') . '&pf_deleted=1'
        );
    }

    private function handleSaveWebhooks(): void
    {
        $id      = (int) Tools::getValue('id_form');
        $raw     = Tools::getValue('webhooks_json', '[]');
        $decoded = json_decode($raw, true);

        // Reject malformed JSON — avoids silently saving nothing
        if (!is_array($decoded)) {
            $this->errors[] = 'Invalid webhook data.';
            return;
        }

        $repo = new \PrestaForm\Repository\WebhookRepository();
        foreach ($decoded as $w) {
            $w['id_form'] = $id;
            $repo->save($w);
        }
        $pfTab = (string) Tools::getValue('pf_tab', '');
        \Tools::redirectAdmin(
            $this->context->link->getAdminLink('AdminPrestaFormForms') .
            '&action=edit&id_form=' . $id . '&pf_saved=1' .
            ($pfTab !== '' ? '&pf_tab=' . urlencode($pfTab) : '')
        );
    }

    private function handleSaveConditions(): void
    {
        $id      = (int) Tools::getValue('id_form');
        $raw     = Tools::getValue('conditions_json', '[]');
        $decoded = json_decode($raw, true);

        if (!is_array($decoded)) {
            $this->errors[] = 'Invalid conditions data.';
            return;
        }

        (new \PrestaForm\Repository\ConditionRepository())->saveForForm($id, $decoded);
        $pfTab = (string) Tools::getValue('pf_tab', '');
        \Tools::redirectAdmin(
            $this->context->link->getAdminLink('AdminPrestaFormForms') .
            '&action=edit&id_form=' . $id . '&pf_saved=1' .
            ($pfTab !== '' ? '&pf_tab=' . urlencode($pfTab) : '')
        );
    }

    private function handleSaveMail(): void
    {
        $id = (int) Tools::getValue('id_form');

        // Parse the routing rules JSON (still dynamic — JS serialises only this part)
        $routingRulesRaw = Tools::getValue('mail_routing_json', '[]');
        $routingRules    = json_decode($routingRulesRaw, true);
        if (!is_array($routingRules)) {
            $routingRules = [];
        }

        // Helper: split a comma-separated "To" string into a clean address array
        $splitAddresses = static function (string $raw): array {
            return array_values(
                array_filter(array_map('trim', explode(',', $raw)))
            );
        };

        $routes = [
            [
                'type'               => 'admin',
                'enabled'            => 1,
                'notify_addresses'   => $splitAddresses((string) Tools::getValue('mail_admin_to',      '')),
                'from_address'       => (string) Tools::getValue('mail_admin_from',    ''),
                'subject'            => (string) Tools::getValue('mail_admin_subject', ''),
                'additional_headers' => (string) Tools::getValue('mail_admin_headers', ''),
                'body'               => (string) Tools::getValue('mail_admin_body',    ''),
                'routing_rules'      => $routingRules,
            ],
            [
                'type'               => 'confirmation',
                'enabled'            => Tools::getValue('mail_conf_enabled') ? 1 : 0,
                'notify_addresses'   => $splitAddresses((string) Tools::getValue('mail_conf_to',      '')),
                'from_address'       => (string) Tools::getValue('mail_conf_from',    ''),
                'subject'            => (string) Tools::getValue('mail_conf_subject', ''),
                'additional_headers' => (string) Tools::getValue('mail_conf_headers', ''),
                'body'               => (string) Tools::getValue('mail_conf_body',    ''),
                'routing_rules'      => [],
            ],
        ];

        (new \PrestaForm\Repository\EmailRouteRepository())->saveForForm($id, $routes);

        $pfTab = (string) Tools::getValue('pf_tab', '');
        \Tools::redirectAdmin(
            $this->context->link->getAdminLink('AdminPrestaFormForms') .
            '&action=edit&id_form=' . $id . '&pf_saved=1' .
            ($pfTab !== '' ? '&pf_tab=' . urlencode($pfTab) : '')
        );
    }

    private function handleTestWebhook(): void
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

        // Clamp timeout: minimum 1 s, maximum 30 s — prevents a rogue DB value from
        // hanging the admin request for an unbounded amount of time.
        $timeout = max(1, min(30, (int) ($webhook['timeout_seconds'] ?? 10)));

        $testPayload = ['_test' => true, 'timestamp' => date('c')];
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CUSTOMREQUEST  => $webhook['method'],
            CURLOPT_POSTFIELDS     => json_encode($testPayload),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        ]);
        $body   = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->outputJson(['success' => $status >= 200 && $status < 300, 'status' => $status, 'body' => mb_substr((string) $body, 0, 500)]);
    }

    private function handleDeleteWebhook(): void
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

    /**
     * Convert a string to a URL-safe slug.
     * e.g. "Contact Form" → "contact-form"
     */
    private function slugify(string $text): string
    {
        // Transliterate accented characters to ASCII equivalents
        $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? $text;
        $text = trim($text, '-');
        return $text ?: 'form';
    }

    private function emptyForm(): array
    {
        $sampleTemplate = implode("\n", [
            '<p>[text* your-name placeholder "Your Name"]</p>',
            '<p>[email* your-email placeholder "Email Address"]</p>',
            '<p>[tel your-phone placeholder "Phone Number"]</p>',
            '<p>[textarea* your-message rows "5" placeholder "Your message…"]</p>',
            '<p>[submit "Send Message"]</p>',
        ]);

        return [
            'id_form'          => 0,
            'name'             => '',
            'slug'             => '',
            'template'         => $sampleTemplate,
            'custom_css'       => '',
            'success_message'  => 'Thank you! Your message has been sent.',
            'status'           => 'draft',
            'captcha_provider' => 'none',
            'retention_days'   => null,
        ];
    }
}
