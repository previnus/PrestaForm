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
        $replyTo = ($route['reply_to'] ?? '') ?: \Configuration::get('PS_SHOP_EMAIL');

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
