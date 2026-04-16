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
