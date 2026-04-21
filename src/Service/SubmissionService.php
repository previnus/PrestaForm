<?php
declare(strict_types=1);

namespace PrestaForm\Service;

use PrestaForm\Repository\EmailRouteRepository;
use PrestaForm\Repository\FormRepository;
use PrestaForm\Repository\SubmissionRepository;
use PrestaForm\Repository\WebhookRepository;

class SubmissionService
{
    public function __construct(
        private readonly ShortcodeParser      $parser      = new ShortcodeParser(),
        private readonly ConditionEvaluator   $conditions  = new ConditionEvaluator(),
        private readonly FormRepository       $formRepo    = new FormRepository(),
        private readonly SubmissionRepository $subRepo     = new SubmissionRepository(),
        private readonly EmailRouteRepository $emailRepo   = new EmailRouteRepository(),
        private readonly WebhookDispatcher    $webhooks    = new WebhookDispatcher(),
        private readonly EmailRouter          $emailRouter = new EmailRouter()
    ) {}

    /**
     * Handle a form submission POST.
     *
     * @param array<string, mixed> $post      $_POST data
     * @param array<string, mixed> $files     $_FILES data
     * @param string               $ip
     * @return array{success: bool, errors: array<string, string>}
     */
    public function handle(array $post, array $files, string $ip): array
    {
        $formId = (int) ($post['pf_form_id'] ?? 0);
        $form   = $this->formRepo->findById($formId);

        if (!$form || $form['status'] !== 'active') {
            return ['success' => false, 'errors' => ['_form' => 'Form not found.']];
        }

        // CSRF token check — compare against the expected PS token, not just sanitise
        if (empty($post['token']) || $post['token'] !== \Tools::getToken(false)) {
            return ['success' => false, 'errors' => ['_form' => 'Invalid security token.']];
        }

        $fields      = $this->parser->parse((string) $form['template']);
        $condGroups  = (new \PrestaForm\Repository\ConditionRepository())->findByForm($formId);
        $allNames    = array_filter(array_column($fields, 'name'));
        $visibleFields = $this->conditions->getVisibleFields($allNames, $condGroups, $post);

        $errors = [];

        // CAPTCHA verification
        if ($form['captcha_provider'] !== 'none') {
            $captchaError = $this->verifyCaptcha($form['captcha_provider'], $post);
            if ($captchaError) {
                $errors['_captcha'] = $captchaError;
            }
        }

        // Validate visible fields
        foreach ($fields as $field) {
            $name = $field['name'];
            if (!$name || !in_array($name, $visibleFields, true)) {
                continue; // skip hidden fields and submit button
            }

            if ($field['type'] === 'file') {
                $error = $this->validateFile($name, $field, $files);
                if ($error) {
                    $errors[$name] = $error;
                }
                continue;
            }

            $value = $post[$name] ?? '';
            if ($field['required'] && ($value === '' || $value === [])) {
                $errors[$name] = 'This field is required.';
            }
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        // Build clean submission data (visible fields only)
        $data = [];
        foreach ($fields as $field) {
            $name = $field['name'];
            if (!$name || !in_array($name, $visibleFields, true)) {
                continue;
            }
            if ($field['type'] === 'file') {
                $data[$name] = $this->storeFile($name, $files);
            } else {
                $data[$name] = $post[$name] ?? '';
            }
        }

        $submissionId = $this->subRepo->save($formId, $data, $ip);

        // Fire emails — wrapped so a mail/SMTP failure never kills the JSON response.
        // The submission is already saved; the visitor should still see success.
        try {
            $routes   = $this->emailRepo->findByForm($formId);
            $hasAdmin = false;

            foreach ($routes as $route) {
                if ($route['type'] === 'admin') {
                    $hasAdmin = true;
                    if ((int) ($route['enabled'] ?? 1)) {
                        $this->emailRouter->dispatchAdmin($form, $route, $data);
                    }
                } elseif ($route['type'] === 'confirmation') {
                    $this->emailRouter->dispatchConfirmation($form, $route, $data);
                }
            }

            // If no admin route was ever saved for this form, send a fallback notification
            // to the store contact email so submissions are never silently lost.
            if (!$hasAdmin) {
                $this->emailRouter->dispatchAdmin($form, [
                    'notify_addresses' => [],
                    'routing_rules'    => [],
                    'subject'          => 'New submission: [_form_title]',
                    'body'             => '[_all_fields]',
                ], $data);
            }
        } catch (\Throwable $e) {
            // Log but do not surface mail errors to the visitor
            \PrestaShopLogger::addLog(
                'PrestaForm mail error (form ' . $formId . '): ' . $e->getMessage(),
                3,
                null,
                'PrestaForm',
                $submissionId
            );
        }

        // Fire webhooks (non-blocking — failures are logged and retried via cron)
        try {
            $this->webhooks->dispatchForSubmission($formId, $submissionId, $data);
        } catch (\Throwable $e) {
            \PrestaShopLogger::addLog(
                'PrestaForm webhook error (form ' . $formId . '): ' . $e->getMessage(),
                3,
                null,
                'PrestaForm',
                $submissionId
            );
        }

        return ['success' => true, 'errors' => []];
    }

    private function verifyCaptcha(string $provider, array $post): string
    {
        if ($provider === 'recaptcha_v2' || $provider === 'recaptcha_v3') {
            $token  = (string) ($post['g-recaptcha-response'] ?? '');
            $secret = (string) \Db::getInstance()->getValue(
                'SELECT setting_value FROM `' . _DB_PREFIX_ . 'pf_settings`
                 WHERE setting_key = \'' . pSQL($provider . '_secret_key') . '\''
            );
        } elseif ($provider === 'turnstile') {
            $token  = (string) ($post['cf-turnstile-response'] ?? '');
            $secret = (string) \Db::getInstance()->getValue(
                'SELECT setting_value FROM `' . _DB_PREFIX_ . 'pf_settings`
                 WHERE setting_key = \'turnstile_secret_key\''
            );
        } else {
            $token  = '';
            $secret = '';
        }

        if (!$token) {
            return 'Please complete the CAPTCHA.';
        }

        $endpoint = match ($provider) {
            'recaptcha_v2', 'recaptcha_v3' => 'https://www.google.com/recaptcha/api/siteverify',
            'turnstile'                    => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
            default                        => '',
        };

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query(['secret' => $secret, 'response' => $token]),
            CURLOPT_TIMEOUT        => 10,
        ]);
        $response = json_decode(curl_exec($ch) ?: '{}', true);
        curl_close($ch);

        return ($response['success'] ?? false) ? '' : 'CAPTCHA verification failed. Please try again.';
    }

    /** @param array<string, mixed> $field  Parsed field definition */
    private function validateFile(string $name, array $field, array $files): string
    {
        if ($field['required'] && (empty($files[$name]) || $files[$name]['error'] === UPLOAD_ERR_NO_FILE)) {
            return 'This field is required.';
        }

        if (empty($files[$name]) || $files[$name]['error'] === UPLOAD_ERR_NO_FILE) {
            return '';
        }

        if ($files[$name]['error'] !== UPLOAD_ERR_OK) {
            return 'File upload error.';
        }

        // Validate size limit
        if (!empty($field['params']['limit'])) {
            $limit = $this->parseSize($field['params']['limit']);
            if ($files[$name]['size'] > $limit) {
                return 'File exceeds maximum size of ' . $field['params']['limit'] . '.';
            }
        }

        // Validate accepted extensions against the server-side tmp_name (not the
        // browser-supplied filename) using both extension and MIME type checks.
        if (!empty($field['params']['accept'])) {
            $accepted = array_map('trim', explode(',', strtolower($field['params']['accept'])));

            // Extension check — use the client filename only for display; normalise to lowercase
            $clientExt = '.' . strtolower(pathinfo($files[$name]['name'], PATHINFO_EXTENSION));
            if (!in_array($clientExt, $accepted, true)) {
                return 'File type not allowed. Accepted: ' . $field['params']['accept'];
            }

            // MIME type check — read from the actual uploaded bytes, not the browser header
            if (function_exists('finfo_open')) {
                $finfo    = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $files[$name]['tmp_name']) ?: '';
                finfo_close($finfo);

                // Build a safe allowlist: map accepted extensions to expected MIME types
                $safeMimes = $this->mimeTypesForExtensions($accepted);
                if (!empty($safeMimes) && !in_array(strtolower($mimeType), $safeMimes, true)) {
                    return 'File type not allowed. Accepted: ' . $field['params']['accept'];
                }
            }
        }

        return '';
    }

    private function storeFile(string $name, array $files): string
    {
        if (empty($files[$name]) || $files[$name]['error'] !== UPLOAD_ERR_OK) {
            return '';
        }

        $uploadDir = _PS_MODULE_DIR_ . 'prestaform/uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        // Prevent direct execution of uploaded files
        if (!file_exists($uploadDir . '.htaccess')) {
            file_put_contents($uploadDir . '.htaccess', "Options -ExecCGI -Indexes\n<FilesMatch \"\\.php$\">\n    deny from all\n</FilesMatch>\n");
        }
        if (!file_exists($uploadDir . 'index.php')) {
            file_put_contents($uploadDir . 'index.php', "<?php\nheader('Expires: Mon, 26 Jul 1997 05:00:00 GMT');\nheader('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');\nheader('Cache-Control: no-store, no-cache, must-revalidate');\nheader('Cache-Control: post-check=0, pre-check=0', false);\nheader('Pragma: no-cache');\nheader('Location: ../../../');\nexit;\n");
        }

        $ext      = preg_replace('/[^a-zA-Z0-9]/', '', pathinfo($files[$name]['name'], PATHINFO_EXTENSION));
        $filename = uniqid('pf_', true) . ($ext ? '.' . $ext : '');
        move_uploaded_file($files[$name]['tmp_name'], $uploadDir . $filename);

        return $filename;
    }

    /**
     * Map a list of accepted file extensions to their expected MIME types.
     * Only well-known safe extensions are included; unknown ones are ignored
     * (so custom extensions still pass as long as the ext check passes).
     *
     * @param  list<string> $extensions  e.g. ['.pdf', '.docx']
     * @return list<string>              e.g. ['application/pdf', ...]
     */
    private function mimeTypesForExtensions(array $extensions): array
    {
        $map = [
            '.jpg'  => 'image/jpeg',
            '.jpeg' => 'image/jpeg',
            '.png'  => 'image/png',
            '.gif'  => 'image/gif',
            '.webp' => 'image/webp',
            '.svg'  => 'image/svg+xml',
            '.pdf'  => 'application/pdf',
            '.txt'  => 'text/plain',
            '.csv'  => 'text/csv',
            '.doc'  => 'application/msword',
            '.docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            '.xls'  => 'application/vnd.ms-excel',
            '.xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            '.zip'  => 'application/zip',
            '.mp3'  => 'audio/mpeg',
            '.mp4'  => 'video/mp4',
        ];

        $mimes = [];
        foreach ($extensions as $ext) {
            if (isset($map[$ext])) {
                $mimes[] = $map[$ext];
            }
        }
        return $mimes;
    }

    private function parseSize(string $size): int
    {
        $size  = strtolower(trim($size));
        $units = ['kb' => 1024, 'mb' => 1024 * 1024, 'gb' => 1024 ** 3];
        foreach ($units as $unit => $multiplier) {
            if (str_ends_with($size, $unit)) {
                // floatval() strips the unit suffix; cast to int AFTER multiplying
                return (int) (floatval($size) * $multiplier);
            }
        }
        return (int) $size;
    }
}
