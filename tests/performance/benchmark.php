<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use PfinalClub\Asyncio\{create_task, gather, wait_for, sleep, run};

/**
 * 天罡 WAF 性能基准测试
 * 
 * 测试系统在各种负载下的性能表现
 */

echo "天罡 WAF 性能基准测试\n";
echo "====================\n\n";

// 基准测试 1: 请求处理性能
function benchmarkRequestProcessing(): void
{
    echo "基准测试 1: 请求处理性能\n";
    echo "------------------------\n";
    
    $requestCounts = [100, 500, 1000, 2000, 5000];
    
    foreach ($requestCounts as $count) {
        echo "测试请求数量: {$count}\n";
        
        $startTime = microtime(true);
        
        // 模拟请求处理
        $tasks = [];
        for ($i = 0; $i < $count; $i++) {
            $tasks[] = create_task(fn() => processRequest($i));
        }
        
        $results = gather(...$tasks);
        
        $endTime = microtime(true);
        $duration = $endTime - $startTime;
        
        $qps = $count / $duration;
        $avgResponseTime = $duration / $count * 1000;
        
        echo "  总耗时: " . round($duration * 1000, 2) . "ms\n";
        echo "  QPS: " . round($qps, 2) . "\n";
        echo "  平均响应时间: " . round($avgResponseTime, 2) . "ms\n";
        echo "  处理成功: " . count(array_filter($results)) . "/{$count}\n";
        echo "  ----------------------------------------\n";
    }
    
    echo "\n";
}

// 处理单个请求
function processRequest(int $requestId): array
{
    // 模拟请求处理时间
    sleep(0.001);
    
    // 模拟一些处理逻辑
    $data = [
        'id' => $requestId,
        'timestamp' => microtime(true),
        'processed' => true
    ];
    
    return $data;
}

// 基准测试 2: 内存使用性能
function benchmarkMemoryUsage(): void
{
    echo "基准测试 2: 内存使用性能\n";
    echo "------------------------\n";
    
    $dataSizes = [1000, 5000, 10000, 50000, 100000];
    
    foreach ($dataSizes as $size) {
        echo "测试数据大小: {$size}\n";
        
        $initialMemory = memory_get_usage(true);
        
        // 创建大量数据
        $data = [];
        for ($i = 0; $i < $size; $i++) {
            $data[] = [
                'id' => $i,
                'name' => "Item {$i}",
                'data' => str_repeat('x', 100)
            ];
        }
        
        $afterDataMemory = memory_get_usage(true);
        $dataMemory = $afterDataMemory - $initialMemory;
        
        // 异步处理数据
        $tasks = [];
        foreach (array_chunk($data, 100) as $chunk) {
            $tasks[] = create_task(fn() => processDataChunk($chunk));
        }
        
        gather(...$tasks);
        
        $afterProcessMemory = memory_get_usage(true);
        $processMemory = $afterProcessMemory - $afterDataMemory;
        
        echo "  初始内存: " . formatBytes($initialMemory) . "\n";
        echo "  数据内存: " . formatBytes($dataMemory) . "\n";
        echo "  处理内存: " . formatBytes($processMemory) . "\n";
        echo "  总内存: " . formatBytes($afterProcessMemory) . "\n";
        echo "  内存效率: " . round($dataMemory / $size, 2) . " bytes/item\n";
        echo "  ----------------------------------------\n";
        
        // 清理数据
        unset($data);
    }
    
    echo "\n";
}

// 处理数据块
function processDataChunk(array $chunk): int
{
    sleep(0.001);
    return count($chunk);
}

// 基准测试 3: 并发性能
function benchmarkConcurrency(): void
{
    echo "基准测试 3: 并发性能\n";
    echo "--------------------\n";
    
    $concurrencyLevels = [1, 5, 10, 20, 50, 100];
    
    foreach ($concurrencyLevels as $level) {
        echo "测试并发级别: {$level}\n";
        
        $startTime = microtime(true);
        
        // 创建并发任务
        $tasks = [];
        for ($i = 0; $i < $level; $i++) {
            $tasks[] = create_task(fn() => concurrentTask($i));
        }
        
        $results = gather(...$tasks);
        
        $endTime = microtime(true);
        $duration = $endTime - $startTime;
        
        $throughput = $level / $duration;
        $avgTaskTime = $duration / $level * 1000;
        
        echo "  并发级别: {$level}\n";
        echo "  执行耗时: " . round($duration * 1000, 2) . "ms\n";
        echo "  吞吐量: " . round($throughput, 2) . " 任务/秒\n";
        echo "  平均任务时间: " . round($avgTaskTime, 2) . "ms\n";
        echo "  任务完成率: " . count(array_filter($results)) . "/{$level}\n";
        echo "  ----------------------------------------\n";
    }
    
    echo "\n";
}

// 并发任务
function concurrentTask(int $taskId): string
{
    sleep(0.01);
    return $taskId;
}

// 基准测试 4: 数据库操作性能
function benchmarkDatabaseOperations(): void
{
    echo "基准测试 4: 数据库操作性能\n";
    echo "--------------------------\n";
    
    $operationCounts = [10, 50, 100, 200, 500];
    
    foreach ($operationCounts as $count) {
        echo "测试操作数量: {$count}\n";
        
        $startTime = microtime(true);
        
        // 模拟数据库操作
        $tasks = [];
        for ($i = 0; $i < $count; $i++) {
            $tasks[] = create_task(fn() => simulateDatabaseOperation($i));
        }
        
        $results = gather(...$tasks);
        
        $endTime = microtime(true);
        $duration = $endTime - $startTime;
        
        $opsPerSecond = $count / $duration;
        $avgOperationTime = $duration / $count * 1000;
        
        echo "  操作数量: {$count}\n";
        echo "  总耗时: " . round($duration * 1000, 2) . "ms\n";
        echo "  操作/秒: " . round($opsPerSecond, 2) . "\n";
        echo "  平均操作时间: " . round($avgOperationTime, 2) . "ms\n";
        echo "  操作成功率: " . count(array_filter($results)) . "/{$count}\n";
        echo "  ----------------------------------------\n";
    }
    
    echo "\n";
}

// 模拟数据库操作
function simulateDatabaseOperation(int $operationId): array
{
    // 模拟数据库查询时间
    sleep(0.005);
    
    return [
        'id' => $operationId,
        'result' => 'success',
        'timestamp' => microtime(true)
    ];
}

// 基准测试 5: 缓存操作性能
function benchmarkCacheOperations(): void
{
    echo "基准测试 5: 缓存操作性能\n";
    echo "------------------------\n";
    
    $cacheSizes = [100, 500, 1000, 2000, 5000];
    
    foreach ($cacheSizes as $size) {
        echo "测试缓存大小: {$size}\n";
        
        $startTime = microtime(true);
        
        // 模拟缓存操作
        $tasks = [];
        for ($i = 0; $i < $size; $i++) {
            $tasks[] = create_task(fn() => simulateCacheOperation($i));
        }
        
        $results = gather(...$tasks);
        
        $endTime = microtime(true);
        $duration = $endTime - $startTime;
        
        $opsPerSecond = $size / $duration;
        $avgOperationTime = $duration / $size * 1000;
        
        echo "  缓存大小: {$size}\n";
        echo "  总耗时: " . round($duration * 1000, 2) . "ms\n";
        echo "  操作/秒: " . round($opsPerSecond, 2) . "\n";
        echo "  平均操作时间: " . round($avgOperationTime, 2) . "ms\n";
        echo "  操作成功率: " . count(array_filter($results)) . "/{$size}\n";
        echo "  ----------------------------------------\n";
    }
    
    echo "\n";
}

// 模拟缓存操作
function simulateCacheOperation(int $key): array
{
    // 模拟缓存读写时间
    sleep(0.001);
    
    return [
        'key' => $key,
        'value' => "cached_value_{$key}",
        'timestamp' => microtime(true)
    ];
}

// 基准测试 6: 系统负载测试
function benchmarkSystemLoad(): void
{
    echo "基准测试 6: 系统负载测试\n";
    echo "------------------------\n";
    
    $loadLevels = [0.1, 0.2, 0.5, 1.0, 2.0]; // 秒
    
    foreach ($loadLevels as $load) {
        echo "测试负载级别: {$load}秒\n";
        
        $startTime = microtime(true);
        
        // 创建负载任务
        $tasks = [];
        for ($i = 0; $i < 10; $i++) {
            $tasks[] = create_task(fn() => loadTask($load));
        }
        
        $results = gather(...$tasks);
        
        $endTime = microtime(true);
        $duration = $endTime - $startTime;
        
        $efficiency = $load / $duration;
        $avgTaskTime = $duration / 10 * 1000;
        
        echo "  负载级别: {$load}秒\n";
        echo "  实际耗时: " . round($duration * 1000, 2) . "ms\n";
        echo "  效率: " . round($efficiency, 2) . "\n";
        echo "  平均任务时间: " . round($avgTaskTime, 2) . "ms\n";
        echo "  任务完成率: " . count(array_filter($results)) . "/10\n";
        echo "  ----------------------------------------\n";
    }
    
    echo "\n";
}

// 负载任务
function loadTask(float $load): bool
{
    sleep($load);
    return microtime(true);
}

// 格式化字节数
function formatBytes(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
}

// 主函数
function runBenchmarks(): void
{
    echo "开始运行性能基准测试...\n\n";
    
    benchmarkRequestProcessing();
    benchmarkMemoryUsage();
    benchmarkConcurrency();
    benchmarkDatabaseOperations();
    benchmarkCacheOperations();
    benchmarkSystemLoad();
    
    echo "性能基准测试完成！\n";
    echo "==================\n";
    echo "基准测试总结:\n";
    echo "1. 请求处理性能: 测试不同请求数量的处理能力\n";
    echo "2. 内存使用性能: 测试内存使用效率和回收\n";
    echo "3. 并发性能: 测试不同并发级别的性能表现\n";
    echo "4. 数据库操作性能: 测试数据库操作的性能\n";
    echo "5. 缓存操作性能: 测试缓存操作的性能\n";
    echo "6. 系统负载测试: 测试系统在不同负载下的表现\n\n";
    
    echo "性能优化建议:\n";
    echo "- 根据基准测试结果调整系统配置\n";
    echo "- 监控内存使用，避免内存泄漏\n";
    echo "- 优化并发处理，提高吞吐量\n";
    echo "- 使用缓存减少数据库访问\n";
    echo "- 根据负载情况调整资源分配\n";
    echo "- 定期进行性能测试，持续优化\n";
}

// 运行基准测试
try {
    \PfinalClub\Asyncio\run(fn() => runBenchmarks());
} catch (Exception $e) {
    echo "基准测试运行失败: " . $e->getMessage() . "\n";
    echo "错误位置: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
