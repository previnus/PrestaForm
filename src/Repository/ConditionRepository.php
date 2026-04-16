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
