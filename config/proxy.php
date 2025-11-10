<?php

return [
    // 代理基础配置
    'enabled' => true,
    'timeout' => 30,
    'connect_timeout' => 5,
    'verify_ssl' => true,
    'stream_threshold' => 1048576,
    
    // 后端服务配置
    'backends' => [
        [
            'name' => 'primary',
            'url' => 'http://localhost:8080',
            'weight' => 1,
            'health_url' => 'http://localhost:8080/health',
            'health_timeout' => 5,
            'recovery_time' => 60,
            'health_check' => array (
  'expected_status' => 'ok',
  'expected_content' => 'healthy',
),
        ],
        [
            'name' => 'secondary',
            'url' => 'http://localhost:8081',
            'weight' => 1,
            'health_url' => 'http://localhost:8081/health',
            'health_timeout' => 5,
            'recovery_time' => 60,
            'health_check' => array (
  'expected_status' => 'ok',
  'expected_content' => 'healthy',
),
        ],
    ],
    
    // 域名映射配置（域名 -> 后端服务名称）- 主要路由方式
    // 示例：'crm.smm.cn' -> 'crm-backend', 'erp.smm.cn' -> 'erp-backend'
    // 支持精确匹配和通配符匹配（如 '*.api.smm.cn'）
    // 优先级：域名映射 > 路径映射 > 默认后端
    'domain_mappings' => [
        [
            'domain' => 'dev.local.crm.cn',
            'backend' => 'primary',
            'waf_rules' => array (
  0 => 'sql_injection',
  1 => 'xss',
  2 => 'rate_limit',
  3 => 'ip_blacklist',
),
            'enabled' => true,
        ],
        [
            'domain' => 'dev.local.wd.cn',
            'backend' => 'primary',
            'waf_rules' => array (
  0 => 'sql_injection',
  1 => 'xss',
  2 => 'rate_limit',
  3 => 'ip_blacklist',
),
            'enabled' => true,
        ],
    ],
    
    // 路径映射配置（路径前缀 -> 后端服务名称）- 补充路由方式
    // 示例：'/app1' -> 'primary', '/app2' -> 'secondary'
    // 如果请求路径匹配映射，则转发到对应的后端服务
    // 优先级：域名映射 > 路径映射 > 默认后端
    'path_mappings' => [
    ],
    
    // 默认后端（当没有域名或路径匹配时使用）
    'default_backend' => 'primary',
    
    // 负载均衡配置
    'load_balancer' => array (
  'strategy' => 'round_robin',
  'health_check_interval' => 30,
  'failure_threshold' => 3,
  'success_threshold' => 2,
),
    
    // 安全配置
    'security' => array (
  'strip_headers' => 
  array (
    0 => 'X-Forwarded-For',
    1 => 'X-Real-IP',
    2 => 'X-Forwarded-Proto',
    3 => 'X-Forwarded-Host',
  ),
  'add_headers' => 
  array (
    'X-Proxy-By' => 'Tiangang-WAF',
    'X-Proxy-Version' => '1.0.0',
  ),
  'blocked_headers' => 
  array (
    0 => 'X-Forwarded-For',
    1 => 'X-Real-IP',
  ),
  'allowed_backend_hosts' => 
  array (
  ),
  'allowed_schemes' => 
  array (
    0 => 'http',
    1 => 'https',
  ),
  'block_private_ips' => true,
),
    
    // 日志配置
    'logging' => array (
  'enabled' => true,
  'level' => 'info',
  'log_requests' => true,
  'log_responses' => false,
  'log_errors' => true,
  'sensitive_headers' => 
  array (
    0 => 'Authorization',
    1 => 'Cookie',
    2 => 'X-API-Key',
  ),
),
    
    // 监控配置
    'monitoring' => array (
  'enabled' => true,
  'metrics' => 
  array (
    'response_time' => true,
    'throughput' => true,
    'error_rate' => true,
    'backend_health' => true,
  ),
  'alerts' => 
  array (
    'high_response_time' => 5000,
    'high_error_rate' => 0.1,
    'backend_down' => true,
  ),
),
    
    // 缓存配置
    'cache' => array (
  'enabled' => false,
  'ttl' => 300,
  'max_size' => 104857600,
  'exclude_headers' => 
  array (
    0 => 'Set-Cookie',
    1 => 'Authorization',
    2 => 'X-Request-ID',
  ),
),
    
    // 重试配置
    'retry' => array (
  'enabled' => true,
  'max_attempts' => 3,
  'delay' => 100,
  'backoff_multiplier' => 2,
  'retry_on' => 
  array (
    0 => 502,
    1 => 503,
    2 => 504,
    3 => 520,
    4 => 521,
    5 => 522,
    6 => 523,
    7 => 524,
  ),
),
    
    // 限流配置
    'rate_limit' => array (
  'enabled' => true,
  'requests_per_minute' => 1000,
  'burst_size' => 100,
  'key_by' => 'ip',
  'exclude_paths' => 
  array (
    0 => '/health',
    1 => '/status',
    2 => '/metrics',
  ),
),
];
