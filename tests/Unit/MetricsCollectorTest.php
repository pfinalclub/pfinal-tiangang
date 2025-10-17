<?php

require_once __DIR__ . '/../Unit/AsyncTestFramework.php';
require_once __DIR__ . '/../../app/monitoring/MetricsCollector.php';

use Tiangang\Waf\Monitoring\MetricsCollector;
use PfinalClub\Asyncio\{create_task, gather, wait_for, sleep, run};

/**
 * 指标收集器单元测试
 */
class MetricsCollectorTest extends AsyncTestFramework
{
    private MetricsCollector $metricsCollector;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->metricsCollector = new MetricsCollector();
    }
    
    /**
     * 测试异步请求记录
     */
    public function testAsyncRecordRequest(): \Generator
    {
        $requestData = [
            'blocked' => false,
            'duration' => 0.05,
            'rule' => null,
            'ip' => '192.168.1.100',
            'uri' => '/api/test'
        ];
        
        $result = yield $this->metricsCollector->asyncRecordRequest($requestData);
        
        $this->assertTrue($result);
    }
    
    /**
     * 测试异步响应时间记录
     */
    public function testAsyncRecordResponseTime(): \Generator
    {
        $responseTime = 0.1;
        $component = 'waf_middleware';
        
        $result = yield $this->metricsCollector->asyncRecordResponseTime($responseTime, $component);
        
        $this->assertTrue($result);
    }
    
    /**
     * 测试安全事件记录
     */
    public function testRecordSecurityEvent(): \Generator
    {
        $eventData = [
            'ip' => '192.168.1.100',
            'rule' => 'sql_injection',
            'severity' => 'high',
            'timestamp' => time()
        ];
        
        $result = yield $this->metricsCollector->asyncRecordSecurityEvent('sql_injection', $eventData);
        
        $this->assertTrue($result);
    }
    
    /**
     * 测试指标聚合
     */
    public function testMetricsAggregation(): \Generator
    {
        // 记录多个请求
        $requests = [
            ['blocked' => false, 'duration' => 0.05, 'rule' => null],
            ['blocked' => true, 'duration' => 0.02, 'rule' => 'sql_injection'],
            ['blocked' => false, 'duration' => 0.08, 'rule' => null],
            ['blocked' => true, 'duration' => 0.01, 'rule' => 'xss']
        ];
        
        $tasks = [];
        foreach ($requests as $request) {
            $tasks[] = create_task($this->metricsCollector->asyncRecordRequest($request));
        }
        
        $results = yield gather(...$tasks);
        
        $this->assertCount(4, $results);
        $this->assertTrue($results[0]);
        $this->assertTrue($results[1]);
        $this->assertTrue($results[2]);
        $this->assertTrue($results[3]);
    }
    
    /**
     * 测试性能指标
     */
    public function testPerformanceMetrics(): \Generator
    {
        $startTime = microtime(true);
        
        // 记录性能指标
        $tasks = [];
        for ($i = 0; $i < 100; $i++) {
            $tasks[] = create_task($this->metricsCollector->asyncRecordResponseTime(0.01 + $i * 0.001, 'test_component'));
        }
        
        $results = yield gather(...$tasks);
        
        $endTime = microtime(true);
        $duration = $endTime - $startTime;
        
        $this->assertCount(100, $results);
        $this->assertLessThan(1.0, $duration, '指标记录应该很快');
    }
    
    /**
     * 测试指标查询
     */
    public function testGetMetrics(): \Generator
    {
        // 记录一些指标
        yield $this->metricsCollector->asyncRecordRequest(['blocked' => false, 'duration' => 0.05, 'rule' => null]);
        yield $this->metricsCollector->asyncRecordResponseTime(0.1, 'waf_middleware');
        
        // 获取指标
        $metrics = yield $this->metricsCollector->asyncGetMetrics('1h');
        
        $this->assertArrayHasKey('requests', $metrics);
        $this->assertArrayHasKey('response_times', $metrics);
        $this->assertArrayHasKey('security_events', $metrics);
    }
    
    /**
     * 测试指标导出
     */
    public function testExportMetrics(): \Generator
    {
        // 记录一些指标
        yield $this->metricsCollector->asyncRecordRequest(['blocked' => false, 'duration' => 0.05, 'rule' => null]);
        
        // 导出指标
        $exportData = yield $this->metricsCollector->asyncExportMetrics('json');
        
        $this->assertArrayHasKey('format', $exportData);
        $this->assertArrayHasKey('data', $exportData);
        $this->assertEquals('json', $exportData['format']);
    }
    
    /**
     * 测试指标清理
     */
    public function testCleanupMetrics(): \Generator
    {
        // 记录一些指标
        yield $this->metricsCollector->asyncRecordRequest(['blocked' => false, 'duration' => 0.05, 'rule' => null]);
        
        // 清理旧指标
        $result = yield $this->metricsCollector->asyncCleanupMetrics(3600); // 清理1小时前的数据
        
        $this->assertTrue($result);
    }
    
    /**
     * 测试指标告警
     */
    public function testMetricsAlerting(): \Generator
    {
        // 记录高响应时间
        yield $this->metricsCollector->asyncRecordResponseTime(5.0, 'waf_middleware');
        
        // 检查告警
        $alerts = yield $this->metricsCollector->asyncCheckAlerts();
        
        $this->assertIsArray($alerts);
    }
    
    /**
     * 测试指标统计
     */
    public function testMetricsStatistics(): \Generator
    {
        // 记录多个指标
        $tasks = [];
        for ($i = 0; $i < 50; $i++) {
            $tasks[] = create_task($this->metricsCollector->asyncRecordRequest([
                'blocked' => $i % 10 === 0,
                'duration' => 0.01 + $i * 0.001,
                'rule' => $i % 10 === 0 ? 'test_rule' : null
            ]));
        }
        
        yield gather(...$tasks);
        
        // 获取统计信息
        $stats = yield $this->metricsCollector->asyncGetStatistics('1h');
        
        $this->assertArrayHasKey('total_requests', $stats);
        $this->assertArrayHasKey('blocked_requests', $stats);
        $this->assertArrayHasKey('avg_response_time', $stats);
        $this->assertArrayHasKey('block_rate', $stats);
    }
    
    /**
     * 测试指标持久化
     */
    public function testMetricsPersistence(): \Generator
    {
        // 记录指标
        yield $this->metricsCollector->asyncRecordRequest(['blocked' => false, 'duration' => 0.05, 'rule' => null]);
        
        // 持久化指标
        $result = yield $this->metricsCollector->asyncPersistMetrics();
        
        $this->assertTrue($result);
    }
}
