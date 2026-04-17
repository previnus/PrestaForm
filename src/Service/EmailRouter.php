<?php
declare(strict_types=1);

namespace PrestaForm\Service;

class EmailRouter
{
    /**
     * Render a subject/body template, substituting [field] and system variables.
     *
     * Special placeholders:
     *   [field-name]   — replaced with the submitted value of that field
     *   [_form_title]  — replaced with the form name
     *   [_date]        — replaced with the submission date/time
     *   [_ip]          — replaced with the submitter's IP address
     *   [_all_fields]  — replaced with an HTML table of all submitted fields and values
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
        $vars['[_all_fields]'] = $this->buildAllFieldsTable($submission);
        $vars['[_shop_name]']  = (string) \Configuration::get('PS_SHOP_NAME');
        $vars['[_shop_email]'] = (string) \Configuration::get('PS_SHOP_EMAIL');

        return strtr($template, $vars);
    }

    /**
     * Build a simple HTML table of all submitted field→value pairs.
     *
     * @param array<string, mixed> $submission
     */
    private function buildAllFieldsTable(array $submission): string
    {
        if (empty($submission)) {
            return '';
        }

        $rows = '';
        foreach ($submission as $key => $value) {
            $displayValue = is_array($value) ? implode(', ', $value) : (string) $value;
            $rows .= '<tr>'
                . '<td style="padding:4px 8px;font-weight:bold;white-space:nowrap">'
                . htmlspecialchars($key) . '</td>'
                . '<td style="padding:4px 8px">'
                . nl2br(htmlspecialchars($displayValue)) . '</td>'
                . '</tr>';
        }

        return '<table style="border-collapse:collapse;width:100%">'
            . '<thead><tr>'
            . '<th style="padding:4px 8px;text-align:left;border-bottom:1px solid #ccc">Field</th>'
            . '<th style="padding:4px 8px;text-align:left;border-bottom:1px solid #ccc">Value</th>'
            . '</tr></thead>'
            . '<tbody>' . $rows . '</tbody>'
            . '</table>';
    }

    /**
     * Resolve the final list of notify email addresses, applying conditional routing rules
     * and substituting [field] variables in address strings.
     * Falls back to the store's contact email when no addresses are configured.
     *
     * @param array<string, mixed> $route       Row from pf_email_routes
     * @param array<string, mixed> $submission  Submitted field values
     * @return list<string>
     */
    public function resolveAdminAddresses(array $route, array $submission): array
    {
        $addresses = $this->decodeAddresses($route['notify_addresses']);

        // Apply conditional routing rules — first matching rule overrides addresses
        $rules = $route['routing_rules'] ?? null;
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
                    break;
                }
            }
        }

        // Substitute [field] variables in address strings
        $addresses = array_map(
            fn(string $addr): string => $this->renderTemplate($addr, $submission, []),
            $addresses
        );

        // Filter to valid addresses only
        $addresses = array_values(array_filter($addresses, fn(string $a): bool =>
            filter_var($a, FILTER_VALIDATE_EMAIL) !== false
        ));

        // Fall back to the store contact email so admins always get notified
        if (empty($addresses)) {
            $storeEmail = (string) \Configuration::get('PS_SHOP_EMAIL');
            if ($storeEmail) {
                $addresses = [$storeEmail];
            }
        }

        return $addresses;
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

        [$fromEmail, $fromName] = $this->parseFromAddress(
            $this->renderTemplate((string) ($route['from_address'] ?? ''), $submission, $form)
        );
        $parsed  = $this->parseAdditionalHeaders(
            $this->renderTemplate((string) ($route['additional_headers'] ?? ''), $submission, $form)
        );
        $replyTo = $parsed['reply_to'] ?: ((string) ($route['reply_to'] ?? '')) ?: null;
        $bcc     = $parsed['bcc'] ?: null;

        foreach ($addresses as $address) {
            \Mail::Send(
                (int) \Configuration::get('PS_LANG_DEFAULT'),
                'prestaform_notification',
                $subject,
                ['{message}' => nl2br(htmlspecialchars($body))],
                $address,
                null,
                $fromEmail ?: (string) \Configuration::get('PS_SHOP_EMAIL'),
                $fromName  ?: (string) \Configuration::get('PS_SHOP_NAME'),
                null,
                null,
                __DIR__ . '/../../views/templates/mail/',
                false,
                null,
                $bcc,
                $replyTo
            );
        }
    }

    /**
     * Dispatch submitter confirmation email.
     *
     * The confirmation recipient is resolved in this priority order:
     *  1. The `notify_addresses` field on the route (supports [field-name] references,
     *     e.g. set it to "[your-email]" to pull the address from the submission)
     *  2. Auto-detection: the first valid email address found in the submission data
     *
     * @param array<string, mixed> $form
     * @param array<string, mixed> $route      Row from pf_email_routes (type='confirmation')
     * @param array<string, mixed> $submission
     */
    public function dispatchConfirmation(array $form, array $route, array $submission): void
    {
        if (!(int) ($route['enabled'] ?? 0)) {
            return;
        }

        // 1. Try the configured notify_addresses (supports [field-name] substitution)
        $toEmail = '';
        $configured = $this->decodeAddresses($route['notify_addresses']);
        foreach ($configured as $addr) {
            $resolved = trim($this->renderTemplate($addr, $submission, []));
            if (filter_var($resolved, FILTER_VALIDATE_EMAIL)) {
                $toEmail = $resolved;
                break;
            }
        }

        // 2. Fall back: scan submission data for the first valid email value
        if (!$toEmail) {
            foreach ($submission as $value) {
                if (is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $toEmail = $value;
                    break;
                }
            }
        }

        if (!$toEmail) {
            return;
        }

        $subject = $this->renderTemplate((string) ($route['subject'] ?? ''), $submission, $form);
        $body    = $this->renderTemplate((string) ($route['body']    ?? ''), $submission, $form);

        [$fromEmail, $fromName] = $this->parseFromAddress(
            $this->renderTemplate((string) ($route['from_address'] ?? ''), $submission, $form)
        );
        $parsed  = $this->parseAdditionalHeaders(
            $this->renderTemplate((string) ($route['additional_headers'] ?? ''), $submission, $form)
        );
        $replyTo = $parsed['reply_to']
            ?: ((string) ($route['reply_to'] ?? ''))
            ?: (string) \Configuration::get('PS_SHOP_EMAIL');
        $bcc     = $parsed['bcc'] ?: null;

        \Mail::Send(
            (int) \Configuration::get('PS_LANG_DEFAULT'),
            'prestaform_confirmation',
            $subject,
            ['{message}' => nl2br(htmlspecialchars($body))],
            $toEmail,
            null,
            $fromEmail ?: (string) \Configuration::get('PS_SHOP_EMAIL'),
            $fromName  ?: (string) \Configuration::get('PS_SHOP_NAME'),
            null,
            null,
            __DIR__ . '/../../views/templates/mail/',
            false,
            null,
            $bcc,
            $replyTo
        );
    }

    /**
     * Parse "Display Name <email@domain.com>" or plain "email@domain.com".
     * Returns [emailAddress, displayName].
     *
     * @return array{0: string, 1: string}
     */
    private function parseFromAddress(string $raw): array
    {
        $raw = trim($raw);
        if (preg_match('/^(.+?)\s*<([^>]+)>$/', $raw, $m)) {
            return [trim($m[2]), trim($m[1])];
        }
        return [$raw, ''];
    }

    /**
     * Parse additional headers (one per line) and extract Reply-To and Bcc values.
     *
     * @return array{reply_to: string, bcc: string}
     */
    private function parseAdditionalHeaders(string $headers): array
    {
        $result = ['reply_to' => '', 'bcc' => ''];
        foreach (explode("\n", $headers) as $line) {
            $line = trim($line);
            if (stripos($line, 'Reply-To:') === 0) {
                $result['reply_to'] = trim(substr($line, 9));
            } elseif (stripos($line, 'Bcc:') === 0) {
                $result['bcc'] = trim(substr($line, 4));
            }
        }
        return $result;
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
