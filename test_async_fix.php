<?php

use PfinalClub\Asyncio\{create_task, gather, run, sleep};
use Tiangang\Waf\Gateway\TiangangGateway;
use Workerman\Protocols\Http\Request;

require_once __DIR__ . '/vendor/autoload.php';

echo "🚀 测试修复后的全异步架构...\n";

try {
    // 创建网关实例
    $gateway = new TiangangGateway();
    
    // 创建测试请求
    $request = new Request("GET /test HTTP/1.1");
    $request->header('User-Agent', 'AsyncFixTest/1.0');
    $request->header('X-Forwarded-For', '127.0.0.1');
    
    echo "📊 测试单个异步请求...\n";
    
    $startTime = microtime(true);
    $response = \PfinalClub\Asyncio\run($gateway->handle($request));
    $duration = microtime(true) - $startTime;
    
    echo "✅ 异步请求完成\n";
    echo "   响应时间: " . round($duration * 1000, 2) . "ms\n";
    echo "   响应状态: " . $response->getStatusCode() . "\n";
    echo "   响应内容: " . substr($response->getBody(), 0, 100) . "...\n\n";
    
    echo "📊 测试并发异步请求...\n";
    
    $concurrentCount = 3;
    $startTime = microtime(true);
    
    // 创建并发任务
    $tasks = [];
    for ($i = 0; $i < $concurrentCount; $i++) {
        $testRequest = new Request("GET /test{$i} HTTP/1.1");
        $testRequest->header('User-Agent', 'ConcurrentTest/1.0');
        $testRequest->header('X-Forwarded-For', '127.0.0.1');
        
        $tasks[] = $gateway->handle($testRequest);
    }
    
    // 并发执行
    $responses = \PfinalClub\Asyncio\run(\PfinalClub\Asyncio\gather($tasks));
    
    $duration = microtime(true) - $startTime;
    $qps = $concurrentCount / $duration;
    
    echo "✅ 并发请求完成\n";
    echo "   并发数: {$concurrentCount}\n";
    echo "   总时间: " . round($duration * 1000, 2) . "ms\n";
    echo "   QPS: " . round($qps, 2) . "\n";
    echo "   成功响应数: " . count($responses) . "\n\n";
    
    echo "🎉 全异步架构修复测试完成！\n";
    
} catch (Exception $e) {
    echo "❌ 测试失败: " . $e->getMessage() . "\n";
    echo "堆栈: " . $e->getTraceAsString() . "\n";
}
