<?php

require_once __DIR__ . '/vendor/autoload.php';

use Tiangang\Waf\Proxy\BackendManager;

echo "天罡 WAF 代理管理工具\n";
echo "==================\n\n";

// 检查命令行参数
$action = $argv[1] ?? 'help';

switch ($action) {
    case 'health':
        checkHealth();
        break;
    case 'stats':
        showStats();
        break;
    case 'test':
        testProxy();
        break;
    case 'help':
    default:
        showHelp();
        break;
}

/**
 * 检查后端健康状态
 */
function checkHealth(): void
{
    echo "检查后端健康状态...\n";
    echo "==================\n\n";
    
    $backendManager = new BackendManager();
    $results = $backendManager->healthCheck();
    
    foreach ($results as $result) {
        $status = $result['healthy'] ? '✅ 健康' : '❌ 不健康';
        echo "后端: {$result['backend']}\n";
        echo "  URL: {$result['url']}\n";
        echo "  状态: {$status}\n";
        echo "  响应时间: {$result['response_time']}ms\n";
        echo "  检查时间: " . date('Y-m-d H:i:s', $result['timestamp']) . "\n\n";
    }
}

/**
 * 显示后端统计信息
 */
function showStats(): void
{
    echo "后端统计信息\n";
    echo "============\n\n";
    
    $backendManager = new BackendManager();
    $stats = $backendManager->getBackendStats();
    
    echo "总后端数: {$stats['total_backends']}\n";
    echo "健康后端: {$stats['healthy_backends']}\n";
    echo "不健康后端: {$stats['unhealthy_backends']}\n\n";
    
    echo "后端详情:\n";
    echo "--------\n";
    foreach ($stats['backends'] as $backend) {
        $status = $backend['healthy'] ? '✅ 健康' : '❌ 不健康';
        echo "- {$backend['name']}: {$status}\n";
        echo "  URL: {$backend['url']}\n";
        echo "  连接数: {$backend['connections']}\n";
        echo "  权重: {$backend['weight']}\n\n";
    }
}

/**
 * 测试代理功能
 */
function testProxy(): void
{
    echo "测试代理功能...\n";
    echo "==============\n\n";
    
    $backendManager = new BackendManager();
    
    // 测试获取可用后端
    echo "1. 测试获取可用后端\n";
    $backend = $backendManager->getAvailableBackend();
    if ($backend) {
        echo "✅ 找到可用后端: {$backend['name']} ({$backend['url']})\n";
    } else {
        echo "❌ 没有可用的后端服务\n";
    }
    
    echo "\n2. 测试健康检查\n";
    try {
        $results = $backendManager->healthCheck();
        $healthyCount = 0;
        foreach ($results as $result) {
            if ($result['healthy']) {
                $healthyCount++;
            }
        }
        echo "健康后端数量: {$healthyCount}/" . count($results) . "\n";
    } catch (\Exception $e) {
        echo "⚠️  健康检查失败（后端服务未运行）: " . $e->getMessage() . "\n";
    }
    
    echo "\n3. 测试连接计数\n";
    if ($backend) {
        $backendManager->incrementConnections($backend['name']);
        echo "✅ 连接计数已增加\n";
        
        $backendManager->decrementConnections($backend['name']);
        echo "✅ 连接计数已减少\n";
    }
    
    echo "\n测试完成！\n";
}

/**
 * 显示帮助信息
 */
function showHelp(): void
{
    echo "天罡 WAF 代理管理工具\n";
    echo "====================\n\n";
    echo "用法: php proxy_manager.php <命令>\n\n";
    echo "可用命令:\n";
    echo "  health - 检查后端健康状态\n";
    echo "  stats  - 显示后端统计信息\n";
    echo "  test   - 测试代理功能\n";
    echo "  help   - 显示此帮助信息\n\n";
    echo "示例:\n";
    echo "  php proxy_manager.php health  # 检查后端健康状态\n";
    echo "  php proxy_manager.php stats   # 显示后端统计信息\n";
    echo "  php proxy_manager.php test    # 测试代理功能\n\n";
}
