<?php

return [
    // 代理基础配置
    'enabled' => env('PROXY_ENABLED', true),
    'timeout' => env('PROXY_TIMEOUT', 30),
    'connect_timeout' => env('PROXY_CONNECT_TIMEOUT', 5),
    'verify_ssl' => env('PROXY_VERIFY_SSL', true),
    'stream_threshold' => env('PROXY_STREAM_THRESHOLD', 1048576), // 1MB
    
    // 后端服务配置
    'backends' => [
        [
            'name' => 'primary',
            'url' => env('BACKEND_URL', 'http://localhost:8080'),
            'weight' => env('BACKEND_WEIGHT', 1),
            'health_url' => env('BACKEND_HEALTH_URL', 'http://localhost:8080/health'),
            'health_timeout' => env('BACKEND_HEALTH_TIMEOUT', 5),
            'recovery_time' => env('BACKEND_RECOVERY_TIME', 60),
            'health_check' => [
                'expected_status' => 'ok',
                'expected_content' => 'healthy'
            ]
        ],
        [
            'name' => 'secondary',
            'url' => env('BACKEND_URL_2', 'http://localhost:8081'),
            'weight' => env('BACKEND_WEIGHT_2', 1),
            'health_url' => env('BACKEND_HEALTH_URL_2', 'http://localhost:8081/health'),
            'health_timeout' => env('BACKEND_HEALTH_TIMEOUT_2', 5),
            'recovery_time' => env('BACKEND_RECOVERY_TIME_2', 60),
            'health_check' => [
                'expected_status' => 'ok',
                'expected_content' => 'healthy'
            ]
        ]
    ],
    
    // 负载均衡配置
    'load_balancer' => [
        'strategy' => env('LOAD_BALANCER_STRATEGY', 'round_robin'), // round_robin, least_connections, weighted, ip_hash
        'health_check_interval' => env('HEALTH_CHECK_INTERVAL', 30), // 秒
        'failure_threshold' => env('FAILURE_THRESHOLD', 3),
        'success_threshold' => env('SUCCESS_THRESHOLD', 2)
    ],
    
    // 缓存配置
    'cache' => [
        'enabled' => env('PROXY_CACHE_ENABLED', false),
        'ttl' => env('PROXY_CACHE_TTL', 300), // 秒
        'max_size' => env('PROXY_CACHE_MAX_SIZE', 100 * 1024 * 1024), // 100MB
        'exclude_headers' => [
            'Set-Cookie',
            'Authorization',
            'X-Request-ID'
        ]
    ],
    
    // 重试配置
    'retry' => [
        'enabled' => env('PROXY_RETRY_ENABLED', true),
        'max_attempts' => env('PROXY_RETRY_MAX_ATTEMPTS', 3),
        'delay' => env('PROXY_RETRY_DELAY', 100), // 毫秒
        'backoff_multiplier' => env('PROXY_RETRY_BACKOFF_MULTIPLIER', 2),
        'retry_on' => [
            502, // Bad Gateway
            503, // Service Unavailable
            504, // Gateway Timeout
            520, // Unknown Error
            521, // Web Server Is Down
            522, // Connection Timed Out
            523, // Origin Is Unreachable
            524  // A Timeout Occurred
        ]
    ],
    
    // 限流配置
    'rate_limit' => [
        'enabled' => env('PROXY_RATE_LIMIT_ENABLED', true),
        'requests_per_minute' => env('PROXY_RATE_LIMIT_RPM', 1000),
        'burst_size' => env('PROXY_RATE_LIMIT_BURST', 100),
        'key_by' => 'ip', // ip, user, path
        'exclude_paths' => [
            '/health',
            '/status',
            '/metrics'
        ]
    ],
    
    // 监控配置
    'monitoring' => [
        'enabled' => env('PROXY_MONITORING_ENABLED', true),
        'metrics' => [
            'response_time' => true,
            'throughput' => true,
            'error_rate' => true,
            'backend_health' => true
        ],
        'alerts' => [
            'high_response_time' => env('ALERT_HIGH_RESPONSE_TIME', 5000), // 毫秒
            'high_error_rate' => env('ALERT_HIGH_ERROR_RATE', 0.1), // 10%
            'backend_down' => true
        ]
    ],
    
    // 安全配置
    'security' => [
        'strip_headers' => [
            'X-Forwarded-For',
            'X-Real-IP',
            'X-Forwarded-Proto',
            'X-Forwarded-Host'
        ],
        'add_headers' => [
            'X-Proxy-By' => 'Tiangang-WAF',
            'X-Proxy-Version' => '1.0.0'
        ],
        'blocked_headers' => [
            'X-Forwarded-For',
            'X-Real-IP'
        ],
        // SSRF 防护配置
        'allowed_backend_hosts' => !empty(env('ALLOWED_BACKEND_HOSTS', ''))
            ? array_map('trim', explode(',', env('ALLOWED_BACKEND_HOSTS', '')))
            : [],
        'allowed_schemes' => ['http', 'https'], // 只允许 http 和 https
        'block_private_ips' => env('BLOCK_PRIVATE_IPS', true), // 阻止访问私有 IP
    ],
    
    // 日志配置
    'logging' => [
        'enabled' => env('PROXY_LOGGING_ENABLED', true),
        'level' => env('PROXY_LOG_LEVEL', 'info'),
        'log_requests' => env('PROXY_LOG_REQUESTS', true),
        'log_responses' => env('PROXY_LOG_RESPONSES', false),
        'log_errors' => env('PROXY_LOG_ERRORS', true),
        'sensitive_headers' => [
            'Authorization',
            'Cookie',
            'X-API-Key'
        ]
    ]
];
