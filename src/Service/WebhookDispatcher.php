<?php
declare(strict_types=1);

namespace PrestaForm\Service;

use PrestaForm\Repository\WebhookRepository;

class WebhookDispatcher
{
    /** @var callable */
    private $httpCallback;

    public function __construct(
        private readonly WebhookRepository $repo = new WebhookRepository(),
        ?callable $httpCallback = null
    ) {
        $this->httpCallback = $httpCallback ?? $this->curlRequest(...);
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
            json_encode($payload, JSON_UNESCAPED_UNICODE) ?: '{}',
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
