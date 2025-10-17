<?php

require_once __DIR__ . '/../vendor/autoload.php';

use PfinalClub\Asyncio\{create_task, gather, wait_for, sleep, run};

/**
 * 单元测试运行器
 * 
 * 运行所有单元测试并生成报告
 */

echo "天罡 WAF 单元测试运行器\n";
echo "======================\n\n";

// 测试文件列表
$testFiles = [
    'Unit/AsyncTestFramework.php' => '异步测试框架',
    'Unit/WafMiddlewareTest.php' => 'WAF 中间件测试',
    'Unit/ConfigManagerTest.php' => '配置管理器测试',
    'Unit/ProxyHandlerTest.php' => '代理处理器测试',
    'Unit/AsyncLoggerTest.php' => '异步日志记录器测试',
    'Unit/MetricsCollectorTest.php' => '指标收集器测试',
    'Unit/DecisionEngineTest.php' => '决策引擎测试',
    'Unit/PluginManagerTest.php' => '插件管理器测试'
];

// 运行单个测试文件
function runSingleTest(string $testFile, string $testName): \Generator
{
    echo "运行测试: {$testName}\n";
    echo "文件: {$testFile}\n";
    echo "----------------------------------------\n";
    
    $startTime = microtime(true);
    
    try {
        // 执行测试文件
        $output = shell_exec("cd " . __DIR__ . " && php {$testFile} 2>&1");
        
        $endTime = microtime(true);
        $duration = $endTime - $startTime;
        
        echo "测试输出:\n";
        echo $output . "\n";
        echo "测试耗时: " . round($duration * 1000, 2) . "ms\n";
        echo "测试状态: " . (strpos($output, 'Fatal error') === false ? '成功' : '失败') . "\n";
        
        return [
            'success' => strpos($output, 'Fatal error') === false,
            'duration' => $duration,
            'output' => $output
        ];
        
    } catch (Exception $e) {
        $endTime = microtime(true);
        $duration = $endTime - $startTime;
        
        echo "测试异常: " . $e->getMessage() . "\n";
        echo "测试耗时: " . round($duration * 1000, 2) . "ms\n";
        echo "测试状态: 失败\n";
        
        return [
            'success' => false,
            'duration' => $duration,
            'error' => $e->getMessage()
        ];
    }
    
    echo "\n";
}

// 运行所有单元测试
function runAllUnitTests(): \Generator
{
    global $testFiles;
    
    echo "开始运行所有单元测试...\n\n";
    
    $startTime = microtime(true);
    $results = [];
    $totalDuration = 0;
    $successCount = 0;
    
    foreach ($testFiles as $testFile => $testName) {
        $result = yield runSingleTest($testFile, $testName);
        $results[] = $result;
        $totalDuration += $result['duration'];
        
        if ($result['success']) {
            $successCount++;
        }
    }
    
    $endTime = microtime(true);
    $totalDuration = $endTime - $startTime;
    
    // 生成测试报告
    echo "单元测试报告\n";
    echo "============\n\n";
    
    echo "测试统计:\n";
    echo "总测试文件: " . count($testFiles) . "\n";
    echo "成功测试: {$successCount}\n";
    echo "失败测试: " . (count($testFiles) - $successCount) . "\n";
    echo "成功率: " . round($successCount / count($testFiles) * 100, 2) . "%\n";
    echo "总耗时: " . round($totalDuration * 1000, 2) . "ms\n";
    echo "平均耗时: " . round($totalDuration / count($testFiles) * 1000, 2) . "ms/测试\n\n";
    
    echo "详细结果:\n";
    echo "----------\n";
    
    foreach ($testFiles as $testFile => $testName) {
        $result = array_shift($results);
        $status = $result['success'] ? '✓ 成功' : '✗ 失败';
        $duration = round($result['duration'] * 1000, 2);
        echo "  {$testName}: {$status} ({$duration}ms)\n";
    }
    
    echo "\n";
    
    if ($successCount === count($testFiles)) {
        echo "🎉 所有单元测试通过！\n";
    } else {
        echo "⚠️  有 " . (count($testFiles) - $successCount) . " 个测试失败\n";
    }
    
    echo "\n";
}

// 运行特定测试
function runSpecificTest(string $testFile): \Generator
{
    global $testFiles;
    
    if (!isset($testFiles[$testFile])) {
        echo "错误: 未知的测试文件 '{$testFile}'\n";
        echo "可用测试文件:\n";
        foreach ($testFiles as $file => $name) {
            echo "  {$file}: {$name}\n";
        }
        return;
    }
    
    $result = yield runSingleTest($testFile, $testFiles[$testFile]);
    
    echo "测试完成: {$testFiles[$testFile]}\n";
    echo "结果: " . ($result['success'] ? '成功' : '失败') . "\n";
    echo "耗时: " . round($result['duration'] * 1000, 2) . "ms\n";
}

// 显示帮助信息
function showHelp(): void
{
    global $testFiles;
    
    echo "单元测试运行器使用说明\n";
    echo "======================\n\n";
    echo "用法:\n";
    echo "  php run_unit_tests.php [测试文件]\n\n";
    echo "可用测试文件:\n";
    
    foreach ($testFiles as $testFile => $testName) {
        echo "  {$testFile}: {$testName}\n";
    }
    
    echo "\n示例:\n";
    echo "  php run_unit_tests.php                    # 运行所有单元测试\n";
    echo "  php run_unit_tests.php Unit/WafMiddlewareTest.php  # 运行特定测试\n";
    echo "  php run_unit_tests.php help               # 显示帮助信息\n\n";
}

// 主函数
function main(): \Generator
{
    global $testFiles;
    
    $args = $argv ?? [];
    $testFile = $args[1] ?? 'all';
    
    if ($testFile === 'help') {
        showHelp();
        return;
    }
    
    if ($testFile === 'all') {
        yield runAllUnitTests();
    } elseif (isset($testFiles[$testFile])) {
        yield runSpecificTest($testFile);
    } else {
        echo "错误: 未知的测试文件 '{$testFile}'\n\n";
        showHelp();
    }
}

// 运行测试
try {
    \PfinalClub\Asyncio\run(main());
} catch (Exception $e) {
    echo "测试运行失败: " . $e->getMessage() . "\n";
    echo "错误位置: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
