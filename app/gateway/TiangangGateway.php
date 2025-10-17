<?php

namespace Tiangang\Waf\Gateway;

use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;
use Tiangang\Waf\Middleware\WafMiddleware;
use Tiangang\Waf\Config\ConfigManager;
use Tiangang\Waf\Logging\LogCollector;
use Tiangang\Waf\Proxy\ProxyHandler;
use Tiangang\Waf\Proxy\BackendManager;
use Tiangang\Waf\Web\Routes\WebRoutes;
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
    private WebRoutes $webRoutes;
    private array $config;
    
    public function __construct()
    {
        $this->configManager = new ConfigManager();
        $this->wafMiddleware = new WafMiddleware();
        $this->logCollector = new LogCollector();
        $this->proxyHandler = new ProxyHandler();
        $this->backendManager = new BackendManager();
        $this->webRoutes = new WebRoutes();
        $this->config = $this->configManager->get('waf');
    }
    
    /**
     * 混合模式处理 HTTP 请求（核心同步 + 后台异步）
     */
    public function handle(Request $request): Response
    {
        $startTime = microtime(true);
        
        try {
            // 检查是否为Web管理界面请求
            if ($this->isWebRequest($request)) {
                return $this->webRoutes->handleRequest($request);
            }
            
            // 检查 WAF 是否启用
            if (!$this->config['enabled']) {
                return $this->createPassThroughResponse($request);
            }
            
            // 同步 WAF 检测（核心功能，必须同步）
            $wafResult = $this->wafMiddleware->processSync($request);
            
            if ($wafResult->isBlocked()) {
                // 异步记录安全日志（后台任务）
                $this->queueAsyncLog($request, $wafResult, microtime(true) - $startTime);
                return $this->createBlockResponse($wafResult);
            }
            
            // 同步代理转发（核心功能，必须同步）
            $response = $this->proxyHandler->forwardSync($request);
            
            // 异步记录成功日志（后台任务）
            $this->queueAsyncLog($request, $wafResult, microtime(true) - $startTime);
            
            return $response;
            
        } catch (\Exception $e) {
            // 异步记录错误日志（后台任务）
            $this->queueAsyncErrorLog($request, $e);
            
            // 返回错误响应
            return $this->createErrorResponse($e);
        }
    }
    
    /**
     * 同步代理请求
     */
    private function proxyRequest(Request $request): Response
    {
        try {
            // 获取后端配置
            $backend = $this->backendManager->getAvailableBackend();
            if (!$backend) {
                return $this->createErrorResponse(new \Exception('No available backend'));
            }
            
            // 构建目标 URL
            $targetUrl = $backend['url'] . $request->path();
            if (!empty($request->get())) {
                $targetUrl .= '?' . http_build_query($request->get());
            }
            
            // 构建请求选项
            $options = [
                'headers' => $request->header(),
                'body' => $request->rawBody(),
                'timeout' => 30,
                'http_errors' => false
            ];
            
            // 发送 HTTP 请求
            $response = $this->httpClient->request($request->method(), $targetUrl, $options);
            
            // 构建响应
            return new Response(
                $response->getStatusCode(),
                $response->getHeaders(),
                $response->getBody()->getContents()
            );
            
        } catch (\Exception $e) {
            return $this->createErrorResponse($e);
        }
    }
    
    /**
     * 异步记录日志
     */
    private function asyncLog(Request $request, $wafResult, float $duration): \Generator
    {
        yield $this->logCollector->log($request, $wafResult, $duration);
    }
    
    /**
     * 异步记录错误日志
     */
    private function asyncErrorLog(Request $request, \Exception $e): \Generator
    {
        yield $this->logCollector->logError($request, $e);
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
    
    /**
     * 异步记录日志（后台任务）
     */
    private function queueAsyncLog(Request $request, \Tiangang\Waf\Core\WafResult $wafResult, float $duration): void
    {
        // 使用 fastcgi_finish_request 在响应发送后执行
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
        
        // 在后台异步记录日志
        \PfinalClub\Asyncio\run($this->asyncLog($request, $wafResult, $duration));
    }
    
    /**
     * 异步记录错误日志（后台任务）
     */
    private function queueAsyncErrorLog(Request $request, \Exception $e): void
    {
        // 使用 fastcgi_finish_request 在响应发送后执行
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
        
        // 在后台异步记录错误日志
        \PfinalClub\Asyncio\run($this->asyncErrorLog($request, $e));
    }
    
    /**
     * 检查是否为Web管理界面请求
     */
    private function isWebRequest(Request $request): bool
    {
        $path = $request->path();
        $webPaths = [
            '/',
            '/dashboard',
            '/api/dashboard',
            '/api/performance',
            '/api/security',
            '/api/export',
            '/health'
        ];
        
        return in_array($path, $webPaths) || str_starts_with($path, '/api/');
    }

    /**
     * 创建透传响应（WAF 未启用时）
     */
    private function createPassThroughResponse(Request $request): Response
    {
        // 当 WAF 未启用时，直接透传请求
        return new Response(200, [
            'Content-Type' => 'application/json',
        ], json_encode([
            'message' => 'WAF is disabled, request passed through',
            'timestamp' => time(),
        ]));
    }
}
