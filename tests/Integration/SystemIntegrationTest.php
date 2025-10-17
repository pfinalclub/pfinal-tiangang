<?php

namespace Tiangang\Waf\Tests\Integration;

use Tiangang\Waf\Tests\Unit\AsyncTestFramework;
use Tiangang\Waf\Gateway\TiangangGateway;
use Tiangang\Waf\Middleware\WafMiddleware;
use Tiangang\Waf\Proxy\ProxyHandler;
use Tiangang\Waf\Logging\AsyncLogger;
use Tiangang\Waf\Monitoring\MetricsCollector;
use PfinalClub\Asyncio\{create_task, gather, wait_for, sleep, run};

/**
 * 系统集成测试
 */
class SystemIntegrationTest extends AsyncTestFramework
{
    private TiangangGateway $gateway;
    private WafMiddleware $wafMiddleware;
    private ProxyHandler $proxyHandler;
    private AsyncLogger $logger;
    private MetricsCollector $metricsCollector;

    public function setUp(): void
    {
        $this->gateway = new TiangangGateway();
        $this->wafMiddleware = new WafMiddleware();
        $this->proxyHandler = new ProxyHandler();
        $this->logger = new AsyncLogger();
        $this->metricsCollector = new MetricsCollector();
    }

    /**
     * 异步测试完整请求流程
     */
    public function asyncTestCompleteRequestFlow(): \Generator
    {
        $requestData = yield $this->asyncMockData('request', [
            'ip' => '192.168.1.100',
            'uri' => '/api/users',
            'method' => 'GET',
            'headers' => ['User-Agent' => 'Test Browser']
        ]);
        
        // 1. WAF 检测
        $wafResult = yield $this->wafMiddleware->process($requestData);
        yield $this->asyncAssertTrue($wafResult->isAllowed() || $wafResult->isBlocked(), 'WAF should return valid result');
        
        if ($wafResult->isBlocked()) {
            return ['status' => 'blocked', 'reason' => $wafResult->getReasons()];
        }
        
        // 2. 代理转发
        $proxyResponse = yield $this->proxyHandler->forward($requestData);
        yield $this->asyncAssertArrayHasKey('status_code', $proxyResponse, 'Proxy should return status code');
        yield $this->asyncAssertArrayHasKey('headers', $proxyResponse, 'Proxy should return headers');
        
        // 3. 日志记录
        yield $this->logger->log('info', 'Request processed', [
            'ip' => $requestData['ip'],
            'uri' => $requestData['uri'],
            'status' => $proxyResponse['status_code']
        ]);
        
        // 4. 指标收集
        yield $this->metricsCollector->asyncRecordRequest([
            'blocked' => false,
            'duration' => 0.05,
            'rule' => null
        ]);
        
        return [
            'status' => 'completed',
            'waf_result' => $wafResult,
            'proxy_response' => $proxyResponse
        ];
    }

    /**
     * 异步测试安全威胁处理
     */
    public function asyncTestSecurityThreatHandling(): \Generator
    {
        $maliciousRequest = yield $this->asyncMockData('request', [
            'ip' => '192.168.1.100',
            'uri' => '/api/users',
            'method' => 'GET',
            'query' => ['id' => "1' UNION SELECT * FROM users--"]
        ]);
        
        // 1. WAF 检测恶意请求
        $wafResult = yield $this->wafMiddleware->process($maliciousRequest);
        yield $this->asyncAssertTrue($wafResult->isBlocked(), 'Malicious request should be blocked');
        
        // 2. 记录安全事件
        yield $this->logger->log('warning', 'Security threat detected', [
            'ip' => $maliciousRequest['ip'],
            'threat_type' => 'sql_injection',
            'payload' => $maliciousRequest['query']['id']
        ]);
        
        // 3. 收集安全指标
        yield $this->metricsCollector->asyncRecordSecurityEvent('sql_injection', [
            'ip' => $maliciousRequest['ip'],
            'rule' => 'sql_injection',
            'severity' => 'high'
        ]);
        
        return [
            'status' => 'blocked',
            'threat_type' => 'sql_injection',
            'waf_result' => $wafResult
        ];
    }

    /**
     * 异步测试高并发处理
     */
    public function asyncTestHighConcurrency(): \Generator
    {
        $requestData = yield $this->asyncMockData('request');
        
        $results = yield $this->asyncStressTest('system_concurrency', function() use ($requestData) {
            return $this->gateway->handle($requestData);
        }, 100, 10);
        
        yield $this->asyncAssertTrue($results['completed'] > 0, 'Should complete some requests');
        yield $this->asyncAssertTrue($results['ops_per_second'] > 0, 'Should have positive ops per second');
        yield $this->asyncAssertTrue($results['error_rate'] < 0.1, 'Error rate should be less than 10%');
        
        return $results;
    }

    /**
     * 异步测试性能基准
     */
    public function asyncTestPerformanceBenchmark(): \Generator
    {
        $requestData = yield $this->asyncMockData('request');
        
        $results = yield $this->asyncPerformanceTest('system_performance', function() use ($requestData) {
            return $this->gateway->handle($requestData);
        }, 1000);
        
        yield $this->asyncAssertTrue(count($results) === 1000, 'Should process 1000 requests');
        
        $avgDuration = $this->testMetrics['system_performance']['avg_duration'];
        yield $this->asyncAssertTrue($avgDuration < 0.1, 'Average duration should be less than 100ms');
        
        return $results;
    }

    /**
     * 异步测试错误恢复
     */
    public function asyncTestErrorRecovery(): \Generator
    {
        // 测试无效请求处理
        $invalidRequest = null;
        
        try {
            $result = yield $this->gateway->handle($invalidRequest);
            yield $this->asyncAssertTrue(isset($result['error']), 'Should handle invalid request gracefully');
        } catch (\Exception $e) {
            yield $this->asyncAssertTrue(true, 'Should catch and handle exceptions');
        }
        
        // 测试网络错误恢复
        $networkErrorRequest = yield $this->asyncMockData('request', [
            'uri' => '/api/nonexistent'
        ]);
        
        $result = yield $this->gateway->handle($networkErrorRequest);
        yield $this->asyncAssertTrue(isset($result['status_code']), 'Should handle network errors gracefully');
        
        return true;
    }

    /**
     * 异步测试配置热更新
     */
    public function asyncTestConfigHotReload(): \Generator
    {
        $requestData = yield $this->asyncMockData('request');
        
        // 测试配置更新前的行为
        $result1 = yield $this->gateway->handle($requestData);
        
        // 模拟配置更新
        $this->gateway->updateConfig(['waf' => ['enabled' => false]]);
        
        // 测试配置更新后的行为
        $result2 = yield $this->gateway->handle($requestData);
        
        yield $this->asyncAssertTrue(isset($result1['status_code']), 'First result should be valid');
        yield $this->asyncAssertTrue(isset($result2['status_code']), 'Second result should be valid');
        
        return [$result1, $result2];
    }

    /**
     * 异步测试监控和日志
     */
    public function asyncTestMonitoringAndLogging(): \Generator
    {
        $requestData = yield $this->asyncMockData('request');
        
        // 处理请求
        $result = yield $this->gateway->handle($requestData);
        
        // 验证日志记录
        $logEntries = yield $this->logger->getLogEntries();
        yield $this->asyncAssertTrue(count($logEntries) > 0, 'Should have log entries');
        
        // 验证指标收集
        $metrics = yield $this->metricsCollector->getMetrics();
        yield $this->asyncAssertTrue(isset($metrics['requests_total']), 'Should have request metrics');
        
        return [
            'result' => $result,
            'log_entries' => count($logEntries),
            'metrics' => $metrics
        ];
    }

    /**
     * 异步测试数据一致性
     */
    public function asyncTestDataConsistency(): \Generator
    {
        $requestData = yield $this->asyncMockData('request', [
            'ip' => '192.168.1.100',
            'uri' => '/api/test',
            'method' => 'GET'
        ]);
        
        // 处理请求
        $result = yield $this->gateway->handle($requestData);
        
        // 验证数据一致性
        yield $this->asyncAssertTrue(isset($result['status_code']), 'Result should have status code');
        yield $this->asyncAssertTrue(isset($result['headers']), 'Result should have headers');
        yield $this->asyncAssertTrue(isset($result['body']), 'Result should have body');
        
        // 验证日志数据一致性
        $logEntries = yield $this->logger->getLogEntries();
        $lastLog = end($logEntries);
        yield $this->asyncAssertEquals($requestData['ip'], $lastLog['context']['ip'], 'Log IP should match request IP');
        yield $this->asyncAssertEquals($requestData['uri'], $lastLog['context']['uri'], 'Log URI should match request URI');
        
        return $result;
    }

    /**
     * 异步运行所有集成测试
     */
    public function asyncRunAllIntegrationTests(): \Generator
    {
        $tests = [
            'complete_request_flow' => [$this, 'asyncTestCompleteRequestFlow'],
            'security_threat_handling' => [$this, 'asyncTestSecurityThreatHandling'],
            'high_concurrency' => [$this, 'asyncTestHighConcurrency'],
            'performance_benchmark' => [$this, 'asyncTestPerformanceBenchmark'],
            'error_recovery' => [$this, 'asyncTestErrorRecovery'],
            'config_hot_reload' => [$this, 'asyncTestConfigHotReload'],
            'monitoring_and_logging' => [$this, 'asyncTestMonitoringAndLogging'],
            'data_consistency' => [$this, 'asyncTestDataConsistency']
        ];
        
        $results = yield $this->asyncRunTests($tests);
        
        $report = $this->generateTestReport();
        
        return [
            'results' => $results,
            'report' => $report
        ];
    }
}
