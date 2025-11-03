<?php

return [
    // WAF 基础配置
    'enabled' => env('WAF_ENABLED', true),
    'mode' => env('WAF_MODE', 'proxy'), // proxy, sdk, api
    'timeout' => env('WAF_TIMEOUT', 1000), // 毫秒
    'cache_ttl' => env('WAF_CACHE_TTL', 300), // 秒
    
    // 检测配置
    'detection' => [
        'quick_enabled' => true,
        'async_enabled' => true,
        'quick_timeout' => 10, // 毫秒
        'async_timeout' => 100, // 毫秒
        'max_concurrent' => 100,
    ],
    
    // 规则配置
    'rules' => [
        'enabled' => [
            'sql_injection',
            'xss',
            'rate_limit',
            'ip_blacklist',
        ],
        'priority' => [
            'ip_blacklist' => 100,
            'rate_limit' => 90,
            'sql_injection' => 80,
            'xss' => 70,
        ],
        // 从 YAML 文件加载的规则配置
        'config' => [],
    ],
    
    // IP 黑名单配置
    'ip_blacklist' => [
        '127.0.0.1',
        '192.168.1.100',
    ],
    
    // IP 白名单配置
    'ip_whitelist' => [
        '127.0.0.1',
        '::1',
    ],
    
    // 频率限制配置
    'rate_limit' => [
        'max_requests' => 100,
        'window' => 60,
        'burst' => 20,
    ],
    
    // 决策引擎配置
    'decision' => [
        'threshold' => 100,
        'weights' => [
            'critical' => 100,
            'high' => 80,
            'medium' => 60,
            'low' => 40,
        ],
    ],
    
    // 日志配置
    'logging' => [
        'enabled' => true,
        'level' => 'info',
        'channels' => ['file', 'redis'],
        'async' => true,
    ],
    
    // 监控配置
    'monitoring' => [
        'enabled' => env('MONITORING_ENABLED', true),
        'metrics' => [
            'enabled' => env('METRICS_ENABLED', true),
            'interval' => 60, // 秒
        ],
        'alerts' => [
            'enabled' => env('ALERT_ENABLED', true),
            'thresholds' => [
                'block_rate' => 0.1, // 10%
                'response_time' => 1000, // 毫秒
                'error_rate' => 0.05, // 5%
            ],
        ],
    ],
    
    // 安全配置
    'security' => [
        // 可信代理列表（只有这些 IP 的代理头才会被信任）
        'trusted_proxies' => !empty(env('TRUSTED_PROXIES', '')) 
            ? array_map('trim', explode(',', env('TRUSTED_PROXIES', '127.0.0.1,::1')))
            : ['127.0.0.1', '::1'],
        
        // 请求大小限制
        'max_body_size' => env('MAX_BODY_SIZE', 10485760), // 10MB
        'max_url_length' => env('MAX_URL_LENGTH', 2048),
        'max_header_size' => env('MAX_HEADER_SIZE', 8192),
    ],
];
