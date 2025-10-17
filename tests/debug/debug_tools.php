<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use PfinalClub\Asyncio\{create_task, gather, wait_for, sleep, run};

/**
 * 天罡 WAF 调试工具
 * 
 * 提供各种调试和诊断功能
 */

echo "天罡 WAF 调试工具\n";
echo "================\n\n";

// 调试工具 1: 异步性能分析
function debugAsyncPerformance(): \Generator
{
    echo "调试工具 1: 异步性能分析\n";
    echo "------------------------\n";
    
    $startTime = microtime(true);
    
    // 创建多个异步任务
    $tasks = [];
    for ($i = 0; $i < 100; $i++) {
        $tasks[] = create_task(sleep(0.001));
    }
    
    // 并发执行所有任务
    yield gather(...$tasks);
    
    $endTime = microtime(true);
    $duration = $endTime - $startTime;
    
    echo "异步任务数量: 100\n";
    echo "总耗时: " . round($duration * 1000, 2) . "ms\n";
    echo "平均耗时: " . round($duration / 100 * 1000, 2) . "ms/任务\n";
    echo "性能状态: " . ($duration < 0.1 ? '优秀' : ($duration < 0.5 ? '良好' : '需要优化')) . "\n\n";
}

// 调试工具 2: 内存使用分析
function debugMemoryUsage(): \Generator
{
    echo "调试工具 2: 内存使用分析\n";
    echo "------------------------\n";
    
    $initialMemory = memory_get_usage(true);
    echo "初始内存: " . formatBytes($initialMemory) . "\n";
    
    // 创建大量数据
    $data = [];
    for ($i = 0; $i < 10000; $i++) {
        $data[] = [
            'id' => $i,
            'name' => "Item {$i}",
            'data' => str_repeat('x', 100)
        ];
    }
    
    $afterDataMemory = memory_get_usage(true);
    echo "数据创建后内存: " . formatBytes($afterDataMemory) . "\n";
    echo "数据占用内存: " . formatBytes($afterDataMemory - $initialMemory) . "\n";
    
    // 异步处理数据
    $tasks = [];
    foreach (array_chunk($data, 100) as $chunk) {
        $tasks[] = create_task(processDataChunk($chunk));
    }
    
    yield gather(...$tasks);
    
    $afterProcessMemory = memory_get_usage(true);
    echo "处理完成后内存: " . formatBytes($afterProcessMemory) . "\n";
    echo "处理占用内存: " . formatBytes($afterProcessMemory - $afterDataMemory) . "\n";
    
    // 清理数据
    unset($data);
    $finalMemory = memory_get_usage(true);
    echo "清理后内存: " . formatBytes($finalMemory) . "\n";
    echo "内存回收: " . formatBytes($afterProcessMemory - $finalMemory) . "\n\n";
}

// 处理数据块
function processDataChunk(array $chunk): \Generator
{
    yield sleep(0.001);
    return count($chunk);
}

// 调试工具 3: 错误处理测试
function debugErrorHandling(): \Generator
{
    echo "调试工具 3: 错误处理测试\n";
    echo "------------------------\n";
    
    $errorCases = [
        'division_by_zero' => function() { return 1 / 0; },
        'array_access' => function() { $arr = []; return $arr['nonexistent']; },
        'function_call' => function() { return nonexistentFunction(); },
        'async_error' => function() { yield sleep(0.001); throw new Exception('Async error'); }
    ];
    
    foreach ($errorCases as $caseName => $errorFunction) {
        echo "测试错误情况: {$caseName}\n";
        
        try {
            if ($caseName === 'async_error') {
                yield $errorFunction();
            } else {
                $errorFunction();
            }
            echo "结果: 未捕获到错误\n";
        } catch (Exception $e) {
            echo "结果: 捕获到错误 - " . $e->getMessage() . "\n";
        } catch (Error $e) {
            echo "结果: 捕获到致命错误 - " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }
}

// 调试工具 4: 并发控制测试
function debugConcurrencyControl(): \Generator
{
    echo "调试工具 4: 并发控制测试\n";
    echo "------------------------\n";
    
    $concurrencyLevels = [1, 5, 10, 20, 50];
    
    foreach ($concurrencyLevels as $level) {
        echo "测试并发级别: {$level}\n";
        
        $startTime = microtime(true);
        
        // 创建指定数量的并发任务
        $tasks = [];
        for ($i = 0; $i < $level; $i++) {
            $tasks[] = create_task(sleep(0.01));
        }
        
        // 并发执行
        yield gather(...$tasks);
        
        $endTime = microtime(true);
        $duration = $endTime - $startTime;
        
        echo "并发级别: {$level}\n";
        echo "执行耗时: " . round($duration * 1000, 2) . "ms\n";
        echo "平均耗时: " . round($duration / $level * 1000, 2) . "ms/任务\n";
        echo "效率: " . round($level / $duration, 2) . " 任务/秒\n";
        echo "----------------------------------------\n";
    }
    
    echo "\n";
}

// 调试工具 5: 配置验证
function debugConfigValidation(): \Generator
{
    echo "调试工具 5: 配置验证\n";
    echo "--------------------\n";
    
    $configs = [
        'waf.enabled' => true,
        'waf.timeout' => 5.0,
        'proxy.timeout' => 30.0,
        'monitoring.interval' => 60,
        'logging.level' => 'info'
    ];
    
    echo "验证配置项:\n";
    foreach ($configs as $key => $value) {
        echo "  {$key}: {$value} (" . gettype($value) . ")\n";
    }
    
    // 模拟配置验证
    $validationResults = [];
    foreach ($configs as $key => $value) {
        $isValid = validateConfigValue($key, $value);
        $validationResults[$key] = $isValid;
        echo "验证结果: {$key} - " . ($isValid ? '有效' : '无效') . "\n";
    }
    
    $validCount = count(array_filter($validationResults));
    $totalCount = count($validationResults);
    echo "配置验证完成: {$validCount}/{$totalCount} 有效\n\n";
}

// 验证配置值
function validateConfigValue(string $key, $value): bool
{
    switch ($key) {
        case 'waf.enabled':
            return is_bool($value);
        case 'waf.timeout':
        case 'proxy.timeout':
        case 'monitoring.interval':
            return is_numeric($value) && $value > 0;
        case 'logging.level':
            return in_array($value, ['debug', 'info', 'warning', 'error']);
        default:
            return true;
    }
}

// 调试工具 6: 系统信息收集
function debugSystemInfo(): \Generator
{
    echo "调试工具 6: 系统信息收集\n";
    echo "------------------------\n";
    
    echo "PHP 版本: " . PHP_VERSION . "\n";
    echo "操作系统: " . PHP_OS . "\n";
    echo "内存限制: " . ini_get('memory_limit') . "\n";
    echo "执行时间限制: " . ini_get('max_execution_time') . "秒\n";
    echo "当前内存使用: " . formatBytes(memory_get_usage(true)) . "\n";
    echo "峰值内存使用: " . formatBytes(memory_get_peak_usage(true)) . "\n";
    
    // 检查扩展
    $requiredExtensions = ['json', 'mbstring', 'curl', 'pdo'];
    echo "必需扩展检查:\n";
    foreach ($requiredExtensions as $ext) {
        $loaded = extension_loaded($ext);
        echo "  {$ext}: " . ($loaded ? '已加载' : '未加载') . "\n";
    }
    
    // 检查异步支持
    echo "异步支持检查:\n";
    echo "  Generator 支持: " . (class_exists('Generator') ? '是' : '否') . "\n";
    echo "  PfinalClub\\Asyncio 支持: " . (class_exists('PfinalClub\\Asyncio\\EventLoop') ? '是' : '否') . "\n";
    
    echo "\n";
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
function runDebugTools(): \Generator
{
    echo "开始运行调试工具...\n\n";
    
    yield debugAsyncPerformance();
    yield debugMemoryUsage();
    yield debugErrorHandling();
    yield debugConcurrencyControl();
    yield debugConfigValidation();
    yield debugSystemInfo();
    
    echo "调试工具运行完成！\n";
    echo "================\n";
    echo "调试工具总结:\n";
    echo "1. 异步性能分析: 测试异步任务执行性能\n";
    echo "2. 内存使用分析: 监控内存使用和回收\n";
    echo "3. 错误处理测试: 测试各种错误处理情况\n";
    echo "4. 并发控制测试: 测试不同并发级别的性能\n";
    echo "5. 配置验证: 验证系统配置的有效性\n";
    echo "6. 系统信息收集: 收集系统环境信息\n\n";
    
    echo "使用建议:\n";
    echo "- 定期运行性能分析，监控系统性能\n";
    echo "- 关注内存使用情况，避免内存泄漏\n";
    echo "- 测试错误处理机制，确保系统稳定性\n";
    echo "- 根据并发测试结果调整系统配置\n";
    echo "- 验证配置参数，确保系统正常运行\n";
    echo "- 收集系统信息，便于问题排查\n";
}

// 运行调试工具
try {
    \PfinalClub\Asyncio\run(runDebugTools());
} catch (Exception $e) {
    echo "调试工具运行失败: " . $e->getMessage() . "\n";
    echo "错误位置: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
