<?php

return [
    'default' => 'mysql',
    
    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', 3306),
            'database' => env('DB_DATABASE', 'tiangang_waf'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', '123456'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
        ],
    ],
    
    'redis' => [
        'host' => env('REDIS_HOST', '172.100.0.6'),
        'port' => env('REDIS_PORT', 6379),
        'password' => env('REDIS_PASSWORD', ''),
        'database' => env('REDIS_DATABASE', 0),
        'timeout' => 5,
        'retry_interval' => 100,
    ],
];
