<?php

require_once __DIR__ . '/vendor/autoload.php';

use PfinalClub\Asyncio\{create_task, gather, wait_for, sleep, run};
use Tiangang\Waf\Performance\PerformanceAnalyzer;
use Tiangang\Waf\Performance\PerformanceTracker;
use Tiangang\Waf\Performance\PerformanceDashboard;

echo "天罡 WAF 性能分析和追踪测试\n";
echo "==========================\n\n";

// 模拟性能分析测试
function simulatePerformanceAnalysis(): \Generator
{
    echo "1. 性能分析测试...\n";
    
    $analyzer = new PerformanceAnalyzer();
    
    $startTime = microtime(true);
    
    // 并发执行多个性能分析
    $tasks = [];
    for ($i = 0; $i < 50; $i++) {
        $analysisId = yield $analyzer->asyncStartAnalysis("test_operation_{$i}", [
            'request_id' => "req_{$i}",
            'user_id' => "user_" . ($i % 10),
            'component' => 'waf_middleware'
        ]);
        
        // 模拟操作执行时间
        yield sleep(rand(1, 10) / 1000);
        
        $tasks[] = create_task($analyzer->asyncEndAnalysis($analysisId, [
            'status' => 'success',
            'rules_checked' => rand(5, 20),
            'cache_hits' => rand(0, 5)
        ]));
    }
    
    yield gather(...$tasks);
    
    $endTime = microtime(true);
    $duration = $endTime - $startTime;
    
    echo "✓ 性能分析完成\n";
    echo "  - 分析操作数: 50\n";
    echo "  - 总耗时: " . round($duration * 1000, 2) . "ms\n";
    echo "  - 平均耗时: " . round($duration / 50 * 1000, 2) . "ms/操作\n\n";
}

// 模拟性能追踪测试
function simulatePerformanceTracking(): \Generator
{
    echo "2. 性能追踪测试...\n";
    
    $tracker = new PerformanceTracker();
    
    $startTime = microtime(true);
    
    // 并发执行多个性能追踪
    $tasks = [];
    for ($i = 0; $i < 30; $i++) {
        $traceId = yield $tracker->asyncStartTrace("trace_{$i}", "component_" . ($i % 5), [
            'request_id' => "req_{$i}",
            'operation' => 'detection',
            'priority' => 'high'
        ]);
        
        // 模拟追踪执行时间
        yield sleep(rand(2, 15) / 1000);
        
        $tasks[] = create_task($tracker->asyncEndTrace($traceId, [
            'rules_executed' => rand(3, 15),
            'cache_operations' => rand(1, 8),
            'database_queries' => rand(0, 3)
        ]));
    }
    
    yield gather(...$tasks);
    
    $endTime = microtime(true);
    $duration = $endTime - $startTime;
    
    echo "✓ 性能追踪完成\n";
    echo "  - 追踪操作数: 30\n";
    echo "  - 总耗时: " . round($duration * 1000, 2) . "ms\n";
    echo "  - 平均耗时: " . round($duration / 30 * 1000, 2) . "ms/操作\n\n";
}

// 模拟性能仪表板测试
function simulatePerformanceDashboard(): \Generator
{
    echo "3. 性能仪表板测试...\n";
    
    $dashboard = new PerformanceDashboard();
    
    $startTime = microtime(true);
    
    // 并发获取多个仪表板数据
    $tasks = [
        create_task($dashboard->asyncGetDashboardData('1h')),
        create_task($dashboard->asyncGetDashboardData('1d')),
        create_task($dashboard->asyncGetPerformanceReport('1h')),
        create_task($dashboard->asyncExportPerformanceData('1h', 'json')),
        create_task($dashboard->asyncExportPerformanceData('1h', 'csv'))
    ];
    
    $results = yield gather(...$tasks);
    
    $endTime = microtime(true);
    $duration = $endTime - $startTime;
    
    echo "✓ 性能仪表板完成\n";
    echo "  - 仪表板数据: 2个时间段\n";
    echo "  - 性能报告: 1个\n";
    echo "  - 数据导出: 2种格式\n";
    echo "  - 总耗时: " . round($duration * 1000, 2) . "ms\n";
    echo "  - 平均耗时: " . round($duration / 5 * 1000, 2) . "ms/操作\n\n";
}

// 模拟综合性能测试
function simulateComprehensivePerformanceTest(): \Generator
{
    echo "4. 综合性能测试...\n";
    
    $analyzer = new PerformanceAnalyzer();
    $tracker = new PerformanceTracker();
    $dashboard = new PerformanceDashboard();
    
    $startTime = microtime(true);
    
    // 并发执行所有性能测试
    $tasks = [];
    
    // 性能分析
    for ($i = 0; $i < 20; $i++) {
        $analysisId = yield $analyzer->asyncStartAnalysis("comprehensive_test_{$i}", [
            'test_type' => 'comprehensive',
            'iteration' => $i
        ]);
        
        yield sleep(rand(1, 5) / 1000);
        
        $tasks[] = create_task($analyzer->asyncEndAnalysis($analysisId, [
            'test_result' => 'success',
            'performance_score' => rand(80, 100)
        ]));
    }
    
    // 性能追踪
    for ($i = 0; $i < 15; $i++) {
        $traceId = yield $tracker->asyncStartTrace("comprehensive_trace_{$i}", "test_component", [
            'test_type' => 'comprehensive',
            'iteration' => $i
        ]);
        
        yield sleep(rand(1, 8) / 1000);
        
        $tasks[] = create_task($tracker->asyncEndTrace($traceId, [
            'test_metrics' => [
                'cpu_usage' => rand(10, 80),
                'memory_usage' => rand(1024, 8192),
                'io_operations' => rand(5, 50)
            ]
        ]));
    }
    
    // 仪表板数据
    $tasks[] = create_task($dashboard->asyncGetDashboardData('1h'));
    $tasks[] = create_task($dashboard->asyncGetPerformanceReport('1h'));
    
    $results = yield gather(...$tasks);
    
    $endTime = microtime(true);
    $duration = $endTime - $startTime;
    
    echo "✓ 综合性能测试完成\n";
    echo "  - 性能分析: 20个\n";
    echo "  - 性能追踪: 15个\n";
    echo "  - 仪表板数据: 2个\n";
    echo "  - 总耗时: " . round($duration * 1000, 2) . "ms\n";
    echo "  - 平均耗时: " . round($duration / 37 * 1000, 2) . "ms/操作\n\n";
}

// 模拟性能基准测试
function simulatePerformanceBenchmark(): \Generator
{
    echo "5. 性能基准测试...\n";
    
    $analyzer = new PerformanceAnalyzer();
    
    $startTime = microtime(true);
    
    // 执行基准测试
    $benchmarkResults = [];
    $testCases = [
        'light_operation' => 100,
        'medium_operation' => 50,
        'heavy_operation' => 20
    ];
    
    foreach ($testCases as $testType => $count) {
        $testStartTime = microtime(true);
        
        $tasks = [];
        for ($i = 0; $i < $count; $i++) {
            $analysisId = yield $analyzer->asyncStartAnalysis("benchmark_{$testType}_{$i}", [
                'test_type' => $testType,
                'iteration' => $i
            ]);
            
            // 模拟不同复杂度的操作
            $sleepTime = match($testType) {
                'light_operation' => 0.001,
                'medium_operation' => 0.005,
                'heavy_operation' => 0.01,
                default => 0.001
            };
            
            yield sleep($sleepTime);
            
            $tasks[] = create_task($analyzer->asyncEndAnalysis($analysisId, [
                'complexity' => $testType,
                'execution_time' => $sleepTime
            ]));
        }
        
        yield gather(...$tasks);
        
        $testEndTime = microtime(true);
        $testDuration = $testEndTime - $testStartTime;
        
        $benchmarkResults[$testType] = [
            'count' => $count,
            'duration' => $testDuration,
            'ops_per_second' => $count / $testDuration,
            'avg_time_per_op' => $testDuration / $count
        ];
    }
    
    $endTime = microtime(true);
    $totalDuration = $endTime - $startTime;
    
    echo "✓ 性能基准测试完成\n";
    echo "  - 轻量操作: {$benchmarkResults['light_operation']['count']}次, " . 
         round($benchmarkResults['light_operation']['ops_per_second'], 2) . " ops/s\n";
    echo "  - 中等操作: {$benchmarkResults['medium_operation']['count']}次, " . 
         round($benchmarkResults['medium_operation']['ops_per_second'], 2) . " ops/s\n";
    echo "  - 重量操作: {$benchmarkResults['heavy_operation']['count']}次, " . 
         round($benchmarkResults['heavy_operation']['ops_per_second'], 2) . " ops/s\n";
    echo "  - 总耗时: " . round($totalDuration * 1000, 2) . "ms\n\n";
}

// 主测试函数
function runPerformanceTests(): \Generator
{
    echo "开始性能分析和追踪测试...\n\n";
    
    // 执行各个性能测试
    yield simulatePerformanceAnalysis();
    yield simulatePerformanceTracking();
    yield simulatePerformanceDashboard();
    yield simulateComprehensivePerformanceTest();
    yield simulatePerformanceBenchmark();
    
    echo "性能分析和追踪测试完成！\n";
    echo "======================\n";
    echo "总结:\n";
    echo "- 性能分析: 异步分析系统性能指标\n";
    echo "- 性能追踪: 异步追踪组件性能\n";
    echo "- 性能仪表板: 实时监控和报告\n";
    echo "- 综合测试: 多组件协同性能测试\n";
    echo "- 基准测试: 性能基准和对比\n\n";
    
    echo "性能分析优势:\n";
    echo "1. 实时监控: 实时追踪系统性能\n";
    echo "2. 详细分析: 深入分析性能瓶颈\n";
    echo "3. 趋势预测: 预测性能趋势和问题\n";
    echo "4. 自动告警: 自动检测性能问题\n";
    echo "5. 可视化: 直观的性能仪表板\n";
    echo "6. 数据导出: 支持多种格式导出\n";
}

// 运行测试
try {
    \PfinalClub\Asyncio\run(runPerformanceTests());
} catch (Exception $e) {
    echo "测试失败: " . $e->getMessage() . "\n";
    echo "错误位置: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
