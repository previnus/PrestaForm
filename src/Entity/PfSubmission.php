<?php
declare(strict_types=1);

namespace PrestaForm\Entity;

class PfSubmission extends \ObjectModel
{
    public int    $id_submission = 0;
    public int    $id_form       = 0;
    public string $data          = '{}';
    public string $ip_address    = '';
    public string $date_add      = '';

    /** @var array<string, mixed> */
    public static $definition = [
        'table'   => 'pf_submissions',
        'primary' => 'id_submission',
        'fields'  => [
            'id_form'    => ['type' => self::TYPE_INT,    'required' => true],
            'data'       => ['type' => self::TYPE_STRING, 'required' => true],
            'ip_address' => ['type' => self::TYPE_STRING, 'required' => false, 'size' => 45],
            'date_add'   => ['type' => self::TYPE_DATE,   'required' => false],
        ],
    ];
}
