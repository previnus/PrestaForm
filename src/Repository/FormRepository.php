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
