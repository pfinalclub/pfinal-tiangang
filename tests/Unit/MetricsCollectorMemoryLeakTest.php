<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/SimpleTestFramework.php';

use app\waf\monitoring\MetricsCollector;

/**
 * 指标收集器内存泄漏测试
 * 
 * 重点测试 P0 修复：内存泄漏防护
 */
class MetricsCollectorMemoryLeakTest extends SimpleTestFramework
{
    private MetricsCollector $collector;
    
    protected function setUp(): void
    {
        $this->collector = new MetricsCollector();
    }
    
    /**
     * 测试 P0 修复：指标数量限制
     */
    public function testMetricsSizeLimit(): void
    {
        // 使用反射访问私有属性
        $reflection = new \ReflectionClass($this->collector);
        $metricsProperty = $reflection->getProperty('metrics');
        $metricsProperty->setAccessible(true);
        $maxSizeProperty = $reflection->getProperty('maxMetricsSize');
        $maxSizeProperty->setAccessible(true);
        
        // 获取最大指标数量
        $maxSize = $maxSizeProperty->getValue($this->collector);
        $this->assertGreaterThan(0, $maxSize, 'maxMetricsSize 应该大于 0');
        $this->assertLessThanOrEqual(10000, $maxSize, 'maxMetricsSize 应该合理（不超过 10000）');
        
        // 模拟添加大量指标
        $metrics = [];
        for ($i = 0; $i < $maxSize + 100; $i++) {
            $metrics["metric_$i"] = [
                'value' => $i,
                'timestamp' => time(),
            ];
        }
        $metricsProperty->setValue($this->collector, $metrics);
        
        // 调用清理方法
        $cleanupMethod = $reflection->getMethod('cleanupMetrics');
        $cleanupMethod->setAccessible(true);
        $cleanupMethod->invoke($this->collector);
        
        // 验证指标数量不超过限制
        $metricsAfter = $metricsProperty->getValue($this->collector);
        $this->assertLessThanOrEqual($maxSize, count($metricsAfter), '清理后指标数量应该不超过限制');
    }
    
    /**
     * 测试 P0 修复：过期指标清理
     */
    public function testExpiredMetricsCleanup(): void
    {
        $reflection = new \ReflectionClass($this->collector);
        $metricsProperty = $reflection->getProperty('metrics');
        $metricsProperty->setAccessible(true);
        
        // 创建包含过期指标的数组
        $currentTime = time();
        $metrics = [
            'recent_metric' => [
                'value' => 100,
                'timestamp' => $currentTime - 100, // 100 秒前
            ],
            'expired_metric_1' => [
                'value' => 200,
                'timestamp' => $currentTime - 86400 * 8, // 8 天前（超过 7 天保留期）
            ],
            'expired_metric_2' => [
                'value' => 300,
                'timestamp' => $currentTime - 86400 * 10, // 10 天前
            ],
        ];
        
        $metricsProperty->setValue($this->collector, $metrics);
        
        // 调用清理方法
        $cleanupMethod = $reflection->getMethod('cleanupMetrics');
        $cleanupMethod->setAccessible(true);
        $cleanupMethod->invoke($this->collector);
        
        // 验证过期指标被清理
        $metricsAfter = $metricsProperty->getValue($this->collector);
        $this->assertArrayHasKey('recent_metric', $metricsAfter, '最近的指标应该保留');
        $this->assertArrayNotHasKey('expired_metric_1', $metricsAfter, '过期指标应该被清理');
        $this->assertArrayNotHasKey('expired_metric_2', $metricsAfter, '过期指标应该被清理');
    }
    
    /**
     * 测试 P0 修复：定期清理机制
     */
    public function testPeriodicCleanup(): void
    {
        $reflection = new \ReflectionClass($this->collector);
        $metricsProperty = $reflection->getProperty('metrics');
        $metricsProperty->setAccessible(true);
        
        // 添加大量指标
        $metrics = [];
        for ($i = 0; $i < 2000; $i++) {
            $metrics["metric_$i"] = [
                'value' => $i,
                'timestamp' => time() - ($i % 10) * 86400, // 混合新旧指标
            ];
        }
        $metricsProperty->setValue($this->collector, $metrics);
        
        // 模拟 collectMetrics 中的清理调用
        $cleanupMethod = $reflection->getMethod('cleanupMetrics');
        $cleanupMethod->setAccessible(true);
        $cleanupMethod->invoke($this->collector);
        
        // 验证清理后指标数量合理
        $metricsAfter = $metricsProperty->getValue($this->collector);
        $maxSizeProperty = $reflection->getProperty('maxMetricsSize');
        $maxSizeProperty->setAccessible(true);
        $maxSize = $maxSizeProperty->getValue($this->collector);
        
        $this->assertLessThanOrEqual($maxSize, count($metricsAfter), '清理后指标数量应该不超过限制');
    }
    
    /**
     * 测试内存使用稳定性
     */
    public function testMemoryStability(): void
    {
        $initialMemory = memory_get_usage(true);
        
        // 记录大量请求
        for ($i = 0; $i < 5000; $i++) {
            $this->collector->recordRequest([
                'blocked' => $i % 10 === 0,
                'duration' => 0.01 + ($i % 100) * 0.001,
                'rule' => $i % 10 === 0 ? 'test_rule' : null,
            ]);
        }
        
        // 触发清理
        $reflection = new \ReflectionClass($this->collector);
        $cleanupMethod = $reflection->getMethod('cleanupMetrics');
        $cleanupMethod->setAccessible(true);
        $cleanupMethod->invoke($this->collector);
        
        $finalMemory = memory_get_usage(true);
        $memoryIncrease = $finalMemory - $initialMemory;
        
        // 内存增长应该合理（不超过 10MB）
        $this->assertLessThan(10 * 1024 * 1024, $memoryIncrease, '内存增长应该在合理范围内');
    }
}

