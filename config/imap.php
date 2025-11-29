<?php

return [

    'default' => 'default',

    'accounts' => [

        'default' => [
            'host'          => env('IMAP_HOST', 'imap.strato.de'),
            'port'          => env('IMAP_PORT', 993),
            'encryption'    => env('IMAP_ENCRYPTION', 'ssl'),
            'validate_cert' => env('IMAP_VALIDATE_CERT', true),
            'username'      => env('IMAP_USERNAME', 'alex@noppenberger.org'),
            'password'      => env('IMAP_PASSWORD', '!Cyberbob03'),
            'protocol'      => env('IMAP_PROTOCOL', 'imap')
        ],

    ],

    'options' => [
        'delimiter' => '/',
        'fetch'     => \Webklex\PHPIMAP\IMAP::FT_UID,
        'sequence'  => \Webklex\PHPIMAP\IMAP::ST_UID,
        'fetch_body' => true,
        'fetch_flags' => true,
    ],

];
