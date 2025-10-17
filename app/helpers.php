<?php

/**
 * 获取运行时路径
 */
function runtime_path(string $path = ''): string
{
    $runtimePath = __DIR__ . '/../runtime';
    
    if ($path) {
        return $runtimePath . '/' . ltrim($path, '/');
    }
    
    return $runtimePath;
}

/**
 * 获取配置值
 */
function config(string $key, $default = null)
{
    static $config = null;
    
    if ($config === null) {
        $configManager = new \Tiangang\Waf\Config\ConfigManager();
        $config = $configManager->all();
    }
    
    $keys = explode('.', $key);
    $value = $config;
    
    foreach ($keys as $k) {
        if (!isset($value[$k])) {
            return $default;
        }
        $value = $value[$k];
    }
    
    return $value;
}

/**
 * 记录日志
 */
function logger(string $level, string $message, array $context = []): void
{
    static $logger = null;
    
    if ($logger === null) {
        $logger = new \Tiangang\Waf\Logging\AsyncLogger();
    }
    
    $logger->log($level, $message, $context);
}

/**
 * 获取环境变量
 */
function env(string $key, $default = null)
{
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    
    if ($value === false) {
        return $default;
    }
    
    // 转换布尔值
    if (in_array(strtolower($value), ['true', '1', 'yes', 'on'])) {
        return true;
    }
    
    if (in_array(strtolower($value), ['false', '0', 'no', 'off'])) {
        return false;
    }
    
    return $value;
}

/**
 * 格式化字节大小
 */
function format_bytes(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= pow(1024, $pow);
    
    return round($bytes, 2) . ' ' . $units[$pow];
}

/**
 * 格式化时间
 */
function format_duration(float $seconds): string
{
    if ($seconds < 0.001) {
        return round($seconds * 1000000) . 'μs';
    } elseif ($seconds < 1) {
        return round($seconds * 1000) . 'ms';
    } else {
        return round($seconds, 2) . 's';
    }
}

/**
 * 生成唯一ID
 */
function generate_id(): string
{
    return uniqid('tiangang_', true);
}

/**
 * 获取客户端IP
 */
function get_client_ip(): string
{
    $headers = [
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_REAL_IP',
        'HTTP_CLIENT_IP',
        'REMOTE_ADDR'
    ];
    
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ips = explode(',', $_SERVER[$header]);
            $ip = trim($ips[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    
    return '127.0.0.1';
}

/**
 * 检查是否为内部IP
 */
function is_internal_ip(string $ip): bool
{
    return !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
}

/**
 * 获取当前时间戳（微秒）
 */
function microtime_float(): float
{
    return microtime(true);
}

/**
 * 安全地序列化数据
 */
function safe_serialize($data): string
{
    return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/**
 * 安全地反序列化数据
 */
function safe_unserialize(string $data)
{
    return json_decode($data, true);
}

/**
 * 清理敏感数据
 */
function sanitize_data(array $data, array $sensitiveFields = []): array
{
    $defaultSensitiveFields = [
        'password',
        'token',
        'authorization',
        'cookie',
        'x-api-key',
        'secret',
        'key'
    ];
    
    $sensitiveFields = array_merge($defaultSensitiveFields, $sensitiveFields);
    
    foreach ($data as $key => $value) {
        $lowerKey = strtolower($key);
        
        if (in_array($lowerKey, $sensitiveFields)) {
            $data[$key] = '***';
        } elseif (is_array($value)) {
            $data[$key] = sanitize_data($value, $sensitiveFields);
        }
    }
    
    return $data;
}