<?php
declare(strict_types=1);

namespace PrestaForm\Entity;

class PfWebhook extends \ObjectModel
{
    public int    $id_webhook      = 0;
    public int    $id_form         = 0;
    public string $name            = '';
    public string $url             = '';
    public string $method          = 'POST';
    public string $headers         = '[]';
    public ?string $field_map      = null;
    public int    $retry_count     = 3;
    public int    $timeout_seconds = 10;
    public int    $active          = 1;

    /** @var array<string, mixed> */
    public static $definition = [
        'table'   => 'pf_webhooks',
        'primary' => 'id_webhook',
        'fields'  => [
            'id_form'         => ['type' => self::TYPE_INT,    'required' => true],
            'name'            => ['type' => self::TYPE_STRING, 'required' => true,  'size' => 255],
            'url'             => ['type' => self::TYPE_STRING, 'required' => true],
            'method'          => ['type' => self::TYPE_STRING, 'required' => false, 'size' => 6],
            'headers'         => ['type' => self::TYPE_STRING, 'required' => false],
            'field_map'       => ['type' => self::TYPE_STRING, 'required' => false, 'allow_null' => true],
            'retry_count'     => ['type' => self::TYPE_INT,    'required' => false],
            'timeout_seconds' => ['type' => self::TYPE_INT,    'required' => false],
            'active'          => ['type' => self::TYPE_INT,    'required' => false],
        ],
    ];
}
