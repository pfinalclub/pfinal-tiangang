<?php

/**
 * 获取运行时路径（如果不存在则定义）
 */
if (!function_exists('runtime_path')) {
    function runtime_path(string $path = ''): string
    {
        $runtimePath = __DIR__ . '/../runtime';
        
        if ($path) {
            return $runtimePath . '/' . ltrim($path, '/');
        }
        
        return $runtimePath;
    }
}

/**
 * 获取配置值（如果不存在则定义）
 */
if (!function_exists('config')) {
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
}

/**
 * 记录日志（如果不存在则定义）
 */
if (!function_exists('logger')) {
    function logger(string $level, string $message, array $context = []): void
    {
        $logFile = runtime_path('logs/app.log');
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = empty($context) ? '' : ' ' . json_encode($context);
        $logMessage = "[{$timestamp}] {$level}: {$message}{$contextStr}" . PHP_EOL;
        
        file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }
}

/**
 * 获取环境变量（如果不存在则定义）
 */
if (!function_exists('env')) {
    function env(string $key, $default = null)
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        
        if ($value === false) {
            return $default;
        }
        
        // 处理布尔值
        if (in_array(strtolower($value), ['true', '1', 'yes', 'on'])) {
            return true;
        }
        
        if (in_array(strtolower($value), ['false', '0', 'no', 'off'])) {
            return false;
        }
        
        return $value;
    }
}

/**
 * 生成随机字符串（如果不存在则定义）
 */
if (!function_exists('str_random')) {
    function str_random(int $length = 16): string
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        
        return $randomString;
    }
}

/**
 * 格式化字节大小（如果不存在则定义）
 */
if (!function_exists('format_bytes')) {
    function format_bytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}

/**
 * 获取客户端IP（如果不存在则定义）
 */
if (!function_exists('get_client_ip')) {
    function get_client_ip(): string
    {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}

/**
 * 检查是否为HTTPS（如果不存在则定义）
 */
if (!function_exists('is_https')) {
    function is_https(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
               $_SERVER['SERVER_PORT'] == 443 ||
               (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    }
}

/**
 * 获取当前时间戳（毫秒）（如果不存在则定义）
 */
if (!function_exists('microtime_ms')) {
    function microtime_ms(): int
    {
        return (int)(microtime(true) * 1000);
    }
}