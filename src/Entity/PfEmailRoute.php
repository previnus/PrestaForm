<?php
declare(strict_types=1);

namespace PrestaForm\Entity;

class PfEmailRoute extends \ObjectModel
{
    public int    $id_route          = 0;
    public int    $id_form           = 0;
    public string $type              = 'admin';
    public int    $enabled           = 1;
    public string $notify_addresses  = '[]';
    public ?string $reply_to         = null;
    public string $subject           = '';
    public string $body              = '';
    public ?string $routing_rules    = null;

    /** @var array<string, mixed> */
    public static $definition = [
        'table'   => 'pf_email_routes',
        'primary' => 'id_route',
        'fields'  => [
            'id_form'          => ['type' => self::TYPE_INT,    'required' => true],
            'type'             => ['type' => self::TYPE_STRING, 'required' => true,  'size' => 12],
            'enabled'          => ['type' => self::TYPE_INT,    'required' => false],
            'notify_addresses' => ['type' => self::TYPE_STRING, 'required' => false],
            'reply_to'         => ['type' => self::TYPE_STRING, 'required' => false, 'allow_null' => true, 'size' => 255],
            'subject'          => ['type' => self::TYPE_STRING, 'required' => false, 'size' => 500],
            'body'             => ['type' => self::TYPE_HTML,   'required' => false],
            'routing_rules'    => ['type' => self::TYPE_STRING, 'required' => false, 'allow_null' => true],
        ],
    ];
}
