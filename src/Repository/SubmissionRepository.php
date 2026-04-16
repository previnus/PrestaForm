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
