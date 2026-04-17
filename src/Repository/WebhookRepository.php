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
            'headers'         => pSQL(json_encode($data['headers'] ?? [], JSON_UNESCAPED_UNICODE) ?: '[]'),
            'field_map'       => isset($data['field_map'])
                ? pSQL(json_encode($data['field_map'], JSON_UNESCAPED_UNICODE) ?: '[]')
                : null,
            'retry_count'     => (int) ($data['retry_count']     ?? 3),
            'timeout_seconds' => (int) ($data['timeout_seconds'] ?? 10),
            'active'          => (int) ($data['active']          ?? 1),
        ];

        if (isset($data['id_webhook']) && (int) $data['id_webhook'] > 0) {
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

    public function deleteByForm(int $formId): void
    {
        // Delete logs for all webhooks belonging to this form
        $webhookIds = \Db::getInstance()->executeS(
            'SELECT id_webhook FROM `' . _DB_PREFIX_ . 'pf_webhooks` WHERE id_form = ' . (int) $formId
        ) ?: [];
        foreach ($webhookIds as $row) {
            \Db::getInstance()->delete('pf_webhook_log', 'id_webhook = ' . (int) $row['id_webhook']);
        }
        \Db::getInstance()->delete('pf_webhooks', 'id_form = ' . (int) $formId);
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
