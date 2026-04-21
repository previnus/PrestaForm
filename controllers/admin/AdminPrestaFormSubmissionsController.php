<?php
declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

class AdminPrestaFormSubmissionsController extends ModuleAdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->bootstrap  = true;
        $this->meta_title = 'PrestaForm — Submissions';
    }

    public function initContent(): void
    {
        $action = Tools::getValue('action');
        if ($action === 'view') {
            $this->content = $this->buildViewHtml();
        } else {
            $this->content = $this->buildListHtml();
        }
        parent::initContent();
    }

    private function buildListHtml(): string
    {
        $subRepo  = new \PrestaForm\Repository\SubmissionRepository();
        $formRepo = new \PrestaForm\Repository\FormRepository();

        $filters = [
            'id_form'   => (int) Tools::getValue('id_form') ?: null,
            'date_from' => Tools::getValue('date_from') ?: null,
            'date_to'   => Tools::getValue('date_to')   ?: null,
        ];

        $page        = max(1, (int) Tools::getValue('page', 1));
        $perPage     = 50;
        $total       = $subRepo->countAll(array_filter($filters));
        $submissions = $subRepo->findAll(array_filter($filters), $perPage, ($page - 1) * $perPage);

        // Pre-convert any array field values to comma strings so templates
        // never need |@implode (avoids PS9 SmartyLazyRegister PHP-8 crash)
        foreach ($submissions as &$sub) {
            foreach ($sub['data'] as $k => $v) {
                if (is_array($v)) {
                    $sub['data'][$k] = implode(', ', $v);
                }
            }
        }
        unset($sub);

        $this->context->smarty->assign([
            'submissions' => $submissions,
            'forms'       => $formRepo->findAll(),
            'filters'     => $filters,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'pages'       => (int) ceil($total / $perPage),
            'page_range'  => range(1, max(1, (int) ceil($total / $perPage))),
            'base_url'    => $this->context->link->getAdminLink('AdminPrestaFormSubmissions'),
            'forms_url'   => $this->context->link->getAdminLink('AdminPrestaFormForms'),
        ]);

        return $this->context->smarty->fetch(
            _PS_MODULE_DIR_ . 'prestaform/views/templates/admin/submissions/list.tpl'
        );
    }

    public function postProcess(): void
    {
        $action = Tools::getValue('action');

        if ($action === 'delete') {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                return;
            }
            $id   = (int) Tools::getValue('id_submission');
            $repo = new \PrestaForm\Repository\SubmissionRepository();
            $repo->delete($id);
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminPrestaFormSubmissions'));
        }

        if ($action === 'export_csv') {
            $this->exportCsv();
        }

        parent::postProcess();
    }

    private function buildViewHtml(): string
    {
        $id   = (int) Tools::getValue('id_submission');
        $repo = new \PrestaForm\Repository\SubmissionRepository();
        $sub  = $repo->findById($id);

        if (!$sub) {
            $this->errors[] = 'Submission not found.';
            return $this->buildListHtml();
        }

        // Pre-convert array field values to strings (avoids |@implode in template)
        foreach ($sub['data'] as $k => $v) {
            if (is_array($v)) {
                $sub['data'][$k] = implode(', ', $v);
            }
        }

        // Build field label map: slug → placeholder (or humanised slug as fallback)
        $fieldLabels = [];
        $form = (new \PrestaForm\Repository\FormRepository())->findById((int) ($sub['id_form'] ?? 0));
        if ($form) {
            $parser = new \PrestaForm\Service\ShortcodeParser();
            foreach ($parser->parse((string) $form['template']) as $field) {
                if ($field['name'] !== '') {
                    $fieldLabels[$field['name']] = $field['params']['placeholder']
                        ?? ucwords(str_replace(['-', '_'], ' ', $field['name']));
                }
            }
        }

        $this->context->smarty->assign([
            'submission'   => $sub,
            'field_labels' => $fieldLabels,
            'base_url'     => $this->context->link->getAdminLink('AdminPrestaFormSubmissions'),
        ]);

        return $this->context->smarty->fetch(
            _PS_MODULE_DIR_ . 'prestaform/views/templates/admin/submissions/view.tpl'
        );
    }

    private function exportCsv(): void
    {
        $formId  = (int) Tools::getValue('id_form') ?: null;
        $filters = array_filter([
            'id_form'   => $formId,
            'date_from' => Tools::getValue('date_from') ?: null,
            'date_to'   => Tools::getValue('date_to')   ?: null,
        ]);
        $repo = new \PrestaForm\Repository\SubmissionRepository();
        $rows = $repo->findAll($filters, 10000, 0);

        // Collect all unique field keys across submissions
        $keys = ['id_submission', 'date_add', 'ip_address'];
        foreach ($rows as $row) {
            foreach (array_keys($row['data']) as $k) {
                if (!in_array($k, $keys, true)) {
                    $keys[] = $k;
                }
            }
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="prestaform-submissions-' . date('Y-m-d') . '.csv"');

        $out = fopen('php://output', 'w');
        fputs($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
        fputcsv($out, $keys);

        foreach ($rows as $row) {
            $line = [];
            foreach ($keys as $k) {
                if (in_array($k, ['id_submission', 'date_add', 'ip_address'], true)) {
                    $line[] = $row[$k] ?? '';
                } else {
                    $v = $row['data'][$k] ?? '';
                    $line[] = is_array($v) ? implode(', ', $v) : $v;
                }
            }
            fputcsv($out, $line);
        }

        fclose($out);
        exit;
    }
}
