<?php

require_once __DIR__ . '/../vendor/autoload.php';

use PfinalClub\Asyncio\{create_task, gather, wait_for, sleep, run};

/**
 * 天罡 WAF 测试运行器
 * 
 * 统一运行所有测试和示例
 */

echo "天罡 WAF 测试运行器\n";
echo "==================\n\n";

// 测试分类
$testCategories = [
    'debug' => [
        'name' => '调试测试',
        'description' => '基础异步功能调试和演示',
        'tests' => [
            'simple_async_test.php' => '简单异步测试',
            'basic_async_demo.php' => '基础异步演示',
            'simple_demo.php' => '简单演示',
            'async_demo.php' => '异步演示'
        ]
    ],
    'performance' => [
        'name' => '性能测试',
        'description' => '性能分析和对比测试',
        'tests' => [
            'async_performance_test.php' => '异步性能测试',
            'async_vs_sync_comparison.php' => '异步vs同步对比',
            'async_optimization_demo.php' => '异步优化演示',
            'performance_test.php' => '性能分析测试'
        ]
    ],
    'examples' => [
        'name' => '使用示例',
        'description' => '基础和高级使用示例',
        'tests' => [
            'basic-usage.php' => '基础使用示例',
            'advanced-usage.php' => '高级使用示例'
        ]
    ],
    'integration' => [
        'name' => '集成测试',
        'description' => '系统级集成测试',
        'tests' => [
            'phase6_test.php' => '阶段六集成测试',
            'phase7_test.php' => '阶段七集成测试'
        ]
    ]
];

// 运行单个测试
function runSingleTest(string $category, string $testFile, string $testName): \Generator
{
    echo "运行测试: {$testName}\n";
    echo "文件: {$testFile}\n";
    echo "分类: {$category}\n";
    echo "----------------------------------------\n";
    
    $startTime = microtime(true);
    
    try {
        // 执行测试文件
        $output = shell_exec("cd " . __DIR__ . " && php {$category}/{$testFile} 2>&1");
        
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

// 运行分类测试
function runCategoryTests(string $category, array $categoryInfo): \Generator
{
    echo "开始运行 {$categoryInfo['name']} 测试\n";
    echo "描述: {$categoryInfo['description']}\n";
    echo "测试数量: " . count($categoryInfo['tests']) . "\n";
    echo "========================================\n\n";
    
    $results = [];
    $totalDuration = 0;
    $successCount = 0;
    
    foreach ($categoryInfo['tests'] as $testFile => $testName) {
        $result = yield runSingleTest($category, $testFile, $testName);
        $results[] = $result;
        $totalDuration += $result['duration'];
        
        if ($result['success']) {
            $successCount++;
        }
        
        echo "\n";
    }
    
    echo "{$categoryInfo['name']} 测试完成\n";
    echo "成功: {$successCount}/" . count($categoryInfo['tests']) . "\n";
    echo "总耗时: " . round($totalDuration * 1000, 2) . "ms\n";
    echo "平均耗时: " . round($totalDuration / count($categoryInfo['tests']) * 1000, 2) . "ms/测试\n";
    echo "========================================\n\n";
    
    return [
        'category' => $category,
        'name' => $categoryInfo['name'],
        'total_tests' => count($categoryInfo['tests']),
        'success_count' => $successCount,
        'total_duration' => $totalDuration,
        'results' => $results
    ];
}

// 运行所有测试
function runAllTests(): \Generator
{
    global $testCategories;
    
    echo "开始运行所有测试...\n\n";
    
    $startTime = microtime(true);
    $allResults = [];
    
    // 运行每个分类的测试
    foreach ($testCategories as $category => $categoryInfo) {
        $result = yield runCategoryTests($category, $categoryInfo);
        $allResults[] = $result;
    }
    
    $endTime = microtime(true);
    $totalDuration = $endTime - $startTime;
    
    // 生成测试报告
    echo "测试报告\n";
    echo "========\n\n";
    
    $totalTests = 0;
    $totalSuccess = 0;
    $totalDuration = 0;
    
    foreach ($allResults as $result) {
        echo "分类: {$result['name']}\n";
        echo "测试数量: {$result['total_tests']}\n";
        echo "成功数量: {$result['success_count']}\n";
        echo "成功率: " . round($result['success_count'] / $result['total_tests'] * 100, 2) . "%\n";
        echo "耗时: " . round($result['total_duration'] * 1000, 2) . "ms\n";
        echo "----------------------------------------\n";
        
        $totalTests += $result['total_tests'];
        $totalSuccess += $result['success_count'];
        $totalDuration += $result['total_duration'];
    }
    
    echo "\n总体统计:\n";
    echo "总测试数量: {$totalTests}\n";
    echo "总成功数量: {$totalSuccess}\n";
    echo "总成功率: " . round($totalSuccess / $totalTests * 100, 2) . "%\n";
    echo "总耗时: " . round($totalDuration * 1000, 2) . "ms\n";
    echo "平均耗时: " . round($totalDuration / $totalTests * 1000, 2) . "ms/测试\n\n";
    
    echo "测试完成！\n";
    echo "==========\n";
}

// 运行特定分类测试
function runSpecificCategory(string $category): \Generator
{
    global $testCategories;
    
    if (!isset($testCategories[$category])) {
        echo "错误: 未知的分类 '{$category}'\n";
        echo "可用分类: " . implode(', ', array_keys($testCategories)) . "\n";
        return;
    }
    
    $result = yield runCategoryTests($category, $testCategories[$category]);
    
    echo "分类测试完成: {$result['name']}\n";
    echo "成功: {$result['success_count']}/{$result['total_tests']}\n";
    echo "耗时: " . round($result['total_duration'] * 1000, 2) . "ms\n";
}

// 显示帮助信息
function showHelp(): void
{
    global $testCategories;
    
    echo "天罡 WAF 测试运行器使用说明\n";
    echo "============================\n\n";
    echo "用法:\n";
    echo "  php run_all_tests.php [分类]\n\n";
    echo "可用分类:\n";
    
    foreach ($testCategories as $category => $info) {
        echo "  {$category}: {$info['name']} - {$info['description']}\n";
    }
    
    echo "\n示例:\n";
    echo "  php run_all_tests.php              # 运行所有测试\n";
    echo "  php run_all_tests.php debug          # 运行调试测试\n";
    echo "  php run_all_tests.php performance    # 运行性能测试\n";
    echo "  php run_all_tests.php examples       # 运行使用示例\n";
    echo "  php run_all_tests.php integration    # 运行集成测试\n";
    echo "  php run_all_tests.php help           # 显示帮助信息\n\n";
}

// 主函数
function main(): \Generator
{
    global $testCategories;
    
    $args = $argv ?? [];
    $category = $args[1] ?? 'all';
    
    if ($category === 'help') {
        showHelp();
        return;
    }
    
    if ($category === 'all') {
        yield runAllTests();
    } elseif (isset($testCategories[$category])) {
        yield runSpecificCategory($category);
    } else {
        echo "错误: 未知的分类 '{$category}'\n\n";
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
