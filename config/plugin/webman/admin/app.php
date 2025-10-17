<?php

return [
    'enable' => true,
    'app_name' => '天罡 WAF 管理控制台',
    'app_logo' => '/static/admin/images/logo.png',
    'app_debug' => true,
    'https' => false,
    'trace' => [
        'type' => 'Html',
    ],
    'default_timezone' => 'Asia/Shanghai',
    'request_class' => \support\Request::class,
    'public_path' => base_path() . DIRECTORY_SEPARATOR . 'public',
    'runtime_path' => base_path(false) . DIRECTORY_SEPARATOR . 'runtime',
    'controller_suffix' => 'Controller',
    'controller_reuse' => false,
    'middleware' => [
        '' => [
            \Webman\Middleware\SessionInit::class,
        ],
        'admin' => [
            \Webman\Middleware\SessionInit::class,
            \Webman\Middleware\CrossOrigin::class,
        ],
    ],
    'exception' => [
        '' => \support\exception\Handler::class,
    ],
    'bootstrap' => [
        \app\Bootstrap::class,
    ],
    'request' => [
        'enable_csrf' => false,
        'enable_request_log' => true,
        'enable_coroutine' => true,
    ],
    'database' => [
        'default' => 'mysql',
        'connections' => [
            'mysql' => [
                'driver' => 'mysql',
                'host' => env('DB_HOST', '127.0.0.1'),
                'port' => env('DB_PORT', 3306),
                'database' => env('DB_DATABASE', 'waf'),
                'username' => env('DB_USERNAME', 'root'),
                'password' => env('DB_PASSWORD', ''),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'strict' => true,
                'engine' => null,
            ],
        ],
    ],
    'redis' => [
        'default' => 'default',
        'connections' => [
            'default' => [
                'host' => env('REDIS_HOST', '127.0.0.1'),
                'password' => env('REDIS_PASSWORD', ''),
                'port' => env('REDIS_PORT', 6379),
                'database' => env('REDIS_DATABASE', 0),
            ],
        ],
    ],
    'session' => [
        'type' => 'File',
        'handler' => null,
        'config' => [
            'expire' => 1440,
            'domain' => '',
            'secure' => false,
            'httponly' => true,
            'name' => 'WAF_SESSION',
            'path' => '/',
        ],
        'auto_start' => true,
    ],
    'view' => [
        'type' => 'twig',
        'config' => [
            'view_path' => app_path() . '/view/',
            'cache_path' => runtime_path() . '/view/',
        ],
    ],
    'cache' => [
        'default' => 'file',
        'stores' => [
            'file' => [
                'type' => 'File',
                'path' => runtime_path() . '/cache/',
            ],
            'redis' => [
                'type' => 'Redis',
                'host' => env('REDIS_HOST', '127.0.0.1'),
                'port' => env('REDIS_PORT', 6379),
                'password' => env('REDIS_PASSWORD', ''),
                'select' => env('REDIS_DATABASE', 0),
            ],
        ],
    ],
    'log' => [
        'default' => 'file',
        'channels' => [
            'file' => [
                'type' => 'File',
                'path' => runtime_path() . '/logs/',
                'level' => ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'],
                'file_size' => 1024 * 1024 * 10,
                'max_files' => 5,
            ],
        ],
    ],
    'route' => [
        'enable' => true,
        'route_dir' => base_path() . '/config/route',
        'cache_file' => runtime_path() . '/route.cache',
    ],
    'static' => [
        'enable' => true,
        'root' => public_path(),
        'options' => [
            'dotfiles' => 'ignore',
            'etag' => true,
            'lastModified' => true,
        ],
    ],
];
