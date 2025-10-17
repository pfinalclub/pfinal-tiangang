<?php

require_once __DIR__ . '/vendor/autoload.php';

// 直接使用命名空间，因为 functions.php 已经自动加载

echo "pfinalclub/asyncio 使用对比演示\n";
echo "==============================\n\n";

// 模拟一些耗时的检测任务
function simulateDetection($name, $delay) {
    echo "  [{$name}] 开始检测...\n";
    usleep($delay * 1000); // 模拟耗时操作
    echo "  [{$name}] 检测完成\n";
    return "{$name} 检测结果";
}

function simulateAsyncDetection($name, $delay) {
    echo "  [{$name}] 开始异步检测...\n";
    yield \PfinalClub\Asyncio\sleep($delay);
    echo "  [{$name}] 异步检测完成\n";
    return "{$name} 异步检测结果";
}

echo "1. 传统同步方式（不使用 pfinal-asyncio）\n";
echo "=====================================\n";

$startTime = microtime(true);

// 同步执行多个检测任务
$results1 = [];
$results1[] = simulateDetection('SQL注入检测', 100);
$results1[] = simulateDetection('XSS检测', 150);
$results1[] = simulateDetection('CSRF检测', 200);
$results1[] = simulateDetection('Bot检测', 300);

$endTime = microtime(true);
$syncTime = $endTime - $startTime;

echo "同步执行结果: " . implode(', ', $results1) . "\n";
echo "同步执行耗时: " . round($syncTime * 1000, 2) . "ms\n";
echo "理论最小耗时: " . (100 + 150 + 200 + 300) . "ms\n\n";

echo "2. 使用 pfinal-asyncio 异步方式\n";
echo "=============================\n";

$startTime = microtime(true);

// 异步并发执行检测任务
$asyncDemo = function() {
        $tasks = [
            \PfinalClub\Asyncio\create_task(simulateAsyncDetection('SQL注入检测', 0.1)),
            \PfinalClub\Asyncio\create_task(simulateAsyncDetection('XSS检测', 0.15)),
            \PfinalClub\Asyncio\create_task(simulateAsyncDetection('CSRF检测', 0.2)),
            \PfinalClub\Asyncio\create_task(simulateAsyncDetection('Bot检测', 0.3)),
        ];
        
        $results = yield \PfinalClub\Asyncio\gather(...$tasks);
    return $results;
};

$results2 = \PfinalClub\Asyncio\run($asyncDemo());

$endTime = microtime(true);
$asyncTime = $endTime - $startTime;

echo "异步执行结果: " . implode(', ', (array)$results2) . "\n";
echo "异步执行耗时: " . round($asyncTime * 1000, 2) . "ms\n";
echo "理论最小耗时: " . max(100, 150, 200, 300) . "ms (最长任务时间)\n\n";

echo "3. 性能对比分析\n";
echo "==============\n";
echo "同步方式总耗时: " . round($syncTime * 1000, 2) . "ms\n";
echo "异步方式总耗时: " . round($asyncTime * 1000, 2) . "ms\n";
echo "性能提升: " . round(($syncTime / $asyncTime), 2) . "x\n";
echo "时间节省: " . round(($syncTime - $asyncTime) * 1000, 2) . "ms\n\n";

echo "4. 实际 WAF 场景对比\n";
echo "===================\n";

// 模拟真实的 WAF 检测场景
function simulateWafDetection($requestData) {
    $detections = [
        'IP黑名单检查' => 5,
        '频率限制检查' => 10,
        'SQL注入检测' => 50,
        'XSS检测' => 30,
        'CSRF检测' => 40,
        '文件上传检测' => 60,
        '路径遍历检测' => 20,
        'Bot行为分析' => 100,
    ];
    
    $results = [];
    foreach ($detections as $name => $delay) {
        echo "  执行 {$name}...\n";
        usleep($delay * 1000);
        $results[] = "{$name}: 通过";
    }
    
    return $results;
}

function simulateAsyncWafDetection($requestData) {
    $detections = [
        'IP黑名单检查' => 0.005,
        '频率限制检查' => 0.01,
        'SQL注入检测' => 0.05,
        'XSS检测' => 0.03,
        'CSRF检测' => 0.04,
        '文件上传检测' => 0.06,
        '路径遍历检测' => 0.02,
        'Bot行为分析' => 0.1,
    ];
    
    $tasks = [];
    foreach ($detections as $name => $delay) {
        $tasks[] = \PfinalClub\Asyncio\create_task(asyncDetection($name, $delay));
    }
    
    return yield \PfinalClub\Asyncio\gather(...$tasks);
}

function asyncDetection($name, $delay) {
    echo "  异步执行 {$name}...\n";
    yield \PfinalClub\Asyncio\sleep($delay);
    return "{$name}: 通过";
}

echo "同步 WAF 检测:\n";
$startTime = microtime(true);
$syncResults = simulateWafDetection([]);
$endTime = microtime(true);
echo "同步检测耗时: " . round(($endTime - $startTime) * 1000, 2) . "ms\n\n";

echo "异步 WAF 检测:\n";
$startTime = microtime(true);
$asyncResults = \PfinalClub\Asyncio\run(simulateAsyncWafDetection([]));
$endTime = microtime(true);
echo "异步检测耗时: " . round(($endTime - $startTime) * 1000, 2) . "ms\n\n";

echo "5. 关键区别总结\n";
echo "==============\n";
echo "不使用 pfinal-asyncio:\n";
echo "  - 串行执行，总耗时 = 所有任务耗时之和\n";
echo "  - 阻塞主线程，影响并发处理能力\n";
echo "  - 无法充分利用 I/O 等待时间\n";
echo "  - 扩展性差，任务越多耗时越长\n\n";

echo "使用 pfinal-asyncio:\n";
echo "  - 并发执行，总耗时 ≈ 最长任务耗时\n";
echo "  - 非阻塞，支持高并发处理\n";
echo "  - 充分利用 I/O 等待时间\n";
echo "  - 扩展性好，任务数量对总耗时影响小\n\n";

echo "6. 适用场景\n";
echo "==========\n";
echo "适合使用 pfinal-asyncio 的场景:\n";
echo "  - 多个独立的检测任务\n";
echo "  - 涉及 I/O 操作（数据库查询、API调用）\n";
echo "  - 需要高并发处理能力\n";
echo "  - 对响应时间要求严格\n\n";

echo "不适合使用 pfinal-asyncio 的场景:\n";
echo "  - 简单的计算任务\n";
echo "  - 任务之间有强依赖关系\n";
echo "  - 内存使用要求极低\n";
echo "  - 任务数量很少（< 3个）\n\n";

echo "演示完成！\n";
echo "========\n";
