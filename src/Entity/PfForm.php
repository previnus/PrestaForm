<?php
declare(strict_types=1);

namespace PrestaForm\Entity;

class PfForm extends \ObjectModel
{
    public int    $id_form        = 0;
    public string $name           = '';
    public string $slug           = '';
    public string $template       = '';
    public string $custom_css     = '';
    public string $success_message = '';
    public string $status         = 'draft';
    public string $captcha_provider = 'none';
    public ?int   $retention_days  = null;
    public string $date_add       = '';
    public string $date_upd       = '';

    /** @var array<string, mixed> */
    public static $definition = [
        'table'   => 'pf_forms',
        'primary' => 'id_form',
        'fields'  => [
            'name'             => ['type' => self::TYPE_STRING,  'required' => true,  'size' => 255],
            'slug'             => ['type' => self::TYPE_STRING,  'required' => true,  'size' => 255],
            'template'         => ['type' => self::TYPE_HTML,    'required' => false],
            'custom_css'       => ['type' => self::TYPE_STRING,  'required' => false],
            'success_message'  => ['type' => self::TYPE_STRING,  'required' => false],
            'status'           => ['type' => self::TYPE_STRING,  'required' => false, 'size' => 10],
            'captcha_provider' => ['type' => self::TYPE_STRING,  'required' => false, 'size' => 20],
            'retention_days'   => ['type' => self::TYPE_INT,     'required' => false, 'allow_null' => true],
            'date_add'         => ['type' => self::TYPE_DATE,    'required' => false],
            'date_upd'         => ['type' => self::TYPE_DATE,    'required' => false],
        ],
    ];
}
