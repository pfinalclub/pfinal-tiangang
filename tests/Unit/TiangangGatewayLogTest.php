<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/SimpleTestFramework.php';

use app\waf\TiangangGateway;
use app\waf\core\WafResult;
use Workerman\Protocols\Http\Request;

/**
 * TiangangGateway 日志测试
 * 
 * 重点测试 P0 修复：异步日志在 Workerman 中正常工作
 */
class TiangangGatewayLogTest extends SimpleTestFramework
{
    private TiangangGateway $gateway;
    
    protected function setUp(): void
    {
        $this->gateway = new TiangangGateway();
    }
    
    /**
     * 测试 P0 修复：异步日志方法不包含 fastcgi_finish_request 调用
     */
    public function testAsyncLogDoesNotUseFastCgi(): void
    {
        // 使用反射检查方法实现
        $reflection = new \ReflectionClass($this->gateway);
        $method = $reflection->getMethod('queueAsyncLog');
        $method->setAccessible(true);
        
        // 读取方法源代码
        $filename = $method->getFileName();
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        
        $source = file_get_contents($filename);
        $lines = explode("\n", $source);
        $methodSource = implode("\n", array_slice($lines, $startLine - 1, $endLine - $startLine + 1));
        
        // 移除注释行，只检查实际代码
        $codeLines = array_filter(explode("\n", $methodSource), function($line) {
            $trimmed = trim($line);
            // 过滤掉注释和空行
            return !empty($trimmed) && 
                   strpos($trimmed, '//') !== 0 && 
                   strpos($trimmed, '*') !== 0 &&
                   strpos($trimmed, '/**') !== 0 &&
                   strpos($trimmed, '*/') !== 0;
        });
        $codeSource = implode("\n", $codeLines);
        
        // 验证不包含 fastcgi_finish_request 调用（不是注释）
        $this->assertStringNotContainsString(
            'fastcgi_finish_request()',
            $codeSource,
            'queueAsyncLog 方法不应包含 fastcgi_finish_request() 调用（Workerman 不是 FastCGI）'
        );
    }
    
    /**
     * 测试 P0 修复：异步错误日志方法不包含 fastcgi_finish_request 调用
     */
    public function testAsyncErrorLogDoesNotUseFastCgi(): void
    {
        // 使用反射检查方法实现
        $reflection = new \ReflectionClass($this->gateway);
        $method = $reflection->getMethod('queueAsyncErrorLog');
        $method->setAccessible(true);
        
        // 读取方法源代码
        $filename = $method->getFileName();
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        
        $source = file_get_contents($filename);
        $lines = explode("\n", $source);
        $methodSource = implode("\n", array_slice($lines, $startLine - 1, $endLine - $startLine + 1));
        
        // 移除注释行，只检查实际代码
        $codeLines = array_filter(explode("\n", $methodSource), function($line) {
            $trimmed = trim($line);
            // 过滤掉注释和空行
            return !empty($trimmed) && 
                   strpos($trimmed, '//') !== 0 && 
                   strpos($trimmed, '*') !== 0 &&
                   strpos($trimmed, '/**') !== 0 &&
                   strpos($trimmed, '*/') !== 0;
        });
        $codeSource = implode("\n", $codeLines);
        
        // 验证不包含 fastcgi_finish_request 调用（不是注释）
        $this->assertStringNotContainsString(
            'fastcgi_finish_request()',
            $codeSource,
            'queueAsyncErrorLog 方法不应包含 fastcgi_finish_request() 调用（Workerman 不是 FastCGI）'
        );
    }
    
    /**
     * 测试异步日志方法签名正确
     */
    public function testAsyncLogMethodSignature(): void
    {
        $reflection = new \ReflectionClass($this->gateway);
        $method = $reflection->getMethod('queueAsyncLog');
        
        // 验证方法参数类型
        $parameters = $method->getParameters();
        $this->assertEquals(3, count($parameters), 'queueAsyncLog 应该有 3 个参数');
        
        // 验证第一个参数是 Request 类型（完整类名）
        $firstParamType = $parameters[0]->getType();
        $this->assertNotNull($firstParamType, '第一个参数应该有类型声明');
        $this->assertStringContainsString('Request', $firstParamType->getName(), '第一个参数应该是 Request 类型');
    }
    
    /**
     * 测试异步错误日志方法签名正确
     */
    public function testAsyncErrorLogMethodSignature(): void
    {
        $reflection = new \ReflectionClass($this->gateway);
        $method = $reflection->getMethod('queueAsyncErrorLog');
        
        // 验证方法参数类型
        $parameters = $method->getParameters();
        $this->assertEquals(2, count($parameters), 'queueAsyncErrorLog 应该有 2 个参数');
        
        // 验证第一个参数是 Request 类型（完整类名）
        $firstParamType = $parameters[0]->getType();
        $this->assertNotNull($firstParamType, '第一个参数应该有类型声明');
        $this->assertStringContainsString('Request', $firstParamType->getName(), '第一个参数应该是 Request 类型');
    }
    
    /**
     * 创建模拟请求对象
     */
    private function createMockRequest()
    {
        // 由于 Request 是 Workerman 的类，我们创建一个简单的模拟对象
        // 使用 stdClass 来模拟 Request 对象
        $request = new \stdClass();
        $request->path = function() { return '/api/test'; };
        $request->method = function() { return 'GET'; };
        $request->header = function() { return []; };
        $request->get = function() { return []; };
        $request->post = function() { return []; };
        
        return $request;
    }
}

