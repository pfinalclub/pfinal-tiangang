<?php

namespace Tiangang\Waf\Gateway;

use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;
use Tiangang\Waf\Middleware\WafMiddleware;
use Tiangang\Waf\Config\ConfigManager;
use Tiangang\Waf\Logging\LogCollector;
use Tiangang\Waf\Proxy\ProxyHandler;
use Tiangang\Waf\Proxy\BackendManager;
use PfinalClub\Asyncio\{create_task, gather, wait_for, sleep};

/**
 * 天罡 WAF 核心网关
 * 
 * 负责接收请求、WAF 检测、代理转发等核心功能
 */
class TiangangGateway
{
    private ConfigManager $configManager;
    private WafMiddleware $wafMiddleware;
    private LogCollector $logCollector;
    private ProxyHandler $proxyHandler;
    private BackendManager $backendManager;
    private array $config;
    
    public function __construct()
    {
        $this->configManager = new ConfigManager();
        $this->wafMiddleware = new WafMiddleware();
        $this->logCollector = new LogCollector();
        $this->proxyHandler = new ProxyHandler();
        $this->backendManager = new BackendManager();
        $this->config = $this->configManager->get('waf');
    }
    
    /**
     * 异步处理 HTTP 请求
     */
    public function handle(Request $request): \Generator
    {
        $startTime = microtime(true);
        
        try {
            // 检查 WAF 是否启用
            if (!$this->config['enabled']) {
                return $this->createPassThroughResponse($request);
            }
            
            // 异步 WAF 检测
            $wafResult = yield create_task($this->wafMiddleware->process($request));
            
            // 异步记录日志
            create_task($this->asyncLogRequest($request, $wafResult, microtime(true) - $startTime));
            
            // 根据检测结果处理
            if ($wafResult->isBlocked()) {
                return $this->createBlockResponse($wafResult);
            }
            
            // 异步代理转发
            $response = yield create_task($this->asyncProxyRequest($request));
            
            return $response;
            
        } catch (\Exception $e) {
            // 异步记录错误日志
            create_task($this->asyncLogError($request, $e));
            
            // 返回错误响应
            return $this->createErrorResponse($e);
        }
    }
    
    /**
     * 异步代理请求到后端
     */
    private function asyncProxyRequest(Request $request): \Generator
    {
        try {
            // 异步获取可用的后端
            $backend = yield create_task($this->asyncGetBackend());
            if (!$backend) {
                return $this->createServiceUnavailableResponse();
            }
            
            // 异步增加连接计数
            create_task($this->asyncIncrementConnections($backend['name']));
            
            try {
                // 异步转发请求
                $response = yield create_task($this->proxyHandler->forward($request));
                return $response;
            } finally {
                // 异步减少连接计数
                create_task($this->asyncDecrementConnections($backend['name']));
            }
            
        } catch (\Exception $e) {
            logger('error', 'Proxy request failed', [
                'url' => $request->path(),
                'method' => $request->method(),
                'error' => $e->getMessage()
            ]);
            
            return $this->createServiceUnavailableResponse();
        }
    }
    
    /**
     * 异步获取后端
     */
    private function asyncGetBackend(): \Generator
    {
        // 模拟异步后端选择
        yield sleep(0.001);
        
        return $this->backendManager->getAvailableBackend();
    }
    
    /**
     * 异步增加连接计数
     */
    private function asyncIncrementConnections(string $backendName): \Generator
    {
        // 模拟异步连接计数
        yield sleep(0.001);
        
        $this->backendManager->incrementConnections($backendName);
    }
    
    /**
     * 异步减少连接计数
     */
    private function asyncDecrementConnections(string $backendName): \Generator
    {
        // 模拟异步连接计数
        yield sleep(0.001);
        
        $this->backendManager->decrementConnections($backendName);
    }
    
    /**
     * 异步记录请求日志
     */
    private function asyncLogRequest(Request $request, $wafResult, float $duration): \Generator
    {
        // 模拟异步日志记录
        yield sleep(0.001);
        
        $this->logCollector->log($request, $wafResult, $duration);
    }
    
    /**
     * 异步记录错误日志
     */
    private function asyncLogError(Request $request, \Exception $e): \Generator
    {
        // 模拟异步错误日志记录
        yield sleep(0.001);
        
        $this->logCollector->logError($request, $e);
    }
    
    /**
     * 代理请求到后端（同步版本，保留兼容性）
     */
    private function proxyRequest(Request $request): Response
    {
        try {
            // 获取可用的后端
            $backend = $this->backendManager->getAvailableBackend();
            if (!$backend) {
                return $this->createServiceUnavailableResponse();
            }
            
            // 增加连接计数
            $this->backendManager->incrementConnections($backend['name']);
            
            try {
                // 转发请求
                $response = $this->proxyHandler->forward($request);
                return $response;
            } finally {
                // 减少连接计数
                $this->backendManager->decrementConnections($backend['name']);
            }
            
        } catch (\Exception $e) {
            logger('error', 'Proxy request failed', [
                'url' => $request->path(),
                'method' => $request->method(),
                'error' => $e->getMessage()
            ]);
            
            return $this->createServiceUnavailableResponse();
        }
    }
    
    /**
     * 创建服务不可用响应
     */
    private function createServiceUnavailableResponse(): Response
    {
        return new Response(503, [
            'Content-Type' => 'application/json',
        ], json_encode([
            'error' => 'Service Unavailable',
            'message' => 'All backend services are currently unavailable',
            'timestamp' => time(),
        ]));
    }
    
    /**
     * 创建拦截响应
     */
    private function createBlockResponse($wafResult): Response
    {
        $statusCode = $wafResult->getStatusCode();
        $message = $wafResult->getMessage();
        
        return new Response($statusCode, [
            'Content-Type' => 'application/json',
        ], json_encode([
            'error' => 'Request Blocked by WAF',
            'message' => $message,
            'rule' => $wafResult->getRule(),
            'timestamp' => time(),
        ]));
    }
    
    /**
     * 创建错误响应
     */
    private function createErrorResponse(\Exception $e): Response
    {
        return new Response(500, [
            'Content-Type' => 'application/json',
        ], json_encode([
            'error' => 'Internal Server Error',
            'message' => $e->getMessage(),
            'timestamp' => time(),
        ]));
    }
}
