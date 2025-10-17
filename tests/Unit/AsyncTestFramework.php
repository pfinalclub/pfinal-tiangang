<?php

namespace Tiangang\Waf\Tests\Unit;

use PfinalClub\Asyncio\{create_task, gather, wait_for, sleep, run};
use PHPUnit\Framework\TestCase;

/**
 * 异步测试框架
 * 
 * 为异步组件提供测试支持
 */
class AsyncTestFramework extends TestCase
{
    private array $testResults = [];
    private array $testMetrics = [];

    /**
     * 异步运行测试用例
     */
    public function asyncRunTest(string $testName, callable $testFunction): \Generator
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        
        try {
            $result = yield $testFunction();
            $endTime = microtime(true);
            $endMemory = memory_get_usage(true);
            
            $this->testResults[$testName] = [
                'status' => 'passed',
                'result' => $result,
                'duration' => $endTime - $startTime,
                'memory_used' => $endMemory - $startMemory,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
            return $result;
            
        } catch (\Exception $e) {
            $endTime = microtime(true);
            $endMemory = memory_get_usage(true);
            
            $this->testResults[$testName] = [
                'status' => 'failed',
                'error' => $e->getMessage(),
                'duration' => $endTime - $startTime,
                'memory_used' => $endMemory - $startMemory,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
            throw $e;
        }
    }

    /**
     * 异步运行多个测试用例
     */
    public function asyncRunTests(array $tests): \Generator
    {
        $startTime = microtime(true);
        
        $tasks = [];
        foreach ($tests as $testName => $testFunction) {
            $tasks[] = create_task($this->asyncRunTest($testName, $testFunction));
        }
        
        $results = yield gather(...$tasks);
        
        $endTime = microtime(true);
        $this->testMetrics['total_duration'] = $endTime - $startTime;
        $this->testMetrics['total_tests'] = count($tests);
        $this->testMetrics['passed_tests'] = count(array_filter($this->testResults, fn($r) => $r['status'] === 'passed'));
        $this->testMetrics['failed_tests'] = count(array_filter($this->testResults, fn($r) => $r['status'] === 'failed'));
        
        return $results;
    }

    /**
     * 异步断言相等
     */
    public function asyncAssertEquals(mixed $expected, mixed $actual, string $message = ''): \Generator
    {
        yield sleep(0.001);
        
        if ($expected !== $actual) {
            throw new \Exception("Assertion failed: {$message}. Expected: " . json_encode($expected) . ", Actual: " . json_encode($actual));
        }
        
        return true;
    }

    /**
     * 异步断言为真
     */
    public function asyncAssertTrue(mixed $condition, string $message = ''): \Generator
    {
        yield sleep(0.001);
        
        if (!$condition) {
            throw new \Exception("Assertion failed: {$message}. Expected true, got false");
        }
        
        return true;
    }

    /**
     * 异步断言为假
     */
    public function asyncAssertFalse(mixed $condition, string $message = ''): \Generator
    {
        yield sleep(0.001);
        
        if ($condition) {
            throw new \Exception("Assertion failed: {$message}. Expected false, got true");
        }
        
        return true;
    }

    /**
     * 异步断言包含
     */
    public function asyncAssertContains(mixed $needle, array $haystack, string $message = ''): \Generator
    {
        yield sleep(0.001);
        
        if (!in_array($needle, $haystack)) {
            throw new \Exception("Assertion failed: {$message}. Needle not found in haystack");
        }
        
        return true;
    }

    /**
     * 异步断言数组键存在
     */
    public function asyncAssertArrayHasKey(string $key, array $array, string $message = ''): \Generator
    {
        yield sleep(0.001);
        
        if (!array_key_exists($key, $array)) {
            throw new \Exception("Assertion failed: {$message}. Key '{$key}' not found in array");
        }
        
        return true;
    }

    /**
     * 异步断言异常
     */
    public function asyncAssertException(callable $function, string $expectedException, string $message = ''): \Generator
    {
        yield sleep(0.001);
        
        try {
            yield $function();
            throw new \Exception("Assertion failed: {$message}. Expected exception '{$expectedException}' was not thrown");
        } catch (\Exception $e) {
            if (get_class($e) !== $expectedException) {
                throw new \Exception("Assertion failed: {$message}. Expected '{$expectedException}', got '" . get_class($e) . "'");
            }
        }
        
        return true;
    }

    /**
     * 异步模拟数据
     */
    public function asyncMockData(string $type, array $options = []): \Generator
    {
        yield sleep(0.001);
        
        return match($type) {
            'request' => [
                'ip' => $options['ip'] ?? '192.168.1.100',
                'uri' => $options['uri'] ?? '/api/test',
                'method' => $options['method'] ?? 'GET',
                'headers' => $options['headers'] ?? ['User-Agent' => 'Test Browser'],
                'query' => $options['query'] ?? [],
                'post' => $options['post'] ?? []
            ],
            'rule' => [
                'id' => $options['id'] ?? 'test_rule_' . time(),
                'name' => $options['name'] ?? 'Test Rule',
                'type' => $options['type'] ?? 'sql_injection',
                'pattern' => $options['pattern'] ?? '/union.*select/i',
                'enabled' => $options['enabled'] ?? true,
                'severity' => $options['severity'] ?? 'high'
            ],
            'config' => [
                'key' => $options['key'] ?? 'test.config',
                'value' => $options['value'] ?? 'test_value',
                'type' => $options['type'] ?? 'string'
            ],
            'plugin' => [
                'id' => $options['id'] ?? 'test_plugin_' . time(),
                'name' => $options['name'] ?? 'Test Plugin',
                'type' => $options['type'] ?? 'detector',
                'version' => $options['version'] ?? '1.0.0',
                'enabled' => $options['enabled'] ?? true
            ],
            default => []
        };
    }

    /**
     * 异步性能测试
     */
    public function asyncPerformanceTest(string $testName, callable $testFunction, int $iterations = 100): \Generator
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        
        $tasks = [];
        for ($i = 0; $i < $iterations; $i++) {
            $tasks[] = create_task($testFunction());
        }
        
        $results = yield gather(...$tasks);
        
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        
        $duration = $endTime - $startTime;
        $memoryUsed = $endMemory - $startMemory;
        
        $this->testMetrics[$testName] = [
            'iterations' => $iterations,
            'total_duration' => $duration,
            'avg_duration' => $duration / $iterations,
            'ops_per_second' => $iterations / $duration,
            'memory_used' => $memoryUsed,
            'avg_memory_per_op' => $memoryUsed / $iterations
        ];
        
        return $results;
    }

    /**
     * 异步压力测试
     */
    public function asyncStressTest(string $testName, callable $testFunction, int $concurrency = 10, int $duration = 10): \Generator
    {
        $startTime = microtime(true);
        $endTime = $startTime + $duration;
        $completed = 0;
        $errors = 0;
        
        while (microtime(true) < $endTime) {
            $tasks = [];
            for ($i = 0; $i < $concurrency; $i++) {
                $tasks[] = create_task($testFunction());
            }
            
            try {
                yield gather(...$tasks);
                $completed += $concurrency;
            } catch (\Exception $e) {
                $errors++;
            }
        }
        
        $actualDuration = microtime(true) - $startTime;
        
        $this->testMetrics[$testName] = [
            'concurrency' => $concurrency,
            'duration' => $actualDuration,
            'completed_operations' => $completed,
            'errors' => $errors,
            'ops_per_second' => $completed / $actualDuration,
            'error_rate' => $errors / ($completed + $errors)
        ];
        
        return [
            'completed' => $completed,
            'errors' => $errors,
            'ops_per_second' => $completed / $actualDuration
        ];
    }

    /**
     * 获取测试结果
     */
    public function getTestResults(): array
    {
        return $this->testResults;
    }

    /**
     * 获取测试指标
     */
    public function getTestMetrics(): array
    {
        return $this->testMetrics;
    }

    /**
     * 生成测试报告
     */
    public function generateTestReport(): array
    {
        $totalTests = count($this->testResults);
        $passedTests = count(array_filter($this->testResults, fn($r) => $r['status'] === 'passed'));
        $failedTests = $totalTests - $passedTests;
        
        $totalDuration = array_sum(array_column($this->testResults, 'duration'));
        $totalMemory = array_sum(array_column($this->testResults, 'memory_used'));
        
        return [
            'summary' => [
                'total_tests' => $totalTests,
                'passed_tests' => $passedTests,
                'failed_tests' => $failedTests,
                'success_rate' => $totalTests > 0 ? round($passedTests / $totalTests * 100, 2) : 0
            ],
            'performance' => [
                'total_duration' => round($totalDuration, 3),
                'avg_duration' => $totalTests > 0 ? round($totalDuration / $totalTests, 3) : 0,
                'total_memory' => $totalMemory,
                'avg_memory' => $totalTests > 0 ? round($totalMemory / $totalTests, 2) : 0
            ],
            'details' => $this->testResults,
            'metrics' => $this->testMetrics,
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * 清理测试数据
     */
    public function asyncCleanup(): \Generator
    {
        yield sleep(0.001);
        
        $this->testResults = [];
        $this->testMetrics = [];
    }
}
