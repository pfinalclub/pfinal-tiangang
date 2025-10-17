<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Tiangang\Waf\Gateway\TiangangGateway;
use Tiangang\Waf\Middleware\WafMiddleware;
use Tiangang\Waf\Proxy\ProxyHandler;
use Tiangang\Waf\Logging\AsyncLogger;
use Tiangang\Waf\Monitoring\MetricsCollector;
use PfinalClub\Asyncio\{create_task, gather, wait_for, sleep, run};

/**
 * 天罡 WAF 基础使用示例
 * 
 * 演示如何使用天罡 WAF 系统的基本功能
 */

echo "天罡 WAF 基础使用示例\n";
echo "==================\n\n";

// 示例 1: 基本请求处理
function example1_basicRequestHandling(): \Generator
{
    echo "示例 1: 基本请求处理\n";
    echo "------------------\n";
    
    $gateway = new TiangangGateway();
    
    // 模拟正常请求
    $normalRequest = [
        'ip' => '192.168.1.100',
        'uri' => '/api/users',
        'method' => 'GET',
        'headers' => ['User-Agent' => 'Mozilla/5.0'],
        'query' => ['page' => '1'],
        'post' => []
    ];
    
    echo "处理正常请求...\n";
    $result = yield $gateway->handle($normalRequest);
    echo "结果: " . ($result['status_code'] ?? 'unknown') . "\n\n";
}

// 示例 2: 安全威胁检测
function example2_securityThreatDetection(): \Generator
{
    echo "示例 2: 安全威胁检测\n";
    echo "------------------\n";
    
    $wafMiddleware = new WafMiddleware();
    
    // 模拟 SQL 注入攻击
    $sqlInjectionRequest = [
        'ip' => '192.168.1.100',
        'uri' => '/api/users',
        'method' => 'GET',
        'headers' => ['User-Agent' => 'Mozilla/5.0'],
        'query' => ['id' => "1' UNION SELECT * FROM users--"],
        'post' => []
    ];
    
    echo "检测 SQL 注入攻击...\n";
    $result = yield $wafMiddleware->process($sqlInjectionRequest);
    
    if ($result->isBlocked()) {
        echo "✓ 威胁被成功拦截\n";
        echo "拦截原因: " . implode(', ', $result->getReasons()) . "\n";
    } else {
        echo "✗ 威胁未被检测到\n";
    }
    echo "\n";
    
    // 模拟 XSS 攻击
    $xssRequest = [
        'ip' => '192.168.1.100',
        'uri' => '/api/comment',
        'method' => 'POST',
        'headers' => ['User-Agent' => 'Mozilla/5.0'],
        'query' => [],
        'post' => ['content' => '<script>alert("xss")</script>']
    ];
    
    echo "检测 XSS 攻击...\n";
    $result = yield $wafMiddleware->process($xssRequest);
    
    if ($result->isBlocked()) {
        echo "✓ 威胁被成功拦截\n";
        echo "拦截原因: " . implode(', ', $result->getReasons()) . "\n";
    } else {
        echo "✗ 威胁未被检测到\n";
    }
    echo "\n";
}

// 示例 3: 代理转发
function example3_proxyForwarding(): \Generator
{
    echo "示例 3: 代理转发\n";
    echo "----------------\n";
    
    $proxyHandler = new ProxyHandler();
    
    // 模拟代理请求
    $request = [
        'ip' => '192.168.1.100',
        'uri' => '/api/users',
        'method' => 'GET',
        'headers' => ['User-Agent' => 'Mozilla/5.0'],
        'query' => ['page' => '1'],
        'post' => []
    ];
    
    echo "转发请求到后端服务...\n";
    $result = yield $proxyHandler->forward($request);
    
    echo "代理响应状态: " . ($result['status_code'] ?? 'unknown') . "\n";
    echo "响应头数量: " . count($result['headers'] ?? []) . "\n";
    echo "响应体大小: " . strlen($result['body'] ?? '') . " bytes\n\n";
}

// 示例 4: 异步日志记录
function example4_asyncLogging(): \Generator
{
    echo "示例 4: 异步日志记录\n";
    echo "------------------\n";
    
    $logger = new AsyncLogger();
    
    // 记录不同类型的日志
    $logEntries = [
        ['level' => 'info', 'message' => 'User login successful', 'context' => ['user_id' => 123]],
        ['level' => 'warning', 'message' => 'High memory usage detected', 'context' => ['memory' => '512MB']],
        ['level' => 'error', 'message' => 'Database connection failed', 'context' => ['error' => 'Connection timeout']],
        ['level' => 'debug', 'message' => 'Request processed', 'context' => ['duration' => 0.05]]
    ];
    
    echo "记录 " . count($logEntries) . " 条日志...\n";
    
    foreach ($logEntries as $entry) {
        yield $logger->log($entry['level'], $entry['message'], $entry['context']);
    }
    
    echo "✓ 日志记录完成\n\n";
}

// 示例 5: 指标收集
function example5_metricsCollection(): \Generator
{
    echo "示例 5: 指标收集\n";
    echo "----------------\n";
    
    $metricsCollector = new MetricsCollector();
    
    // 模拟多个请求的指标收集
    $requests = [
        ['blocked' => false, 'duration' => 0.05, 'rule' => null],
        ['blocked' => true, 'duration' => 0.02, 'rule' => 'sql_injection'],
        ['blocked' => false, 'duration' => 0.08, 'rule' => null],
        ['blocked' => true, 'duration' => 0.01, 'rule' => 'xss'],
        ['blocked' => false, 'duration' => 0.06, 'rule' => null]
    ];
    
    echo "收集 " . count($requests) . " 个请求的指标...\n";
    
    foreach ($requests as $request) {
        yield $metricsCollector->asyncRecordRequest($request);
    }
    
    // 记录安全事件
    yield $metricsCollector->asyncRecordSecurityEvent('sql_injection', [
        'ip' => '192.168.1.100',
        'rule' => 'sql_injection',
        'severity' => 'high'
    ]);
    
    echo "✓ 指标收集完成\n\n";
}

// 示例 6: 并发处理
function example6_concurrentProcessing(): \Generator
{
    echo "示例 6: 并发处理\n";
    echo "----------------\n";
    
    $gateway = new TiangangGateway();
    
    // 模拟多个并发请求
    $requests = [];
    for ($i = 0; $i < 10; $i++) {
        $requests[] = [
            'ip' => '192.168.1.' . (100 + $i),
            'uri' => '/api/test' . $i,
            'method' => 'GET',
            'headers' => ['User-Agent' => 'Mozilla/5.0'],
            'query' => ['id' => $i],
            'post' => []
        ];
    }
    
    echo "并发处理 " . count($requests) . " 个请求...\n";
    
    $startTime = microtime(true);
    
    // 并发处理所有请求
    $tasks = [];
    foreach ($requests as $request) {
        $tasks[] = create_task($gateway->handle($request));
    }
    
    $results = yield gather(...$tasks);
    
    $endTime = microtime(true);
    $duration = $endTime - $startTime;
    
    echo "✓ 并发处理完成\n";
    echo "总耗时: " . round($duration * 1000, 2) . "ms\n";
    echo "平均耗时: " . round($duration / count($requests) * 1000, 2) . "ms/请求\n";
    echo "处理成功: " . count(array_filter($results, fn($r) => isset($r['status_code']))) . " 个\n\n";
}

// 示例 7: 错误处理
function example7_errorHandling(): \Generator
{
    echo "示例 7: 错误处理\n";
    echo "----------------\n";
    
    $gateway = new TiangangGateway();
    
    // 模拟各种错误情况
    $errorCases = [
        'invalid_request' => null,
        'malformed_data' => ['invalid' => 'data'],
        'timeout_request' => [
            'ip' => '192.168.1.100',
            'uri' => '/api/slow',
            'method' => 'GET',
            'headers' => [],
            'query' => [],
            'post' => []
        ]
    ];
    
    foreach ($errorCases as $caseName => $request) {
        echo "测试错误情况: {$caseName}\n";
        
        try {
            $result = yield $gateway->handle($request);
            echo "结果: " . ($result['status_code'] ?? 'unknown') . "\n";
        } catch (\Exception $e) {
            echo "捕获异常: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n";
}

// 示例 8: 配置管理
function example8_configManagement(): \Generator
{
    echo "示例 8: 配置管理\n";
    echo "----------------\n";
    
    $configManager = new \Tiangang\Waf\Config\ConfigManager();
    
    // 获取配置
    $wafEnabled = $configManager->get('waf.enabled');
    $wafTimeout = $configManager->get('waf.timeout');
    
    echo "WAF 启用状态: " . ($wafEnabled ? '是' : '否') . "\n";
    echo "WAF 超时时间: {$wafTimeout}秒\n";
    
    // 更新配置
    $configManager->set('waf.timeout', 10.0);
    echo "更新 WAF 超时时间为 10 秒\n";
    
    // 验证更新
    $newTimeout = $configManager->get('waf.timeout');
    echo "新的超时时间: {$newTimeout}秒\n\n";
    
    yield true;
}

// 主函数
function runBasicExamples(): \Generator
{
    echo "开始运行基础使用示例...\n\n";
    
    // 运行所有示例
    yield example1_basicRequestHandling();
    yield example2_securityThreatDetection();
    yield example3_proxyForwarding();
    yield example4_asyncLogging();
    yield example5_metricsCollection();
    yield example6_concurrentProcessing();
    yield example7_errorHandling();
    yield example8_configManagement();
    
    echo "所有示例运行完成！\n";
    echo "================\n";
    echo "示例总结:\n";
    echo "1. 基本请求处理: 演示如何处理正常请求\n";
    echo "2. 安全威胁检测: 演示如何检测和拦截攻击\n";
    echo "3. 代理转发: 演示如何转发请求到后端\n";
    echo "4. 异步日志记录: 演示如何记录日志\n";
    echo "5. 指标收集: 演示如何收集性能指标\n";
    echo "6. 并发处理: 演示如何处理并发请求\n";
    echo "7. 错误处理: 演示如何处理各种错误\n";
    echo "8. 配置管理: 演示如何管理配置\n\n";
    
    echo "使用建议:\n";
    echo "- 在生产环境中，建议启用所有安全检测规则\n";
    echo "- 定期检查日志和指标，及时发现异常\n";
    echo "- 根据实际需求调整配置参数\n";
    echo "- 使用监控工具跟踪系统性能\n";
}

// 运行示例
try {
    \PfinalClub\Asyncio\run(runBasicExamples());
} catch (Exception $e) {
    echo "示例运行失败: " . $e->getMessage() . "\n";
    echo "错误位置: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
