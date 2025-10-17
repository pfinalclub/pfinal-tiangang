<?php

return [
    // 监控基础配置
    'enabled' => env('MONITORING_ENABLED', true),
    'collection_interval' => env('MONITORING_COLLECTION_INTERVAL', 60), // 秒
    'retention_days' => env('MONITORING_RETENTION_DAYS', 30),
    
    // 指标配置
    'metrics' => [
        'enabled' => env('METRICS_ENABLED', true),
        'namespace' => env('METRICS_NAMESPACE', 'tiangang_waf'),
        'buckets' => [
            'request_duration' => [0.001, 0.005, 0.01, 0.05, 0.1, 0.5, 1.0, 5.0],
            'proxy_duration' => [0.001, 0.005, 0.01, 0.05, 0.1, 0.5, 1.0, 5.0],
        ],
        'labels' => [
            'service' => 'tiangang-waf',
            'version' => '1.0.0',
            'environment' => env('APP_ENV', 'production')
        ]
    ],
    
    // 日志配置
    'logging' => [
        'enabled' => env('LOGGING_ENABLED', true),
        'level' => env('LOG_LEVEL', 'info'),
        'channels' => explode(',', env('LOG_CHANNELS', 'file,redis')),
        'batch_size' => env('LOG_BATCH_SIZE', 10),
        'flush_interval' => env('LOG_FLUSH_INTERVAL', 5), // 秒
        'retention_days' => env('LOG_RETENTION_DAYS', 30),
        'max_file_size' => env('LOG_MAX_FILE_SIZE', 100 * 1024 * 1024), // 100MB
        'sensitive_fields' => [
            'password',
            'token',
            'authorization',
            'cookie',
            'x-api-key'
        ]
    ],
    
    // 告警配置
    'alerts' => [
        'enabled' => env('ALERTS_ENABLED', true),
        'check_interval' => env('ALERT_CHECK_INTERVAL', 30), // 秒
        'cooldown_period' => env('ALERT_COOLDOWN_PERIOD', 300), // 秒
        
        // 告警阈值
        'high_response_time' => env('ALERT_HIGH_RESPONSE_TIME', 5000), // 毫秒
        'high_error_rate' => env('ALERT_HIGH_ERROR_RATE', 10), // 百分比
        'high_memory_usage' => env('ALERT_HIGH_MEMORY_USAGE', 80), // 百分比
        'high_cpu_usage' => env('ALERT_HIGH_CPU_USAGE', 80), // 百分比
        'high_block_rate' => env('ALERT_HIGH_BLOCK_RATE', 50), // 百分比
        'low_throughput' => env('ALERT_LOW_THROUGHPUT', 10), // 请求/分钟
        
        // 告警通道
        'channels' => [
            'log' => [
                'enabled' => env('ALERT_LOG_ENABLED', true),
                'level' => env('ALERT_LOG_LEVEL', 'warning')
            ],
            'email' => [
                'enabled' => env('ALERT_EMAIL_ENABLED', false),
                'smtp_host' => env('ALERT_EMAIL_SMTP_HOST'),
                'smtp_port' => env('ALERT_EMAIL_SMTP_PORT', 587),
                'smtp_username' => env('ALERT_EMAIL_SMTP_USERNAME'),
                'smtp_password' => env('ALERT_EMAIL_SMTP_PASSWORD'),
                'from' => env('ALERT_EMAIL_FROM'),
                'to' => explode(',', env('ALERT_EMAIL_TO', '')),
                'subject_prefix' => env('ALERT_EMAIL_SUBJECT_PREFIX', '[Tiangang WAF]')
            ],
            'webhook' => [
                'enabled' => env('ALERT_WEBHOOK_ENABLED', false),
                'url' => env('ALERT_WEBHOOK_URL'),
                'timeout' => env('ALERT_WEBHOOK_TIMEOUT', 10),
                'retry_attempts' => env('ALERT_WEBHOOK_RETRY_ATTEMPTS', 3)
            ],
            'slack' => [
                'enabled' => env('ALERT_SLACK_ENABLED', false),
                'webhook_url' => env('ALERT_SLACK_WEBHOOK_URL'),
                'channel' => env('ALERT_SLACK_CHANNEL', '#alerts'),
                'username' => env('ALERT_SLACK_USERNAME', 'Tiangang WAF'),
                'icon_emoji' => env('ALERT_SLACK_ICON_EMOJI', ':warning:')
            ]
        ]
    ],
    
    // 性能分析配置
    'profiling' => [
        'enabled' => env('PROFILING_ENABLED', false),
        'sample_rate' => env('PROFILING_SAMPLE_RATE', 0.1), // 10%
        'slow_query_threshold' => env('PROFILING_SLOW_QUERY_THRESHOLD', 1000), // 毫秒
        'memory_threshold' => env('PROFILING_MEMORY_THRESHOLD', 50 * 1024 * 1024), // 50MB
        'retention_hours' => env('PROFILING_RETENTION_HOURS', 24)
    ],
    
    // 健康检查配置
    'health_check' => [
        'enabled' => env('HEALTH_CHECK_ENABLED', true),
        'interval' => env('HEALTH_CHECK_INTERVAL', 30), // 秒
        'timeout' => env('HEALTH_CHECK_TIMEOUT', 5), // 秒
        'endpoints' => [
            'waf' => '/health/waf',
            'proxy' => '/health/proxy',
            'database' => '/health/database',
            'redis' => '/health/redis'
        ]
    ],
    
    // 仪表盘配置
    'dashboard' => [
        'enabled' => env('DASHBOARD_ENABLED', true),
        'refresh_interval' => env('DASHBOARD_REFRESH_INTERVAL', 5), // 秒
        'time_range' => env('DASHBOARD_TIME_RANGE', 3600), // 秒
        'widgets' => [
            'request_rate' => true,
            'response_time' => true,
            'error_rate' => true,
            'block_rate' => true,
            'memory_usage' => true,
            'cpu_usage' => true,
            'active_connections' => true,
            'top_rules' => true
        ]
    ],
    
    // 导出配置
    'export' => [
        'enabled' => env('EXPORT_ENABLED', true),
        'formats' => ['json', 'csv', 'prometheus'],
        'retention_days' => env('EXPORT_RETENTION_DAYS', 7),
        'compression' => env('EXPORT_COMPRESSION', true)
    ]
];
