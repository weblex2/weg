<?php

return [

    'default' => 'default',
    'accounts' => [
        'default' => [
            'host'          => 'imap.strato.de',
            'port'          => 993,
            'encryption'    => 'ssl',
            'validate_cert' => true,
            'username'      => 'alex@noppenberger.org',
            'password'      => '!Cyberbob498712',
            'protocol'      => 'imap'
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
