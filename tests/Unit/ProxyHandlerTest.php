<?php

require_once __DIR__ . '/../Unit/AsyncTestFramework.php';
require_once __DIR__ . '/../../app/proxy/ProxyHandler.php';

use Tiangang\Waf\Proxy\ProxyHandler;
use PfinalClub\Asyncio\{create_task, gather, wait_for, sleep, run};

/**
 * 代理处理器单元测试
 */
class ProxyHandlerTest extends AsyncTestFramework
{
    private ProxyHandler $proxyHandler;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->proxyHandler = new ProxyHandler();
    }
    
    /**
     * 测试代理转发
     */
    public function testForward(): \Generator
    {
        $request = [
            'ip' => '192.168.1.100',
            'uri' => '/api/test',
            'method' => 'GET',
            'headers' => ['User-Agent' => 'Mozilla/5.0'],
            'query' => ['id' => '123'],
            'post' => []
        ];
        
        $result = yield $this->proxyHandler->forward($request);
        
        $this->assertArrayHasKey('status_code', $result);
        $this->assertArrayHasKey('headers', $result);
        $this->assertArrayHasKey('body', $result);
    }
    
    /**
     * 测试代理超时处理
     */
    public function testForwardTimeout(): \Generator
    {
        $request = [
            'ip' => '192.168.1.100',
            'uri' => '/api/slow',
            'method' => 'GET',
            'headers' => [],
            'query' => [],
            'post' => []
        ];
        
        try {
            $result = yield wait_for($this->proxyHandler->forward($request), 0.1);
            $this->fail('应该抛出超时异常');
        } catch (\PfinalClub\Asyncio\TimeoutException $e) {
            $this->assertStringContains('timeout', $e->getMessage());
        }
    }
    
    /**
     * 测试代理错误处理
     */
    public function testForwardError(): \Generator
    {
        $request = [
            'ip' => '192.168.1.100',
            'uri' => '/api/error',
            'method' => 'GET',
            'headers' => [],
            'query' => [],
            'post' => []
        ];
        
        $result = yield $this->proxyHandler->forward($request);
        
        $this->assertArrayHasKey('status_code', $result);
        $this->assertGreaterThanOrEqual(400, $result['status_code']);
    }
    
    /**
     * 测试代理性能
     */
    public function testForwardPerformance(): \Generator
    {
        $request = [
            'ip' => '192.168.1.100',
            'uri' => '/api/test',
            'method' => 'GET',
            'headers' => [],
            'query' => [],
            'post' => []
        ];
        
        $startTime = microtime(true);
        
        $tasks = [];
        for ($i = 0; $i < 10; $i++) {
            $tasks[] = create_task($this->proxyHandler->forward($request));
        }
        
        $results = yield gather(...$tasks);
        
        $endTime = microtime(true);
        $duration = $endTime - $startTime;
        
        $this->assertLessThan(1.0, $duration, '代理转发应该很快');
        $this->assertCount(10, $results);
    }
    
    /**
     * 测试代理头处理
     */
    public function testHeaderHandling(): \Generator
    {
        $request = [
            'ip' => '192.168.1.100',
            'uri' => '/api/test',
            'method' => 'GET',
            'headers' => [
                'User-Agent' => 'Mozilla/5.0',
                'Accept' => 'application/json',
                'X-Forwarded-For' => '192.168.1.100'
            ],
            'query' => [],
            'post' => []
        ];
        
        $result = yield $this->proxyHandler->forward($request);
        
        $this->assertArrayHasKey('headers', $result);
        $this->assertIsArray($result['headers']);
    }
    
    /**
     * 测试代理方法处理
     */
    public function testMethodHandling(): \Generator
    {
        $methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'];
        
        foreach ($methods as $method) {
            $request = [
                'ip' => '192.168.1.100',
                'uri' => '/api/test',
                'method' => $method,
                'headers' => [],
                'query' => [],
                'post' => []
            ];
            
            $result = yield $this->proxyHandler->forward($request);
            
            $this->assertArrayHasKey('status_code', $result);
        }
    }
    
    /**
     * 测试代理并发处理
     */
    public function testConcurrentForward(): \Generator
    {
        $requests = [];
        for ($i = 0; $i < 5; $i++) {
            $requests[] = [
                'ip' => '192.168.1.' . (100 + $i),
                'uri' => '/api/test' . $i,
                'method' => 'GET',
                'headers' => [],
                'query' => ['id' => $i],
                'post' => []
            ];
        }
        
        $startTime = microtime(true);
        
        $tasks = [];
        foreach ($requests as $request) {
            $tasks[] = create_task($this->proxyHandler->forward($request));
        }
        
        $results = yield gather(...$tasks);
        
        $endTime = microtime(true);
        $duration = $endTime - $startTime;
        
        $this->assertCount(5, $results);
        $this->assertLessThan(2.0, $duration, '并发代理应该很快');
    }
}
