<?php

use Illuminate\Support\Str;

return [
    'default' => null,
    'connections' => [
    ],
    'migrations' => 'migrations',
    'redis' => [
        'cluster' => false,
        'default' => [
            'host'     => 'redis',
            'port'     => 6379,
            'database' => 0,
        ]
    ]
];
