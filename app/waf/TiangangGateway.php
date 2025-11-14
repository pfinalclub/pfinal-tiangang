<?php

namespace app\waf;

use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;
use app\waf\config\ConfigManager;
use app\waf\plugins\PluginManager;
use app\waf\middleware\WafMiddleware;
use app\waf\logging\LogCollector;
use app\waf\proxy\ProxyHandler;
use app\waf\proxy\BackendManager;
use app\admin\routes\AdminRoutes;
use app\admin\middleware\AuthMiddleware;
use app\admin\middleware\CsrfMiddleware;
use PfinalClub\Asyncio\{create_task, gather, wait_for, sleep};

/**
 * 天罡 WAF 核心网关
 * 
 * 负责接收请求、WAF 检测、代理转发等核心功能
 */
class TiangangGateway
{
    private ConfigManager $configManager;
    private PluginManager $pluginManager;
    private WafMiddleware $wafMiddleware;
    private LogCollector $logCollector;
    private ProxyHandler $proxyHandler;
    private BackendManager $backendManager;
    private AdminRoutes $adminRoutes;
    private AuthMiddleware $authMiddleware;
    private CsrfMiddleware $csrfMiddleware;
    private ?array $config;
    
    public function __construct()
    {
        $this->configManager = new ConfigManager();
        $this->pluginManager = new PluginManager();
        $this->wafMiddleware = new WafMiddleware($this->configManager, $this->pluginManager);
        $this->logCollector = new LogCollector();
        $this->proxyHandler = new ProxyHandler();
        $this->backendManager = new BackendManager();
        $this->adminRoutes = new AdminRoutes();
        $this->authMiddleware = new AuthMiddleware();
        $this->csrfMiddleware = new CsrfMiddleware();
        $this->config = $this->configManager->get('waf') ?? [];
    }
    
    /**
     * 混合模式处理 HTTP 请求（核心同步 + 后台异步）
     * 
     * @deprecated 使用 handleAdminRequest 或 handleProxyRequest 替代
     */
    public function handle(Request $request): Response
    {
        $startTime = microtime(true);
        
        try {
            // 检查是否为管理界面请求
            if ($this->isWebRequest($request)) {
                // 对管理界面请求应用认证中间件和 CSRF 保护
                return $this->authMiddleware->process($request, function($request) {
                    return $this->csrfMiddleware->process($request, function($request) {
                        return $this->adminRoutes->handleRequest($request);
                    });
                });
            }
            
            // 检查 WAF 是否启用
            if (!($this->config['enabled'] ?? true)) {
                return $this->createPassThroughResponse($request);
            }
            
            // 同步 WAF 检测（核心功能，必须同步）
            $wafResult = $this->wafMiddleware->processSync($request);
            
            if ($wafResult->isBlocked()) {
                // 异步记录安全日志（后台任务）
                $this->queueAsyncLog($request, $wafResult, microtime(true) - $startTime);
                return $this->createBlockResponse($wafResult, $request);
            }
            
            // 同步代理转发（核心功能，必须同步）
            $response = $this->proxyHandler->forwardSync($request);
            
            // 确保响应是有效的 Response 对象
            if (!($response instanceof Response)) {
                throw new \RuntimeException('Invalid response from proxy handler');
            }
            
            // 异步记录成功日志（后台任务）
            $this->queueAsyncLog($request, $wafResult, microtime(true) - $startTime);
            
            return $response;
            
        } catch (\Throwable $e) {
            // 捕获所有类型的错误（包括 Error 和 Exception）
            // 异步记录错误日志（后台任务）
            $this->queueAsyncErrorLog($request, $e);
            
            // 返回错误响应
            return $this->createErrorResponse($e);
        }
    }
    
    /**
     * 处理管理界面请求（8989 端口）
     */
    public function handleAdminRequest(Request $request): Response
    {
        try {
            // 调试日志：确认这是管理界面请求
            if (function_exists('logger')) {
                logger('info', "handleAdminRequest: Processing admin request", [
                    'path' => $request->path(),
                    'host' => $request->header('Host'),
                    'method' => $request->method()
                ]);
            }
            
            // 对管理界面请求应用认证中间件和 CSRF 保护
            $response = $this->authMiddleware->process($request, function($request) {
                return $this->csrfMiddleware->process($request, function($request) {
                    return $this->adminRoutes->handleRequest($request);
                });
            });
            
            // 调试日志：确认返回的是管理界面响应
            if (function_exists('logger')) {
                logger('info', "handleAdminRequest: Admin response generated", [
                    'path' => $request->path(),
                    'status_code' => $response->getStatusCode()
                ]);
            }
            
            return $response;
        } catch (\Throwable $e) {
            if (function_exists('logger')) {
                logger('error', "handleAdminRequest: Error processing admin request", [
                    'path' => $request->path(),
                    'error' => $e->getMessage()
                ]);
            }
            $this->queueAsyncErrorLog($request, $e);
            return $this->createErrorResponse($e);
        }
    }
    
    /**
     * 处理 WAF 代理请求（8787 端口）
     */
    public function handleProxyRequest(Request $request): Response
    {
        $startTime = microtime(true);
        
        // 调试日志：记录所有代理请求
        if (function_exists('logger')) {
            logger('info', "handleProxyRequest: Processing proxy request", [
                'path' => $request->path(),
                'host' => $request->header('Host'),
                'method' => $request->method(),
                'remote_addr' => $request->header('X-Real-IP') ?: $request->header('X-Forwarded-For') ?: 'unknown'
            ]);
        }
        
        try {
            // 检查 WAF 是否启用
            if (!($this->config['enabled'] ?? true)) {
                return $this->createPassThroughResponse($request);
            }
            
            // 同步 WAF 检测（核心功能，必须同步）
            $wafResult = $this->wafMiddleware->processSync($request);
            
            if ($wafResult->isBlocked()) {
                // 异步记录安全日志（后台任务）
                $this->queueAsyncLog($request, $wafResult, microtime(true) - $startTime);
                return $this->createBlockResponse($wafResult, $request);
            }
            
            // 同步代理转发（核心功能，必须同步）
            $response = $this->proxyHandler->forwardSync($request);
            
            // 确保响应是有效的 Response 对象
            if (!($response instanceof Response)) {
                throw new \RuntimeException('Invalid response from proxy handler');
            }
            
            // 异步记录成功日志（后台任务）
            $this->queueAsyncLog($request, $wafResult, microtime(true) - $startTime);
            
            return $response;
            
        } catch (\Throwable $e) {
            // 捕获所有类型的错误（包括 Error 和 Exception）
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
    private function asyncLog(Request $request, \app\waf\core\WafResult $wafResult, float $duration): void
    {
        $this->logCollector->log($request, $wafResult, $duration);
    }
    
    /**
     * 异步记录错误日志
     */
    private function asyncErrorLog(Request $request, \Throwable $e): void
    {
        $this->logCollector->logError($request, $e);
    }

    

    /**
     * 异步代理请求到后端
     */
    private function asyncProxyRequest(Request $request): Response
    {
        try {
            // 异步获取可用的后端
            $backend = create_task(fn() => $this->asyncGetBackend());
            if (!$backend) {
                return $this->createServiceUnavailableResponse();
            }
            
            // 异步增加连接计数
            create_task(fn() => $this->asyncIncrementConnections($backend['name']));
            
            try {
                // 异步转发请求
                $response = create_task(fn() => $this->proxyHandler->forward($request));
                return $response;
            } finally {
                // 异步减少连接计数
                create_task(fn() => $this->asyncDecrementConnections($backend['name']));
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
    private function asyncGetBackend(): ?array
    {
        // 模拟异步后端选择
        sleep(0.001);
        
        return $this->backendManager->getAvailableBackend();
    }
    
    /**
     * 异步增加连接计数
     */
    private function asyncIncrementConnections(string $backendName): void
    {
        // 模拟异步连接计数
        sleep(0.001);
        
        $this->backendManager->incrementConnections($backendName);
    }
    
    /**
     * 异步减少连接计数
     */
    private function asyncDecrementConnections(string $backendName): void
    {
        // 模拟异步连接计数
        sleep(0.001);
        
        $this->backendManager->decrementConnections($backendName);
    }
    
    /**
     * 异步记录请求日志
     */
    private function asyncLogRequest(Request $request, \app\waf\core\WafResult $wafResult, float $duration): void
    {
        // 模拟异步日志记录
        sleep(0.001);
        
        $this->logCollector->log($request, $wafResult, $duration);
    }
    
    /**
     * 异步记录错误日志
     */
    private function asyncLogError(Request $request, \Exception $e): void
    {
        // 模拟异步错误日志记录
        sleep(0.001);
        
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
    private function createBlockResponse($wafResult, Request $request): Response
    {
        $statusCode = $wafResult->getStatusCode();
        $message = $wafResult->getMessage();
        $rule = $wafResult->getRule();
        
        // 检查请求是否接受 HTML（浏览器请求）
        $acceptHeader = $request->header('Accept', '');
        $wantsHtml = strpos($acceptHeader, 'text/html') !== false || 
                     strpos($acceptHeader, '*/*') !== false;
        
        if ($wantsHtml) {
            // 返回 HTML 错误页面
            $html = $this->generateBlockHtmlPage($statusCode, $message, $rule);
            return new Response($statusCode, [
                'Content-Type' => 'text/html; charset=utf-8',
            ], $html);
        }
        
        // 返回 JSON 响应（API 请求）
        return new Response($statusCode, [
            'Content-Type' => 'application/json; charset=utf-8',
        ], json_encode([
            'error' => 'Request Blocked by WAF',
            'message' => $message,
            'rule' => $rule,
            'timestamp' => time(),
        ], JSON_UNESCAPED_SLASHES));
    }
    
    /**
     * 生成 HTML 拦截页面
     */
    private function generateBlockHtmlPage(int $statusCode, string $message, string $rule): string
    {
        $statusText = $statusCode === 403 ? 'Forbidden' : 'Blocked';
        
        return <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>请求被拦截 - 天罡 WAF</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            color: #333;
        }
        .container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            padding: 40px;
            max-width: 500px;
            text-align: center;
        }
        h1 {
            color: #e74c3c;
            margin: 0 0 20px 0;
            font-size: 32px;
        }
        .status {
            font-size: 18px;
            color: #7f8c8d;
            margin-bottom: 20px;
        }
        .message {
            background: #f8f9fa;
            border-left: 4px solid #e74c3c;
            padding: 15px;
            margin: 20px 0;
            text-align: left;
            border-radius: 4px;
        }
        .rule {
            color: #e74c3c;
            font-weight: bold;
        }
        .footer {
            margin-top: 30px;
            color: #95a5a6;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>⚠️ 请求被拦截</h1>
        <div class="status">HTTP {$statusCode} - {$statusText}</div>
        <div class="message">
            <strong>拦截原因：</strong><span class="rule">{$rule}</span><br>
            <strong>详细信息：</strong>{$message}
        </div>
        <div class="footer">
            <p>您的请求已被天罡 WAF 安全防护系统拦截</p>
            <p>如有疑问，请联系系统管理员</p>
        </div>
    </div>
</body>
</html>
HTML;
    }
    
    /**
     * 创建错误响应（修复：区分生产/开发环境，防止信息泄露）
     */
    private function createErrorResponse(\Throwable $e): Response
    {
        $isDebug = env('APP_DEBUG', false) && env('APP_ENV', 'production') !== 'production';
        
        // 记录详细错误到日志（不返回给客户端）
        error_log(sprintf(
            'WAF Gateway Error [%s]: %s in %s:%d',
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        ));
        
        // 生成请求ID用于追踪
        $requestId = uniqid('req_', true);
        
        return new Response(500, [
            'Content-Type' => 'application/json; charset=utf-8',
        ], json_encode([
            'error' => 'Internal Server Error',
            'message' => $isDebug 
                ? $e->getMessage() 
                : 'An unexpected error occurred. Please contact support.',
            'request_id' => $requestId,
            'timestamp' => time(),
        ], JSON_UNESCAPED_SLASHES));
    }
    
    /**
     * 异步记录日志（后台任务）
     * 
     * 修复 P0: 异步日志在 Workerman 中无效
     * - 移除 fastcgi_finish_request()（Workerman 不是 FastCGI）
     * - Workerman 本身就是异步的，直接执行异步任务即可
     */
    private function queueAsyncLog(Request $request, \app\waf\core\WafResult $wafResult, float $duration): void
    {
        // Workerman 本身就是异步事件驱动的，直接执行异步任务
        // 不需要 fastcgi_finish_request()（那是 FastCGI 专用的）
        \PfinalClub\Asyncio\run(fn() => $this->asyncLog($request, $wafResult, $duration));
    }
    
    /**
     * 异步记录错误日志（后台任务）
     * 
     * 修复 P0: 异步日志在 Workerman 中无效
     * - 移除 fastcgi_finish_request()（Workerman 不是 FastCGI）
     * - Workerman 本身就是异步的，直接执行异步任务即可
     */
    private function queueAsyncErrorLog(Request $request, \Throwable $e): void
    {
        // Workerman 本身就是异步事件驱动的，直接执行异步任务
        // 不需要 fastcgi_finish_request()（那是 FastCGI 专用的）
        \PfinalClub\Asyncio\run(fn() => $this->asyncErrorLog($request, $e));
    }
    
    /**
     * 检查是否为管理界面请求
     * 
     * 修复：正确识别业务域名和管理界面
     * - 如果请求的域名在域名映射配置中，说明是业务域名，不是管理界面
     * - 只有管理域名（localhost, 127.0.0.1）下的特定路径才是管理界面
     */
    private function isWebRequest(Request $request): bool
    {
        $path = $request->path();
        $host = $request->header('Host', '');
        
        // 移除端口号（如果有）
        $host = preg_replace('/:\d+$/', '', $host);
        $host = strtolower($host);
        
        try {
            // 检查该域名是否在域名映射配置中
            // 如果在，说明是业务域名，不是管理界面
            $proxyConfig = $this->configManager->get('proxy') ?? [];
            $domainMappings = $proxyConfig['domain_mappings'] ?? [];
            
            foreach ($domainMappings as $mapping) {
                if (!($mapping['enabled'] ?? true)) {
                    continue;
                }
                
                $domain = $mapping['domain'] ?? '';
                if (empty($domain)) {
                    continue;
                }
                
                // 精确匹配
                if (strtolower($domain) === $host) {
                    return false; // 这是业务域名，不是管理界面
                }
                
                // 通配符匹配（如 *.api.smm.cn）
                if (strpos($domain, '*') === 0) {
                    $pattern = str_replace(['.', '*'], ['\.', '.*'], $domain);
                    $pattern = '/^' . $pattern . '$/i';
                    if (preg_match($pattern, $host)) {
                        return false; // 这是业务域名，不是管理界面
                    }
                }
            }
        } catch (\Exception $e) {
            // 如果配置加载失败，记录错误但继续处理
            error_log('Failed to load proxy config in isWebRequest: ' . $e->getMessage());
        }
        
        // 管理界面的域名列表（只有这些域名的请求才被认为是管理界面）
        $adminHosts = [
            'localhost',
            '127.0.0.1',
            '::1',
        ];
        
        // 如果 Host 不在管理域名列表中，不是管理界面请求
        if (!in_array($host, $adminHosts)) {
            return false; // 未知域名，默认不是管理界面
        }
        
        // 检查路径是否为管理界面路径
        $webPaths = [
            '/dashboard',
            '/admin',
            '/admin/',
            '/api/dashboard',
            '/api/performance',
            '/api/security',
            '/api/export',
            '/health'
        ];
        
        // 根路径 '/' 只有在管理域名下才被认为是管理界面
        if ($path === '/') {
            return true; // 管理域名下的根路径，重定向到管理界面
        }
        
        return in_array($path, $webPaths) || 
               str_starts_with($path, '/api/') || 
               str_starts_with($path, '/admin');
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
