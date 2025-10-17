<?php

require_once __DIR__ . '/vendor/autoload.php';

// 直接使用命名空间，因为 functions.php 已经自动加载
// use Tiangang\Waf\Plugins\Waf\AdvancedAsyncRule;

echo "天罡 WAF 异步检测演示\n";
echo "====================\n\n";

// 演示 pfinal-asyncio 的基本使用
echo "1. 演示 pfinal-asyncio 基本功能...\n";

function asyncTask(string $name, float $delay): \Generator
{
    echo "  任务 {$name} 开始\n";
    yield \PfinalClub\Asyncio\sleep($delay);
    echo "  任务 {$name} 完成\n";
    return "结果 {$name}";
}

function concurrentDemo(): \Generator
{
    echo "  创建并发任务...\n";
    
    $tasks = [
        \PfinalClub\Asyncio\create_task(asyncTask('A', 1)),
        \PfinalClub\Asyncio\create_task(asyncTask('B', 2)),
        \PfinalClub\Asyncio\create_task(asyncTask('C', 1.5)),
    ];
    
    echo "  等待所有任务完成...\n";
    $results = yield \PfinalClub\Asyncio\gather(...$tasks);
    
    echo "  所有任务完成，结果: " . implode(', ', (array)$results) . "\n";
    return $results;
}

try {
    $startTime = microtime(true);
    $results = \PfinalClub\Asyncio\run(concurrentDemo());
    $endTime = microtime(true);
    
    echo "✓ 并发任务测试成功\n";
    echo "  - 总耗时: " . round($endTime - $startTime, 2) . "秒\n";
    echo "  - 结果数量: " . count((array)$results) . "\n";
} catch (Exception $e) {
    echo "✗ 并发任务测试失败: " . $e->getMessage() . "\n";
}

echo "\n";

// 演示超时控制
echo "2. 演示超时控制...\n";

function timeoutDemo(): \Generator
{
    try {
        $result = yield \PfinalClub\Asyncio\wait_for(asyncTask('超时测试', 3), 2.0);
        return $result;
    } catch (\PfinalClub\Asyncio\TimeoutException $e) {
        return "任务超时";
    }
}

try {
    $result = \PfinalClub\Asyncio\run(timeoutDemo());
    echo "✓ 超时控制测试成功\n";
    echo "  - 结果: " . (is_string($result) ? $result : json_encode($result)) . "\n";
} catch (Exception $e) {
    echo "✗ 超时控制测试失败: " . $e->getMessage() . "\n";
}

echo "\n";

// 演示异步检测器
echo "3. 演示异步检测器...\n";

try {
    $asyncDetector = new Tiangang\Waf\Detectors\AsyncDetector();
    
    $requestData = [
        'ip' => '127.0.0.1',
        'uri' => '/test',
        'method' => 'GET',
        'headers' => ['User-Agent' => 'Mozilla/5.0'],
        'query' => ['id' => "1' OR '1'='1"], // 模拟 SQL 注入
        'post' => [],
        'cookies' => [],
        'user_agent' => 'Mozilla/5.0',
        'referer' => '',
        'timestamp' => time(),
    ];
    
    $startTime = microtime(true);
    $results = $asyncDetector->check($requestData);
    $endTime = microtime(true);
    
    echo "✓ 异步检测器测试成功\n";
    echo "  - 检测耗时: " . round(($endTime - $startTime) * 1000, 2) . "ms\n";
    echo "  - 检测结果数量: " . count((array)$results) . "\n";
    
    foreach ($results as $result) {
        if ($result['matched'] ?? false) {
            echo "  - 匹配规则: " . ($result['rule'] ?? 'unknown') . "\n";
            echo "  - 严重程度: " . ($result['severity'] ?? 'unknown') . "\n";
            echo "  - 描述: " . ($result['description'] ?? 'unknown') . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "✗ 异步检测器测试失败: " . $e->getMessage() . "\n";
}

echo "\n";

echo "异步检测演示完成！\n";
echo "==================\n";
