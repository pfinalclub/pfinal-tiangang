<?php

use Tiangang\Waf\Config\ConfigManager;

/**
 * 获取配置
 */
function config(string $key, mixed $default = null): mixed
{
    static $configManager = null;
    
    if ($configManager === null) {
        $configManager = new ConfigManager();
    }
    
    return $configManager->get($key, $default);
}

/**
 * 获取环境变量
 */
function env(string $key, mixed $default = null): mixed
{
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? $default;
    
    // 类型转换
    if (is_string($value)) {
        switch (strtolower($value)) {
            case 'true':
            case '(true)':
                return true;
            case 'false':
            case '(false)':
                return false;
            case 'empty':
            case '(empty)':
                return '';
            case 'null':
            case '(null)':
                return null;
        }
    }
    
    return $value;
}

/**
 * 记录日志
 */
function logger(string $level, string $message, array $context = []): void
{
    $logFile = __DIR__ . '/../runtime/logs/app.log';
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] {$level}: {$message} " . json_encode($context) . PHP_EOL;
    
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

/**
 * 获取基础路径
 */
function base_path(string $path = ''): string
{
    return __DIR__ . '/../' . ltrim($path, '/');
}

/**
 * 获取运行时路径
 */
function runtime_path(string $path = ''): string
{
    return base_path('runtime/' . ltrim($path, '/'));
}

/**
 * 获取配置路径
 */
function config_path(string $path = ''): string
{
    return base_path('config/' . ltrim($path, '/'));
}

/**
 * 获取插件路径
 */
function plugin_path(string $path = ''): string
{
    return base_path('plugins/' . ltrim($path, '/'));
}
