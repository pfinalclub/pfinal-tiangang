<?php

require_once __DIR__ . '/vendor/autoload.php';

use Tiangang\Waf\Testing\RuleTestFramework;

echo "天罡 WAF 规则测试框架\n";
echo "==================\n\n";

$testFramework = new RuleTestFramework();

// 运行所有规则测试
echo "1. 运行所有规则测试...\n";
$startTime = microtime(true);
$results = $testFramework->runAllTests();
$endTime = microtime(true);

echo "测试完成，总耗时: " . round($endTime - $startTime, 2) . "秒\n\n";

// 显示测试结果
echo "2. 测试结果概览\n";
echo "==============\n";
echo "总测试数: {$results['total_tests']}\n";
echo "通过测试: {$results['passed_tests']}\n";
echo "失败测试: {$results['failed_tests']}\n";
echo "通过率: " . round(($results['passed_tests'] / $results['total_tests']) * 100, 2) . "%\n\n";

// 显示详细结果
echo "3. 详细测试结果\n";
echo "==============\n";
foreach ($results['test_results'] as $ruleName => $result) {
    echo "规则: {$ruleName}\n";
    echo "  状态: " . ($result['passed'] ? '✅ 通过' : '❌ 失败') . "\n";
    echo "  执行时间: " . round($result['execution_time'], 4) . "s\n";
    echo "  测试用例数: " . ($result['total_cases'] ?? 0) . "\n";
    
    if (isset($result['error'])) {
        echo "  错误: {$result['error']}\n";
    }
    
    if (isset($result['test_cases'])) {
        echo "  测试用例详情:\n";
        foreach ($result['test_cases'] as $testCase) {
            $status = $testCase['passed'] ? '✅' : '❌';
            echo "    - {$testCase['name']}: {$status}\n";
            if (!$testCase['passed'] && isset($testCase['error'])) {
                echo "      错误: {$testCase['error']}\n";
            }
        }
    }
    
    echo "\n";
}

// 性能测试
echo "4. 性能测试\n";
echo "==========\n";
$performanceRules = ['sql_injection', 'xss', 'rate_limit', 'ip_blacklist'];

foreach ($performanceRules as $ruleName) {
    echo "测试规则: {$ruleName}\n";
    $perfResult = $testFramework->performanceTest($ruleName, 1000);
    
    if (isset($perfResult['error'])) {
        echo "  错误: {$perfResult['error']}\n";
    } else {
        echo "  迭代次数: {$perfResult['iterations']}\n";
        echo "  总耗时: " . round($perfResult['total_time'], 4) . "s\n";
        echo "  平均耗时: " . round($perfResult['avg_time'] * 1000, 2) . "ms\n";
        echo "  内存使用: " . round($perfResult['memory_used'] / 1024, 2) . "KB\n";
        echo "  QPS: " . round($perfResult['requests_per_second'], 2) . "\n";
    }
    echo "\n";
}

// 生成测试报告
echo "5. 生成测试报告\n";
echo "==============\n";
$report = $testFramework->generateTestReport($results);

// 保存报告到文件
$reportFile = __DIR__ . '/runtime/reports/test_report_' . date('Y-m-d_H-i-s') . '.md';
$reportDir = dirname($reportFile);
if (!is_dir($reportDir)) {
    mkdir($reportDir, 0755, true);
}

file_put_contents($reportFile, $report);
echo "测试报告已保存到: {$reportFile}\n\n";

echo "规则测试完成！\n";
echo "============\n";
