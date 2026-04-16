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
