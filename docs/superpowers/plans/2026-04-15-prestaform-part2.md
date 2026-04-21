# PrestaForm Module Implementation Plan — Part 2: Repositories, Webhooks & Submissions

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement the repository layer, WebhookDispatcher, RetentionService, SubmissionService, and the front-end SubmitController that ties everything together.

**Prerequisites:** Part 1 complete (all service classes and entities exist).

**Architecture:** Repositories wrap raw `Db::getInstance()` queries. `SubmissionService` orchestrates validation → save → email → webhook. `WebhookDispatcher` uses cURL with retry logging.

**Tech Stack:** PHP 8.1+, PrestaShop 9, cURL.

---

### Task 9: Repositories

**Files:**
- Create: `src/Repository/FormRepository.php`
- Create: `src/Repository/SubmissionRepository.php`
- Create: `src/Repository/ConditionRepository.php`
- Create: `src/Repository/WebhookRepository.php`
- Create: `src/Repository/EmailRouteRepository.php`

- [ ] **Step 1: Create src/Repository/FormRepository.php**

```php
<?php
declare(strict_types=1);

namespace PrestaForm\Repository;

class FormRepository
{
    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $row = \Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . 'pf_forms` WHERE `id_form` = ' . (int) $id
        );
        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    public function findBySlug(string $slug): ?array
    {
        $row = \Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . 'pf_forms`
             WHERE `slug` = \'' . pSQL($slug) . '\''
        );
        return $row ?: null;
    }

    /** @return list<array<string, mixed>> */
    public function findAll(): array
    {
        return \Db::getInstance()->executeS(
            'SELECT f.*,
                (SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'pf_submissions` s WHERE s.id_form = f.id_form) AS submission_count
             FROM `' . _DB_PREFIX_ . 'pf_forms` f
             ORDER BY f.date_add DESC'
        ) ?: [];
    }

    public function save(array $data): int
    {
        $now = date('Y-m-d H:i:s');

        if (!empty($data['id_form'])) {
            $id = (int) $data['id_form'];
            unset($data['id_form']);
            $data['date_upd'] = $now;
            \Db::getInstance()->update('pf_forms', $this->escape($data), 'id_form = ' . $id);
            return $id;
        }

        $data['date_add'] = $now;
        $data['date_upd'] = $now;
        \Db::getInstance()->insert('pf_forms', $this->escape($data));
        return (int) \Db::getInstance()->Insert_ID();
    }

    public function delete(int $id): bool
    {
        return (bool) \Db::getInstance()->delete('pf_forms', 'id_form = ' . $id);
    }

    public function slugExists(string $slug, int $excludeId = 0): bool
    {
        $where = '`slug` = \'' . pSQL($slug) . '\'';
        if ($excludeId > 0) {
            $where .= ' AND `id_form` != ' . $excludeId;
        }
        return (bool) \Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'pf_forms` WHERE ' . $where
        );
    }

    /** @param array<string, mixed> $data */
    private function escape(array $data): array
    {
        $out = [];
        foreach ($data as $k => $v) {
            $out[$k] = is_null($v) ? null : (is_int($v) ? $v : pSQL((string) $v));
        }
        return $out;
    }
}
```

- [ ] **Step 2: Create src/Repository/SubmissionRepository.php**

```php
<?php
declare(strict_types=1);

namespace PrestaForm\Repository;

class SubmissionRepository
{
    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $row = \Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . 'pf_submissions` WHERE `id_submission` = ' . (int) $id
        );
        if (!$row) return null;
        $row['data'] = json_decode($row['data'], true) ?? [];
        return $row;
    }

    /**
     * @param array<string, mixed> $filters  Keys: id_form, search, date_from, date_to
     * @return list<array<string, mixed>>
     */
    public function findAll(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $where = ['1=1'];

        if (!empty($filters['id_form'])) {
            $where[] = 's.id_form = ' . (int) $filters['id_form'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = 's.date_add >= \'' . pSQL($filters['date_from']) . '\'';
        }
        if (!empty($filters['date_to'])) {
            $where[] = 's.date_add <= \'' . pSQL($filters['date_to']) . ' 23:59:59\'';
        }

        $whereStr = implode(' AND ', $where);

        $rows = \Db::getInstance()->executeS(
            'SELECT s.*, f.name AS form_name
             FROM `' . _DB_PREFIX_ . 'pf_submissions` s
             LEFT JOIN `' . _DB_PREFIX_ . 'pf_forms` f ON f.id_form = s.id_form
             WHERE ' . $whereStr . '
             ORDER BY s.date_add DESC
             LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset
        ) ?: [];

        foreach ($rows as &$row) {
            $row['data'] = json_decode($row['data'], true) ?? [];
        }

        return $rows;
    }

    public function countAll(array $filters = []): int
    {
        $where = ['1=1'];
        if (!empty($filters['id_form'])) {
            $where[] = 'id_form = ' . (int) $filters['id_form'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'date_add >= \'' . pSQL($filters['date_from']) . '\'';
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'date_add <= \'' . pSQL($filters['date_to']) . ' 23:59:59\'';
        }
        return (int) \Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'pf_submissions` WHERE ' . implode(' AND ', $where)
        );
    }

    /** @param array<string, mixed> $data  Decoded field values */
    public function save(int $formId, array $data, string $ip): int
    {
        \Db::getInstance()->insert('pf_submissions', [
            'id_form'    => $formId,
            'data'       => pSQL(json_encode($data, JSON_UNESCAPED_UNICODE)),
            'ip_address' => pSQL($ip),
            'date_add'   => date('Y-m-d H:i:s'),
        ]);
        return (int) \Db::getInstance()->Insert_ID();
    }

    public function delete(int $id): bool
    {
        return (bool) \Db::getInstance()->delete('pf_submissions', 'id_submission = ' . $id);
    }

    public function deleteExpired(int $formId, int $retentionDays): int
    {
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$retentionDays} days"));
        \Db::getInstance()->delete(
            'pf_submissions',
            'id_form = ' . $formId . ' AND date_add < \'' . pSQL($cutoff) . '\''
        );
        return (int) \Db::getInstance()->Affected_Rows();
    }

    /** @return list<array<string, mixed>> All submissions for a form (decoded), for CSV export */
    public function findAllForExport(int $formId): array
    {
        $rows = \Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . 'pf_submissions`
             WHERE id_form = ' . $formId . '
             ORDER BY date_add DESC'
        ) ?: [];

        foreach ($rows as &$row) {
            $row['data'] = json_decode($row['data'], true) ?? [];
        }

        return $rows;
    }
}
```

- [ ] **Step 3: Create src/Repository/ConditionRepository.php**

```php
<?php
declare(strict_types=1);

namespace PrestaForm\Repository;

class ConditionRepository
{
    /** @return list<array<string, mixed>> */
    public function findByForm(int $formId): array
    {
        $rows = \Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . 'pf_conditions`
             WHERE id_form = ' . (int) $formId
        ) ?: [];

        foreach ($rows as &$row) {
            $row['rules'] = json_decode($row['rules'], true) ?? [];
        }

        return $rows;
    }

    public function deleteByForm(int $formId): void
    {
        \Db::getInstance()->delete('pf_conditions', 'id_form = ' . (int) $formId);
    }

    /** @param list<array<string, mixed>> $groups */
    public function saveForForm(int $formId, array $groups): void
    {
        $this->deleteByForm($formId);

        foreach ($groups as $group) {
            \Db::getInstance()->insert('pf_conditions', [
                'id_form'      => $formId,
                'target_field' => pSQL((string) ($group['target_field'] ?? '')),
                'action'       => pSQL((string) ($group['action']       ?? 'show')),
                'logic'        => pSQL((string) ($group['logic']        ?? 'AND')),
                'rules'        => pSQL(json_encode($group['rules'] ?? [], JSON_UNESCAPED_UNICODE)),
            ]);
        }
    }
}
```

- [ ] **Step 4: Create src/Repository/WebhookRepository.php**

```php
<?php
declare(strict_types=1);

namespace PrestaForm\Repository;

class WebhookRepository
{
    /** @return list<array<string, mixed>> */
    public function findByForm(int $formId): array
    {
        $rows = \Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . 'pf_webhooks`
             WHERE id_form = ' . (int) $formId . ' ORDER BY id_webhook ASC'
        ) ?: [];

        foreach ($rows as &$row) {
            $row['headers']   = json_decode($row['headers'],   true) ?? [];
            $row['field_map'] = $row['field_map'] ? json_decode($row['field_map'], true) : null;
        }

        return $rows;
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $row = \Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . 'pf_webhooks` WHERE id_webhook = ' . (int) $id
        );
        if (!$row) return null;
        $row['headers']   = json_decode($row['headers'],   true) ?? [];
        $row['field_map'] = $row['field_map'] ? json_decode($row['field_map'], true) : null;
        return $row;
    }

    /** @param array<string, mixed> $data */
    public function save(array $data): int
    {
        $row = [
            'id_form'         => (int) ($data['id_form']         ?? 0),
            'name'            => pSQL((string) ($data['name']    ?? '')),
            'url'             => pSQL((string) ($data['url']     ?? '')),
            'method'          => pSQL((string) ($data['method']  ?? 'POST')),
            'headers'         => pSQL(json_encode($data['headers'] ?? [], JSON_UNESCAPED_UNICODE)),
            'field_map'       => isset($data['field_map'])
                ? pSQL(json_encode($data['field_map'], JSON_UNESCAPED_UNICODE))
                : null,
            'retry_count'     => (int) ($data['retry_count']     ?? 3),
            'timeout_seconds' => (int) ($data['timeout_seconds'] ?? 10),
            'active'          => (int) ($data['active']          ?? 1),
        ];

        if (!empty($data['id_webhook'])) {
            $id = (int) $data['id_webhook'];
            \Db::getInstance()->update('pf_webhooks', $row, 'id_webhook = ' . $id);
            return $id;
        }

        \Db::getInstance()->insert('pf_webhooks', $row);
        return (int) \Db::getInstance()->Insert_ID();
    }

    public function delete(int $id): bool
    {
        \Db::getInstance()->delete('pf_webhook_log', 'id_webhook = ' . $id);
        return (bool) \Db::getInstance()->delete('pf_webhooks', 'id_webhook = ' . $id);
    }

    public function logAttempt(int $webhookId, int $submissionId, int $attempt, ?int $status, string $body, bool $success): void
    {
        \Db::getInstance()->insert('pf_webhook_log', [
            'id_webhook'    => $webhookId,
            'id_submission' => $submissionId,
            'attempt'       => $attempt,
            'http_status'   => $status,
            'response_body' => pSQL(mb_substr($body, 0, 2000)),
            'success'       => (int) $success,
            'date_add'      => date('Y-m-d H:i:s'),
        ]);
    }

    /** @return list<array<string, mixed>> Pending retry entries */
    public function findPendingRetries(): array
    {
        return \Db::getInstance()->executeS(
            'SELECT l.*, w.url, w.method, w.headers, w.field_map, w.retry_count, w.timeout_seconds,
                    s.data AS submission_data, s.id_form
             FROM `' . _DB_PREFIX_ . 'pf_webhook_log` l
             JOIN `' . _DB_PREFIX_ . 'pf_webhooks`    w ON w.id_webhook    = l.id_webhook
             JOIN `' . _DB_PREFIX_ . 'pf_submissions` s ON s.id_submission = l.id_submission
             WHERE l.success = 0
               AND l.attempt < w.retry_count
             ORDER BY l.date_add ASC
             LIMIT 100'
        ) ?: [];
    }

    /** @return array<string, mixed>|null Most recent log entry for a webhook+submission pair */
    public function getLastLog(int $webhookId, int $submissionId): ?array
    {
        $row = \Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . 'pf_webhook_log`
             WHERE id_webhook = ' . (int) $webhookId . '
               AND id_submission = ' . (int) $submissionId . '
             ORDER BY attempt DESC LIMIT 1'
        );
        return $row ?: null;
    }
}
```

- [ ] **Step 5: Create src/Repository/EmailRouteRepository.php**

```php
<?php
declare(strict_types=1);

namespace PrestaForm\Repository;

class EmailRouteRepository
{
    /** @return list<array<string, mixed>> */
    public function findByForm(int $formId): array
    {
        $rows = \Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . 'pf_email_routes`
             WHERE id_form = ' . (int) $formId
        ) ?: [];

        foreach ($rows as &$row) {
            $row['notify_addresses'] = json_decode($row['notify_addresses'], true) ?? [];
            $row['routing_rules']    = $row['routing_rules']
                ? (json_decode($row['routing_rules'], true) ?? [])
                : [];
        }

        return $rows;
    }

    public function deleteByForm(int $formId): void
    {
        \Db::getInstance()->delete('pf_email_routes', 'id_form = ' . (int) $formId);
    }

    /** @param list<array<string, mixed>> $routes */
    public function saveForForm(int $formId, array $routes): void
    {
        $this->deleteByForm($formId);

        foreach ($routes as $route) {
            \Db::getInstance()->insert('pf_email_routes', [
                'id_form'          => $formId,
                'type'             => pSQL((string) ($route['type']    ?? 'admin')),
                'enabled'          => (int) ($route['enabled']         ?? 1),
                'notify_addresses' => pSQL(json_encode($route['notify_addresses'] ?? [], JSON_UNESCAPED_UNICODE)),
                'reply_to'         => isset($route['reply_to']) ? pSQL($route['reply_to']) : null,
                'subject'          => pSQL((string) ($route['subject'] ?? '')),
                'body'             => pSQL((string) ($route['body']    ?? '')),
                'routing_rules'    => !empty($route['routing_rules'])
                    ? pSQL(json_encode($route['routing_rules'], JSON_UNESCAPED_UNICODE))
                    : null,
            ]);
        }
    }
}
```

- [ ] **Step 6: Commit**

```bash
git add src/Repository/
git commit -m "feat: add repository layer for all pf_ tables"
```

---

### Task 10: WebhookDispatcher

**Files:**
- Create: `src/Service/WebhookDispatcher.php`

- [ ] **Step 1: Create src/Service/WebhookDispatcher.php**

```php
<?php
declare(strict_types=1);

namespace PrestaForm\Service;

use PrestaForm\Repository\WebhookRepository;

class WebhookDispatcher
{
    public function __construct(
        private readonly WebhookRepository $repo = new WebhookRepository(),
        private ?callable $httpCallback = null
    ) {
        if ($this->httpCallback === null) {
            $this->httpCallback = $this->curlRequest(...);
        }
    }

    /**
     * Dispatch all active webhooks for a form immediately after submission.
     *
     * @param array<string, mixed> $submission Decoded field values
     */
    public function dispatchForSubmission(int $formId, int $submissionId, array $submission): void
    {
        $webhooks = $this->repo->findByForm($formId);

        foreach ($webhooks as $webhook) {
            if (!(int) $webhook['active']) {
                continue;
            }
            $this->fireWebhook($webhook, $submissionId, $submission, 1);
        }
    }

    /** Retry all pending failed webhook dispatches (called from cron). */
    public function retryPending(): void
    {
        $pending = $this->repo->findPendingRetries();

        foreach ($pending as $entry) {
            $webhook = [
                'id_webhook'      => (int) $entry['id_webhook'],
                'url'             => $entry['url'],
                'method'          => $entry['method'],
                'headers'         => is_string($entry['headers'])
                    ? (json_decode($entry['headers'], true) ?? [])
                    : ($entry['headers'] ?? []),
                'field_map'       => $entry['field_map']
                    ? (is_string($entry['field_map'])
                        ? json_decode($entry['field_map'], true)
                        : $entry['field_map'])
                    : null,
                'retry_count'     => (int) $entry['retry_count'],
                'timeout_seconds' => (int) $entry['timeout_seconds'],
            ];

            $submissionData = is_string($entry['submission_data'])
                ? (json_decode($entry['submission_data'], true) ?? [])
                : $entry['submission_data'];

            $nextAttempt = (int) $entry['attempt'] + 1;
            $this->fireWebhook($webhook, (int) $entry['id_submission'], $submissionData, $nextAttempt);
        }
    }

    /**
     * Fire a single webhook call.
     *
     * @param array<string, mixed> $webhook
     * @param array<string, mixed> $submission
     */
    private function fireWebhook(array $webhook, int $submissionId, array $submission, int $attempt): void
    {
        $payload   = $this->buildPayload($webhook, $submission);
        $headers   = $this->buildHeaders($webhook['headers'] ?? []);
        $timeout   = (int) ($webhook['timeout_seconds'] ?? 10);

        [$status, $body] = ($this->httpCallback)(
            (string) $webhook['url'],
            (string) ($webhook['method'] ?? 'POST'),
            $headers,
            json_encode($payload, JSON_UNESCAPED_UNICODE),
            $timeout
        );

        $success = $status >= 200 && $status < 300;

        $this->repo->logAttempt(
            (int) $webhook['id_webhook'],
            $submissionId,
            $attempt,
            $status ?: null,
            $body,
            $success
        );
    }

    /**
     * Apply field_map filter and build the payload array.
     *
     * @param array<string, mixed>      $webhook
     * @param array<string, mixed>      $submission
     * @return array<string, mixed>
     */
    private function buildPayload(array $webhook, array $submission): array
    {
        $fieldMap = $webhook['field_map'] ?? null;

        if ($fieldMap === null || !is_array($fieldMap)) {
            return $submission;
        }

        return array_intersect_key($submission, array_flip($fieldMap));
    }

    /**
     * @param list<array{key: string, value: string}> $headerDefs
     * @return list<string>  ["Key: Value", ...]
     */
    private function buildHeaders(array $headerDefs): array
    {
        $out = ['Content-Type: application/json', 'Accept: application/json'];
        foreach ($headerDefs as $h) {
            if (!empty($h['key']) && !empty($h['value'])) {
                $out[] = $h['key'] . ': ' . $h['value'];
            }
        }
        return $out;
    }

    /**
     * Real cURL HTTP request. Injected callback in tests.
     *
     * @param list<string> $headers
     * @return array{0: int, 1: string}  [http_status, response_body]
     */
    private function curlRequest(string $url, string $method, array $headers, string $body, int $timeout): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
        ]);

        $response   = curl_exec($ch);
        $httpStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [$httpStatus, is_string($response) ? $response : ''];
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add src/Service/WebhookDispatcher.php
git commit -m "feat: implement WebhookDispatcher with cURL, field mapping, and retry support"
```

---

### Task 11: RetentionService & SubmissionService

**Files:**
- Create: `src/Service/RetentionService.php`
- Create: `src/Service/SubmissionService.php`

- [ ] **Step 1: Create src/Service/RetentionService.php**

```php
<?php
declare(strict_types=1);

namespace PrestaForm\Service;

use PrestaForm\Repository\FormRepository;
use PrestaForm\Repository\SubmissionRepository;

class RetentionService
{
    public function __construct(
        private readonly FormRepository       $formRepo       = new FormRepository(),
        private readonly SubmissionRepository $submissionRepo = new SubmissionRepository()
    ) {}

    /** Called from hookActionCronJob daily. Deletes expired submissions for all forms. */
    public function purgeExpired(): void
    {
        $defaultDays = (int) \Db::getInstance()->getValue(
            'SELECT setting_value FROM `' . _DB_PREFIX_ . 'pf_settings`
             WHERE setting_key = \'default_retention_days\''
        );

        $forms = $this->formRepo->findAll();

        foreach ($forms as $form) {
            $days = $form['retention_days'] !== null
                ? (int) $form['retention_days']
                : $defaultDays;

            if ($days > 0) {
                $this->submissionRepo->deleteExpired((int) $form['id_form'], $days);
            }
        }
    }
}
```

- [ ] **Step 2: Create src/Service/SubmissionService.php**

```php
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

        // CSRF token check
        if (empty($post['token']) || !\Tools::validate($post['token'], false)) {
            return ['success' => false, 'errors' => ['_form' => 'Invalid token.']];
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

        // Fire emails
        $routes = $this->emailRepo->findByForm($formId);
        foreach ($routes as $route) {
            if ($route['type'] === 'admin' && (int) $route['enabled']) {
                $this->emailRouter->dispatchAdmin($form, $route, $data);
            } elseif ($route['type'] === 'confirmation') {
                $this->emailRouter->dispatchConfirmation($form, $route, $data);
            }
        }

        // Fire webhooks (non-blocking — failures are logged and retried via cron)
        $this->webhooks->dispatchForSubmission($formId, $submissionId, $data);

        return ['success' => true, 'errors' => []];
    }

    private function verifyCaptcha(string $provider, array $post): string
    {
        $token = '';
        $secret = '';

        match ($provider) {
            'recaptcha_v2', 'recaptcha_v3' => [
                $token  = (string) ($post['g-recaptcha-response'] ?? ''),
                $secret = (string) \Db::getInstance()->getValue(
                    'SELECT setting_value FROM `' . _DB_PREFIX_ . 'pf_settings`
                     WHERE setting_key = \'' . pSQL($provider . '_secret_key') . '\''
                ),
            ],
            'turnstile' => [
                $token  = (string) ($post['cf-turnstile-response'] ?? ''),
                $secret = (string) \Db::getInstance()->getValue(
                    'SELECT setting_value FROM `' . _DB_PREFIX_ . 'pf_settings`
                     WHERE setting_key = \'turnstile_secret_key\''
                ),
            ],
            default => null,
        };

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

        // Validate accepted extensions
        if (!empty($field['params']['accept'])) {
            $ext      = '.' . strtolower(pathinfo($files[$name]['name'], PATHINFO_EXTENSION));
            $accepted = array_map('trim', explode(',', strtolower($field['params']['accept'])));
            if (!in_array($ext, $accepted, true)) {
                return 'File type not allowed. Accepted: ' . $field['params']['accept'];
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

        $ext      = pathinfo($files[$name]['name'], PATHINFO_EXTENSION);
        $filename = uniqid('pf_', true) . '.' . $ext;
        move_uploaded_file($files[$name]['tmp_name'], $uploadDir . $filename);

        return $filename;
    }

    private function parseSize(string $size): int
    {
        $size  = strtolower(trim($size));
        $units = ['kb' => 1024, 'mb' => 1024 * 1024, 'gb' => 1024 ** 3];
        foreach ($units as $unit => $multiplier) {
            if (str_ends_with($size, $unit)) {
                return (int) $size * $multiplier;
            }
        }
        return (int) $size;
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add src/Service/RetentionService.php src/Service/SubmissionService.php
git commit -m "feat: implement RetentionService and SubmissionService orchestration"
```

---

### Task 12: Front-End Submit Controller

**Files:**
- Create: `controllers/front/SubmitController.php`

- [ ] **Step 1: Create controllers/front/SubmitController.php**

```php
<?php
declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

class PrestaformSubmitModuleFrontController extends ModuleFrontController
{
    public bool $ajax = true;

    public function postProcess(): void
    {
        if (!$this->isTokenValid()) {
            $this->outputJson(['success' => false, 'errors' => ['_form' => 'Invalid token.']]);
            return;
        }

        $service = new \PrestaForm\Service\SubmissionService();
        $result  = $service->handle(
            \Tools::getAllValues(),
            $_FILES,
            \Tools::getRemoteAddr()
        );

        // If form contains file field, form POSTs normally (not AJAX).
        // Redirect to current page with success query param.
        if ($this->isMultipart()) {
            $redirectUrl = \Tools::getReferer();
            if ($result['success']) {
                \Tools::redirectLink($redirectUrl . (str_contains($redirectUrl, '?') ? '&' : '?') . 'pf_success=1');
            } else {
                \Tools::redirectLink($redirectUrl . (str_contains($redirectUrl, '?') ? '&' : '?') . 'pf_error=1');
            }
            return;
        }

        $this->outputJson($result);
    }

    private function isMultipart(): bool
    {
        $ct = $_SERVER['CONTENT_TYPE'] ?? '';
        return str_contains($ct, 'multipart/form-data');
    }

    private function outputJson(array $data): void
    {
        ob_end_clean();
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add controllers/front/SubmitController.php
git commit -m "feat: add front-end SubmitController for AJAX and multipart form handling"
```
