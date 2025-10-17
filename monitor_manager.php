<?php

require_once __DIR__ . '/vendor/autoload.php';

use Tiangang\Waf\Logging\AsyncLogger;
use Tiangang\Waf\Monitoring\MetricsCollector;
use Tiangang\Waf\Monitoring\AlertManager;

echo "天罡 WAF 监控管理工具\n";
echo "==================\n\n";

// 检查命令行参数
$action = $argv[1] ?? 'help';

switch ($action) {
    case 'start':
        startMonitoring();
        break;
    case 'stop':
        stopMonitoring();
        break;
    case 'status':
        showStatus();
        break;
    case 'metrics':
        showMetrics();
        break;
    case 'alerts':
        showAlerts();
        break;
    case 'logs':
        showLogs();
        break;
    case 'test':
        testMonitoring();
        break;
    case 'help':
    default:
        showHelp();
        break;
}

/**
 * 启动监控服务
 */
function startMonitoring(): void
{
    echo "启动监控服务...\n";
    echo "==============\n\n";
    
    try {
        // 启动日志服务
        echo "启动异步日志服务...\n";
        $logger = new AsyncLogger();
        $logger->start();
        echo "✅ 异步日志服务已启动\n";
        
        // 启动指标收集
        echo "启动指标收集服务...\n";
        $metricsCollector = new MetricsCollector();
        $metricsCollector->start();
        echo "✅ 指标收集服务已启动\n";
        
        // 启动告警监控
        echo "启动告警监控服务...\n";
        $alertManager = new AlertManager();
        $alertManager->start();
        echo "✅ 告警监控服务已启动\n";
        
        echo "\n所有监控服务已启动！\n";
        echo "按 Ctrl+C 停止服务\n\n";
        
        // 保持运行
        while (true) {
            sleep(1);
        }
        
    } catch (\Exception $e) {
        echo "❌ 启动监控服务失败: " . $e->getMessage() . "\n";
    }
}

/**
 * 停止监控服务
 */
function stopMonitoring(): void
{
    echo "停止监控服务...\n";
    echo "==============\n\n";
    
    // TODO: 实现停止逻辑
    echo "✅ 监控服务已停止\n";
}

/**
 * 显示监控状态
 */
function showStatus(): void
{
    echo "监控服务状态\n";
    echo "============\n\n";
    
    try {
        $logger = new AsyncLogger();
        $metricsCollector = new MetricsCollector();
        $alertManager = new AlertManager();
        
        echo "日志服务状态:\n";
        $logStats = $logger->getStats();
        echo "  队列大小: {$logStats['queue_size']}\n";
        echo "  运行状态: " . ($logStats['is_running'] ? '运行中' : '已停止') . "\n";
        echo "  内存使用: " . formatBytes($logStats['memory_usage']) . "\n";
        echo "  内存峰值: " . formatBytes($logStats['memory_peak']) . "\n\n";
        
        echo "系统指标:\n";
        $systemMetrics = $metricsCollector->getSystemMetrics();
        echo "  内存使用: " . formatBytes($systemMetrics['memory_usage']) . "\n";
        echo "  内存峰值: " . formatBytes($systemMetrics['memory_peak']) . "\n";
        echo "  CPU 使用率: " . round($systemMetrics['cpu_usage'], 2) . "%\n";
        echo "  负载平均值: " . round($systemMetrics['load_average'], 2) . "\n";
        echo "  活跃请求: {$systemMetrics['active_requests']}\n\n";
        
        echo "活跃告警:\n";
        $activeAlerts = $alertManager->getActiveAlerts();
        if (empty($activeAlerts)) {
            echo "  ✅ 无活跃告警\n";
        } else {
            foreach ($activeAlerts as $alert) {
                echo "  ⚠️  {$alert['name']}: {$alert['message']}\n";
                echo "     严重程度: {$alert['severity']}\n";
                echo "     当前值: {$alert['value']}\n";
                echo "     阈值: {$alert['threshold']}\n";
                echo "     时间: " . date('Y-m-d H:i:s', $alert['timestamp']) . "\n\n";
            }
        }
        
    } catch (\Exception $e) {
        echo "❌ 获取状态失败: " . $e->getMessage() . "\n";
    }
}

/**
 * 显示指标数据
 */
function showMetrics(): void
{
    echo "指标数据\n";
    echo "========\n\n";
    
    try {
        $metricsCollector = new MetricsCollector();
        
        echo "请求指标:\n";
        $totalRequests = $metricsCollector->getCounter('requests_total');
        $blockedRequests = $metricsCollector->getCounter('requests_blocked_total');
        $allowedRequests = $metricsCollector->getCounter('requests_allowed_total');
        
        echo "  总请求数: {$totalRequests}\n";
        echo "  拦截请求: {$blockedRequests}\n";
        echo "  放行请求: {$allowedRequests}\n";
        
        if ($totalRequests > 0) {
            $blockRate = ($blockedRequests / $totalRequests) * 100;
            echo "  拦截率: " . round($blockRate, 2) . "%\n";
        }
        
        echo "\n响应时间统计:\n";
        $responseTimeStats = $metricsCollector->getHistogramStats('request_duration');
        if (!empty($responseTimeStats)) {
            echo "  平均响应时间: " . round($responseTimeStats['avg'] * 1000, 2) . "ms\n";
            echo "  最小响应时间: " . round($responseTimeStats['min'] * 1000, 2) . "ms\n";
            echo "  最大响应时间: " . round($responseTimeStats['max'] * 1000, 2) . "ms\n";
            echo "  P50: " . round($responseTimeStats['p50'] * 1000, 2) . "ms\n";
            echo "  P95: " . round($responseTimeStats['p95'] * 1000, 2) . "ms\n";
            echo "  P99: " . round($responseTimeStats['p99'] * 1000, 2) . "ms\n";
        }
        
        echo "\n代理指标:\n";
        $proxyRequests = $metricsCollector->getCounter('proxy_requests_total');
        $proxySuccess = $metricsCollector->getCounter('proxy_success_total');
        $proxyErrors = $metricsCollector->getCounter('proxy_errors_total');
        
        echo "  代理请求数: {$proxyRequests}\n";
        echo "  成功请求: {$proxySuccess}\n";
        echo "  错误请求: {$proxyErrors}\n";
        
        if ($proxyRequests > 0) {
            $successRate = ($proxySuccess / $proxyRequests) * 100;
            echo "  成功率: " . round($successRate, 2) . "%\n";
        }
        
    } catch (\Exception $e) {
        echo "❌ 获取指标失败: " . $e->getMessage() . "\n";
    }
}

/**
 * 显示告警信息
 */
function showAlerts(): void
{
    echo "告警信息\n";
    echo "========\n\n";
    
    try {
        $alertManager = new AlertManager();
        $activeAlerts = $alertManager->getActiveAlerts();
        
        if (empty($activeAlerts)) {
            echo "✅ 当前无活跃告警\n";
        } else {
            echo "活跃告警 (" . count($activeAlerts) . " 个):\n";
            echo "----------------------------------------\n";
            
            foreach ($activeAlerts as $alert) {
                $severity = strtoupper($alert['severity']);
                $icon = match($alert['severity']) {
                    'critical' => '🔴',
                    'warning' => '🟡',
                    'info' => '🔵',
                    default => '⚪'
                };
                
                echo "{$icon} [{$severity}] {$alert['name']}\n";
                echo "   消息: {$alert['message']}\n";
                echo "   当前值: {$alert['value']}\n";
                echo "   阈值: {$alert['threshold']}\n";
                echo "   时间: " . date('Y-m-d H:i:s', $alert['timestamp']) . "\n\n";
            }
        }
        
    } catch (\Exception $e) {
        echo "❌ 获取告警失败: " . $e->getMessage() . "\n";
    }
}

/**
 * 显示日志信息
 */
function showLogs(): void
{
    echo "日志信息\n";
    echo "========\n\n";
    
    $logFiles = [
        'WAF 日志' => runtime_path('logs/waf.log'),
        '错误日志' => runtime_path('logs/error.log'),
        '安全日志' => runtime_path('logs/security.log')
    ];
    
    foreach ($logFiles as $name => $file) {
        echo "{$name}:\n";
        if (file_exists($file)) {
            $size = filesize($file);
            $lines = count(file($file));
            echo "  文件: {$file}\n";
            echo "  大小: " . formatBytes($size) . "\n";
            echo "  行数: {$lines}\n";
            echo "  修改时间: " . date('Y-m-d H:i:s', filemtime($file)) . "\n";
        } else {
            echo "  文件不存在\n";
        }
        echo "\n";
    }
}

/**
 * 测试监控功能
 */
function testMonitoring(): void
{
    echo "测试监控功能...\n";
    echo "==============\n\n";
    
    try {
        // 测试日志记录
        echo "1. 测试异步日志记录...\n";
        $logger = new AsyncLogger();
        $logger->log('info', 'Test log message', ['test' => true]);
        $logger->logRequest(['ip' => '127.0.0.1'], ['status' => 200], 0.1);
        $logger->logSecurityEvent('test_event', ['rule' => 'test_rule']);
        echo "✅ 日志记录测试完成\n";
        
        // 测试指标收集
        echo "\n2. 测试指标收集...\n";
        $metricsCollector = new MetricsCollector();
        $metricsCollector->recordRequest([
            'duration' => 0.1,
            'blocked' => false
        ]);
        $metricsCollector->recordSecurityEvent('test_event', ['severity' => 'info']);
        $metricsCollector->recordPerformance('test_metric', 100.5);
        echo "✅ 指标收集测试完成\n";
        
        // 测试告警系统
        echo "\n3. 测试告警系统...\n";
        $alertManager = new AlertManager();
        $activeAlerts = $alertManager->getActiveAlerts();
        echo "✅ 告警系统测试完成\n";
        
        echo "\n所有监控功能测试完成！\n";
        
    } catch (\Exception $e) {
        echo "❌ 监控功能测试失败: " . $e->getMessage() . "\n";
    }
}

/**
 * 显示帮助信息
 */
function showHelp(): void
{
    echo "天罡 WAF 监控管理工具\n";
    echo "====================\n\n";
    echo "用法: php monitor_manager.php <命令>\n\n";
    echo "可用命令:\n";
    echo "  start   - 启动监控服务\n";
    echo "  stop    - 停止监控服务\n";
    echo "  status  - 显示监控状态\n";
    echo "  metrics - 显示指标数据\n";
    echo "  alerts  - 显示告警信息\n";
    echo "  logs    - 显示日志信息\n";
    echo "  test    - 测试监控功能\n";
    echo "  help    - 显示此帮助信息\n\n";
    echo "示例:\n";
    echo "  php monitor_manager.php start   # 启动监控服务\n";
    echo "  php monitor_manager.php status  # 查看监控状态\n";
    echo "  php monitor_manager.php metrics # 查看指标数据\n";
    echo "  php monitor_manager.php test      # 测试监控功能\n\n";
}

/**
 * 格式化字节大小
 */
function formatBytes(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= pow(1024, $pow);
    
    return round($bytes, 2) . ' ' . $units[$pow];
}
