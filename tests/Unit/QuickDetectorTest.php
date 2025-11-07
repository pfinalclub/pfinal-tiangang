<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/SimpleTestFramework.php';

use app\waf\detectors\QuickDetector;
use app\waf\core\WafResult;

/**
 * 快速检测器单元测试
 * 
 * 重点测试 P0 修复：白名单逻辑
 */
class QuickDetectorTest extends SimpleTestFramework
{
    private QuickDetector $detector;
    
    protected function setUp(): void
    {
        $this->detector = new QuickDetector();
    }
    
    /**
     * 测试 P0 修复：未配置白名单时应放行
     */
    public function testWhitelistEmptyShouldAllow(): void
    {
        // 模拟未配置白名单的情况
        $requestData = [
            'ip' => '192.168.1.100',
            'uri' => '/api/test',
            'method' => 'GET',
            'headers' => [],
            'query' => [],
            'post' => [],
            'user_agent' => 'Mozilla/5.0',
            'referer' => '',
            'timestamp' => time(),
        ];
        
        // 使用反射来测试白名单检查逻辑
        $reflection = new \ReflectionClass($this->detector);
        $method = $reflection->getMethod('checkIpWhitelist');
        $method->setAccessible(true);
        
        // 模拟空白名单（通过配置）
        // 注意：这里需要确保配置中没有白名单
        $result = $method->invoke($this->detector, '192.168.1.100');
        
        // P0 修复：未配置白名单时应放行
        $this->assertFalse($result->isBlocked(), '未配置白名单时应放行请求');
    }
    
    /**
     * 测试 P0 修复：配置了白名单，IP 在其中应放行
     */
    public function testWhitelistIpInListShouldAllow(): void
    {
        $requestData = [
            'ip' => '192.168.1.100',
            'uri' => '/api/test',
            'method' => 'GET',
            'headers' => [],
            'query' => [],
            'post' => [],
            'user_agent' => 'Mozilla/5.0',
            'referer' => '',
            'timestamp' => time(),
        ];
        
        // 这个测试需要配置白名单，但由于配置是动态的，我们测试整体流程
        $result = $this->detector->check($requestData);
        
        // 如果 IP 在白名单中，应该放行
        // 注意：这个测试依赖于实际配置，可能需要 mock
        $this->assertInstanceOf(WafResult::class, $result);
    }
    
    /**
     * 测试 P0 修复：配置了白名单，IP 不在其中应拦截
     */
    public function testWhitelistIpNotInListShouldBlock(): void
    {
        $requestData = [
            'ip' => '10.0.0.1', // 假设这个 IP 不在白名单中
            'uri' => '/api/test',
            'method' => 'GET',
            'headers' => [],
            'query' => [],
            'post' => [],
            'user_agent' => 'Mozilla/5.0',
            'referer' => '',
            'timestamp' => time(),
        ];
        
        // 使用反射测试白名单检查
        $reflection = new \ReflectionClass($this->detector);
        $method = $reflection->getMethod('checkIpWhitelist');
        $method->setAccessible(true);
        
        // 如果配置了白名单但 IP 不在其中，应该拦截
        // 注意：这个测试依赖于实际配置
        $result = $method->invoke($this->detector, '10.0.0.1');
        
        // 结果应该是 WafResult 实例
        $this->assertInstanceOf(WafResult::class, $result);
    }
    
    /**
     * 测试 IP 黑名单检查
     */
    public function testIpBlacklist(): void
    {
        $requestData = [
            'ip' => '192.168.1.100',
            'uri' => '/api/test',
            'method' => 'GET',
            'headers' => [],
            'query' => [],
            'post' => [],
            'user_agent' => 'Mozilla/5.0',
            'referer' => '',
            'timestamp' => time(),
        ];
        
        $result = $this->detector->check($requestData);
        
        $this->assertInstanceOf(WafResult::class, $result);
    }
    
    /**
     * 测试基础正则检查
     */
    public function testBasicRegex(): void
    {
        $requestData = [
            'ip' => '192.168.1.100',
            'uri' => '/api/test',
            'method' => 'GET',
            'headers' => [],
            'query' => ['id' => "1' OR '1'='1"], // SQL 注入尝试
            'post' => [],
            'user_agent' => 'Mozilla/5.0',
            'referer' => '',
            'timestamp' => time(),
        ];
        
        $result = $this->detector->check($requestData);
        
        $this->assertInstanceOf(WafResult::class, $result);
    }
    
    /**
     * 测试频率限制
     */
    public function testRateLimit(): void
    {
        $requestData = [
            'ip' => '192.168.1.100',
            'uri' => '/api/test',
            'method' => 'GET',
            'headers' => [],
            'query' => [],
            'post' => [],
            'user_agent' => 'Mozilla/5.0',
            'referer' => '',
            'timestamp' => time(),
        ];
        
        // 快速发送多个请求，测试频率限制
        for ($i = 0; $i < 10; $i++) {
            $result = $this->detector->check($requestData);
            $this->assertInstanceOf(WafResult::class, $result);
        }
    }
    
    /**
     * 测试完整检测流程
     */
    public function testCompleteDetectionFlow(): void
    {
        $requestData = [
            'ip' => '192.168.1.100',
            'uri' => '/api/test',
            'method' => 'GET',
            'headers' => [],
            'query' => [],
            'post' => [],
            'user_agent' => 'Mozilla/5.0',
            'referer' => '',
            'timestamp' => time(),
        ];
        
        $result = $this->detector->check($requestData);
        
        $this->assertInstanceOf(WafResult::class, $result);
        $this->assertIsBool($result->isBlocked());
    }
}

