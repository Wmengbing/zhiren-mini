<?php
return [
    'default' => env('DATABASE.TYPE', 'mysql'),
    'connections' => [
        'mysql' => [
            'type' => 'mysql',
            'hostname' => env('DATABASE.HOSTNAME', '127.0.0.1'),
            'database' => env('DATABASE.DATABASE', ''),
            'username' => env('DATABASE.USERNAME', ''),
            'password' => env('DATABASE.PASSWORD', ''),
            'hostport' => env('DATABASE.HOSTPORT', '3306'),
            'params' => [],
            'charset' => env('DATABASE.CHARSET', 'utf8mb4'),
            'prefix' => env('DATABASE.PREFIX', ''),
            'debug' => (bool) env('APP_DEBUG', false),
            'break_reconnect' => true,
            'trigger_sql' => (bool) env('APP_DEBUG', false),
        ],
    ],
];
