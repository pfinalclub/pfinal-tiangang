<?php

/**
 * P0 高优先级问题修复验证测试
 * 
 * 运行此测试以验证所有 P0 修复是否正常工作
 */

require_once __DIR__ . '/../vendor/autoload.php';

require_once __DIR__ . '/Unit/QuickDetectorTest.php';
require_once __DIR__ . '/Unit/MetricsCollectorMemoryLeakTest.php';
require_once __DIR__ . '/Unit/TiangangGatewayLogTest.php';

echo "========================================\n";
echo "P0 高优先级问题修复验证测试\n";
echo "========================================\n\n";

$totalTests = 0;
$passedTests = 0;
$failedTests = 0;
$testResults = [];

/**
 * 运行测试类
 */
function runTestClass(string $className, string $testName): array
{
    global $totalTests, $passedTests, $failedTests, $testResults;
    
    echo "运行测试: {$testName}\n";
    echo str_repeat('-', 50) . "\n";
    
    try {
        $reflection = new ReflectionClass($className);
        $testInstance = $reflection->newInstance();
        
        // 获取所有测试方法
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
        $testMethods = array_filter($methods, function($method) {
            return strpos($method->getName(), 'test') === 0;
        });
        
        $classPassed = 0;
        $classFailed = 0;
        
        foreach ($testMethods as $method) {
            $totalTests++;
            $methodName = $method->getName();
            
            try {
                echo "  ✓ {$methodName}() ... ";
                
                // 调用 setUp 如果存在
                if ($reflection->hasMethod('setUp')) {
                    $setUpMethod = $reflection->getMethod('setUp');
                    $setUpMethod->invoke($testInstance);
                }
                
                // 运行测试方法
                $method->invoke($testInstance);
                
                echo "PASSED\n";
                $passedTests++;
                $classPassed++;
                
            } catch (\Exception $e) {
                echo "FAILED\n";
                echo "    错误: " . $e->getMessage() . "\n";
                if ($e->getPrevious()) {
                    echo "    原因: " . $e->getPrevious()->getMessage() . "\n";
                }
                $failedTests++;
                $classFailed++;
            }
        }
        
        $testResults[$testName] = [
            'passed' => $classPassed,
            'failed' => $classFailed,
            'total' => count($testMethods),
        ];
        
        echo "\n";
        return ['success' => true, 'passed' => $classPassed, 'failed' => $classFailed];
        
    } catch (\Exception $e) {
        echo "测试类加载失败: " . $e->getMessage() . "\n\n";
        $testResults[$testName] = [
            'passed' => 0,
            'failed' => 1,
            'total' => 1,
            'error' => $e->getMessage(),
        ];
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// 运行所有 P0 修复测试
echo "1. 测试白名单逻辑修复\n";
runTestClass(QuickDetectorTest::class, 'QuickDetectorTest');

echo "2. 测试内存泄漏修复\n";
runTestClass(MetricsCollectorMemoryLeakTest::class, 'MetricsCollectorMemoryLeakTest');

echo "3. 测试异步日志修复\n";
runTestClass(TiangangGatewayLogTest::class, 'TiangangGatewayLogTest');

// 输出测试总结
echo "\n";
echo "========================================\n";
echo "测试总结\n";
echo "========================================\n";
echo "总测试数: {$totalTests}\n";
echo "通过: {$passedTests}\n";
echo "失败: {$failedTests}\n";
echo "成功率: " . ($totalTests > 0 ? round(($passedTests / $totalTests) * 100, 2) : 0) . "%\n\n";

// 详细结果
echo "详细结果:\n";
foreach ($testResults as $testName => $result) {
    echo "  {$testName}: {$result['passed']}/{$result['total']} 通过";
    if (isset($result['error'])) {
        echo " (错误: {$result['error']})";
    }
    echo "\n";
}

echo "\n";

// 返回退出码
exit($failedTests > 0 ? 1 : 0);

