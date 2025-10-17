<?php

namespace Tiangang\Waf\Tests\Unit;

use Tiangang\Waf\Middleware\WafMiddleware;
use Tiangang\Waf\Tests\Unit\AsyncTestFramework;
use PfinalClub\Asyncio\{create_task, gather, wait_for, sleep, run};

/**
 * WAF 中间件单元测试
 */
class WafMiddlewareTest extends AsyncTestFramework
{
    private WafMiddleware $wafMiddleware;

    public function setUp(): void
    {
        $this->wafMiddleware = new WafMiddleware();
    }

    /**
     * 异步测试正常请求处理
     */
    public function asyncTestNormalRequest(): \Generator
    {
        $requestData = yield $this->asyncMockData('request', [
            'ip' => '192.168.1.100',
            'uri' => '/api/test',
            'method' => 'GET'
        ]);
        
        $result = yield $this->wafMiddleware->process($requestData);
        
        yield $this->asyncAssertTrue($result->isAllowed(), 'Normal request should be allowed');
        yield $this->asyncAssertFalse($result->isBlocked(), 'Normal request should not be blocked');
        
        return $result;
    }

    /**
     * 异步测试 SQL 注入检测
     */
    public function asyncTestSqlInjectionDetection(): \Generator
    {
        $requestData = yield $this->asyncMockData('request', [
            'ip' => '192.168.1.100',
            'uri' => '/api/users',
            'method' => 'GET',
            'query' => ['id' => "1' UNION SELECT * FROM users--"]
        ]);
        
        $result = yield $this->wafMiddleware->process($requestData);
        
        yield $this->asyncAssertTrue($result->isBlocked(), 'SQL injection should be blocked');
        yield $this->asyncAssertFalse($result->isAllowed(), 'SQL injection should not be allowed');
        yield $this->asyncAssertContains('sql_injection', $result->getReasons(), 'Should detect SQL injection');
        
        return $result;
    }

    /**
     * 异步测试 XSS 检测
     */
    public function asyncTestXssDetection(): \Generator
    {
        $requestData = yield $this->asyncMockData('request', [
            'ip' => '192.168.1.100',
            'uri' => '/api/comment',
            'method' => 'POST',
            'post' => ['content' => '<script>alert("xss")</script>']
        ]);
        
        $result = yield $this->wafMiddleware->process($requestData);
        
        yield $this->asyncAssertTrue($result->isBlocked(), 'XSS should be blocked');
        yield $this->asyncAssertFalse($result->isAllowed(), 'XSS should not be allowed');
        yield $this->asyncAssertContains('xss', $result->getReasons(), 'Should detect XSS');
        
        return $result;
    }

    /**
     * 异步测试 IP 黑名单
     */
    public function asyncTestIpBlacklist(): \Generator
    {
        $requestData = yield $this->asyncMockData('request', [
            'ip' => '10.0.0.1', // 假设这是黑名单 IP
            'uri' => '/api/test',
            'method' => 'GET'
        ]);
        
        $result = yield $this->wafMiddleware->process($requestData);
        
        yield $this->asyncAssertTrue($result->isBlocked(), 'Blacklisted IP should be blocked');
        yield $this->asyncAssertFalse($result->isAllowed(), 'Blacklisted IP should not be allowed');
        yield $this->asyncAssertContains('ip_blacklist', $result->getReasons(), 'Should detect blacklisted IP');
        
        return $result;
    }

    /**
     * 异步测试速率限制
     */
    public function asyncTestRateLimit(): \Generator
    {
        $requestData = yield $this->asyncMockData('request', [
            'ip' => '192.168.1.100',
            'uri' => '/api/test',
            'method' => 'GET'
        ]);
        
        // 模拟多次请求
        $results = [];
        for ($i = 0; $i < 100; $i++) {
            $results[] = yield $this->wafMiddleware->process($requestData);
        }
        
        $blockedCount = count(array_filter($results, fn($r) => $r->isBlocked()));
        yield $this->asyncAssertTrue($blockedCount > 0, 'Rate limit should block some requests');
        
        return $results;
    }

    /**
     * 异步测试性能
     */
    public function asyncTestPerformance(): \Generator
    {
        $requestData = yield $this->asyncMockData('request');
        
        $results = yield $this->asyncPerformanceTest('waf_middleware', function() use ($requestData) {
            return $this->wafMiddleware->process($requestData);
        }, 1000);
        
        yield $this->asyncAssertTrue(count($results) === 1000, 'Should process 1000 requests');
        
        return $results;
    }

    /**
     * 异步测试并发处理
     */
    public function asyncTestConcurrency(): \Generator
    {
        $requestData = yield $this->asyncMockData('request');
        
        $results = yield $this->asyncStressTest('waf_middleware_concurrency', function() use ($requestData) {
            return $this->wafMiddleware->process($requestData);
        }, 50, 5);
        
        yield $this->asyncAssertTrue($results['completed'] > 0, 'Should complete some requests');
        yield $this->asyncAssertTrue($results['ops_per_second'] > 0, 'Should have positive ops per second');
        
        return $results;
    }

    /**
     * 异步测试错误处理
     */
    public function asyncTestErrorHandling(): \Generator
    {
        // 测试无效请求数据
        $invalidRequest = null;
        
        yield $this->asyncAssertException(
            function() use ($invalidRequest) {
                return $this->wafMiddleware->process($invalidRequest);
            },
            \Exception::class,
            'Should throw exception for invalid request'
        );
        
        return true;
    }

    /**
     * 异步测试配置更新
     */
    public function asyncTestConfigUpdate(): \Generator
    {
        $requestData = yield $this->asyncMockData('request');
        
        // 测试配置更新后的行为
        $result1 = yield $this->wafMiddleware->process($requestData);
        
        // 模拟配置更新
        $this->wafMiddleware->updateConfig(['detection' => ['enabled' => false]]);
        
        $result2 = yield $this->wafMiddleware->process($requestData);
        
        yield $this->asyncAssertTrue($result1->isAllowed() || $result1->isBlocked(), 'First result should be valid');
        yield $this->asyncAssertTrue($result2->isAllowed() || $result2->isBlocked(), 'Second result should be valid');
        
        return [$result1, $result2];
    }

    /**
     * 异步运行所有测试
     */
    public function asyncRunAllTests(): \Generator
    {
        $tests = [
            'normal_request' => [$this, 'asyncTestNormalRequest'],
            'sql_injection_detection' => [$this, 'asyncTestSqlInjectionDetection'],
            'xss_detection' => [$this, 'asyncTestXssDetection'],
            'ip_blacklist' => [$this, 'asyncTestIpBlacklist'],
            'rate_limit' => [$this, 'asyncTestRateLimit'],
            'performance' => [$this, 'asyncTestPerformance'],
            'concurrency' => [$this, 'asyncTestConcurrency'],
            'error_handling' => [$this, 'asyncTestErrorHandling'],
            'config_update' => [$this, 'asyncTestConfigUpdate']
        ];
        
        $results = yield $this->asyncRunTests($tests);
        
        $report = $this->generateTestReport();
        
        return [
            'results' => $results,
            'report' => $report
        ];
    }
}
