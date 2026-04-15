<?php
declare(strict_types=1);

namespace PrestaForm\Entity;

class PfConditionGroup extends \ObjectModel
{
    public int    $id_condition_group = 0;
    public int    $id_form            = 0;
    public string $target_field       = '';
    public string $action             = 'show';
    public string $logic              = 'AND';
    public string $rules              = '[]';

    /** @var array<string, mixed> */
    public static $definition = [
        'table'   => 'pf_conditions',
        'primary' => 'id_condition_group',
        'fields'  => [
            'id_form'      => ['type' => self::TYPE_INT,    'required' => true],
            'target_field' => ['type' => self::TYPE_STRING, 'required' => true, 'size' => 255],
            'action'       => ['type' => self::TYPE_STRING, 'required' => false, 'size' => 4],
            'logic'        => ['type' => self::TYPE_STRING, 'required' => false, 'size' => 3],
            'rules'        => ['type' => self::TYPE_STRING, 'required' => false],
        ],
    ];
}
