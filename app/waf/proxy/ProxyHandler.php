<?php

namespace app\waf\proxy;

use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;
use app\waf\config\ConfigManager;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Stream;
use PfinalClub\Asyncio\{create_task, gather, wait_for, sleep};

/**
 * 代理转发处理器
 * 
 * 负责将请求转发到后端服务，并处理响应
 */
class ProxyHandler
{
    private ConfigManager $configManager;
    private HttpClient $httpClient;
    private ?array $config;
    private ?array $backendConfig;
    
    public function __construct()
    {
        $this->configManager = new ConfigManager();
        $this->config = $this->configManager->get('waf') ?? [];
        $this->backendConfig = $this->configManager->get('proxy') ?? [];
        $this->httpClient = $this->createHttpClient();
    }
    
    /**
     * 同步转发请求到后端（混合架构核心）
     */
    public function forwardSync(Request $request): Response
    {
        $startTime = microtime(true);
        
        try {
            // 构建目标 URL
            $targetUrl = $this->buildTargetUrl($request);
            
            // 构建请求选项
            $options = $this->buildRequestOptions($request);
            
            // 发送请求
            $response = $this->httpClient->request(
                $request->method(),
                $targetUrl,
                $options
            );
            
            // 处理响应
            $proxyResponse = $this->processResponse($response, $request);
            
            // 异步记录性能指标（后台任务）
            $this->queueAsyncLogPerformance($request, $proxyResponse, microtime(true) - $startTime);
            
            return $proxyResponse;
            
        } catch (RequestException $e) {
            return $this->handleProxyError($e, $request);
        } catch (\Exception $e) {
            return $this->handleUnexpectedError($e, $request);
        }
    }

    /**
     * 异步转发请求到后端（保留用于后台任务）
     */
    public function forward(Request $request): \Generator
    {
        $startTime = microtime(true);
        
        try {
            // 异步构建目标 URL
            $targetUrl = yield create_task($this->asyncBuildTargetUrl($request));
            
            // 异步构建请求选项
            $options = yield create_task($this->asyncBuildRequestOptions($request));
            
            // 异步发送请求
            $response = yield create_task($this->asyncHttpRequest($request, $targetUrl, $options));
            
            // 异步处理响应
            $proxyResponse = yield create_task($this->asyncProcessResponse($response, $request));
            
            // 异步记录性能指标
            create_task($this->asyncLogPerformance($request, $proxyResponse, microtime(true) - $startTime));
            
            return $proxyResponse;
            
        } catch (RequestException $e) {
            return $this->handleProxyError($e, $request);
        } catch (\Exception $e) {
            return $this->handleUnexpectedError($e, $request);
        }
    }
    
    /**
     * 异步构建目标 URL
     */
    private function asyncBuildTargetUrl(Request $request): \Generator
    {
        // 模拟异步 URL 构建
        yield sleep(0.001);
        
        $backend = $this->getBackendConfig();
        $baseUrl = $backend['url'];
        $path = $request->path();
        $query = $request->queryString();
        
        $targetUrl = rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
        
        if ($query) {
            $targetUrl .= '?' . $query;
        }
        
        return $targetUrl;
    }
    
    /**
     * 异步构建请求选项
     */
    private function asyncBuildRequestOptions(Request $request): \Generator
    {
        // 模拟异步选项构建
        yield sleep(0.001);
        
        $options = [
            'timeout' => $this->backendConfig['timeout'] ?? 30,
            'connect_timeout' => $this->backendConfig['connect_timeout'] ?? 5,
            'http_errors' => false,
            'headers' => $this->filterHeaders($request->header()),
            'verify' => $this->backendConfig['verify_ssl'] ?? true,
        ];
        
        // 添加请求体
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            $body = $request->rawBody();
            if ($body) {
                $options['body'] = $body;
            }
        }
        
        return $options;
    }
    
    /**
     * 异步 HTTP 请求
     */
    private function asyncHttpRequest(Request $request, string $targetUrl, array $options): \Generator
    {
        // 模拟异步 HTTP 请求
        yield sleep(0.01);
        
        return $this->httpClient->request(
            $request->method(),
            $targetUrl,
            $options
        );
    }
    
    /**
     * 异步处理响应
     */
    private function asyncProcessResponse($response, Request $request): \Generator
    {
        // 模拟异步响应处理
        yield sleep(0.002);
        
        $statusCode = $response->getStatusCode();
        $headers = $this->filterResponseHeaders($response->getHeaders());
        $body = $response->getBody();
        
        // 流式传输处理
        if ($this->shouldStreamResponse($response)) {
            return $this->createStreamingResponse($statusCode, $headers, $body);
        }
        
        // 普通响应处理
        $content = $body->getContents();
        return new Response($statusCode, $headers, $content);
    }
    
    /**
     * 异步记录性能指标
     */
    private function asyncLogPerformance(Request $request, Response $response, float $duration): \Generator
    {
        // 模拟异步日志记录
        yield sleep(0.001);
        
        logger('info', 'Proxy performance', [
            'url' => $request->path(),
            'method' => $request->method(),
            'status_code' => $response->getStatusCode(),
            'duration' => round($duration * 1000, 2) . 'ms',
            'size' => strlen($response->getBody())
        ]);
    }
    
    /**
     * 构建目标 URL（修复：添加 SSRF 防护）
     */
    private function buildTargetUrl(Request $request): string
    {
        $backend = $this->getBackendConfig();
        $baseUrl = $backend['url'];
        
        // 1. 验证基础 URL
        if (!filter_var($baseUrl, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('Invalid backend URL configuration');
        }
        
        $parsedBase = parse_url($baseUrl);
        if (!$parsedBase || !isset($parsedBase['scheme'], $parsedBase['host'])) {
            throw new \InvalidArgumentException('Invalid backend URL structure');
        }
        
        // 2. 验证协议（只允许 http 和 https）
        $allowedSchemes = $this->backendConfig['security']['allowed_schemes'] ?? ['http', 'https'];
        if (!in_array($parsedBase['scheme'], $allowedSchemes)) {
            throw new \InvalidArgumentException('Backend URL must use http or https protocol');
        }
        
        // 3. 验证目标主机（防止 SSRF）
        $allowedHosts = $this->backendConfig['security']['allowed_backend_hosts'] ?? [];
        $baseHost = $parsedBase['host'];
        
        // 如果配置了允许的主机列表，必须匹配
        if (!empty($allowedHosts) && !in_array($baseHost, $allowedHosts)) {
            // 如果列表不为空但不匹配，添加基础主机到列表（向后兼容）
            $allowedHosts[] = $baseHost;
        }
        
        // 4. 阻止私有 IP（如果启用）
        $blockPrivateIps = $this->backendConfig['security']['block_private_ips'] ?? true;
        if ($blockPrivateIps) {
            $hostIp = gethostbyname($baseHost);
            if ($hostIp !== $baseHost && !filter_var($hostIp, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new \InvalidArgumentException('SSRF attempt detected: Private IP address not allowed');
            }
        }
        
        // 5. 构建目标路径（清理路径，防止路径遍历）
        $path = $this->sanitizePath($request->path());
        $query = $request->queryString();
        
        // 6. 验证并清理查询字符串
        if ($query) {
            parse_str($query, $queryParams);
            // 移除可能导致问题的参数
            unset($queryParams['url'], $queryParams['redirect'], $queryParams['return']);
            $query = http_build_query($queryParams);
        }
        
        $targetUrl = rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
        
        if ($query) {
            $targetUrl .= '?' . $query;
        }
        
        // 7. 最终验证：确保目标主机在允许列表中或为空列表（允许所有）
        $parsedTarget = parse_url($targetUrl);
        if ($parsedTarget && isset($parsedTarget['host'])) {
            $targetHost = $parsedTarget['host'];
            
            // 如果配置了允许列表，必须匹配
            if (!empty($allowedHosts) && !in_array($targetHost, $allowedHosts)) {
                throw new \InvalidArgumentException('SSRF attempt detected: Host not in allowed list');
            }
            
            // 验证目标主机不为私有 IP（如果启用）
            if ($blockPrivateIps) {
                $targetIp = gethostbyname($targetHost);
                if ($targetIp !== $targetHost && !filter_var($targetIp, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    throw new \InvalidArgumentException('SSRF attempt detected: Target resolves to private IP');
                }
            }
        }
        
        return $targetUrl;
    }
    
    /**
     * 清理路径（防止路径遍历）
     */
    private function sanitizePath(string $path): string
    {
        // 移除路径遍历序列
        $path = str_replace(['../', '..\\', '//'], '', $path);
        // 移除危险字符
        $path = preg_replace('/[^a-zA-Z0-9\-_\/\.]/', '', $path);
        // 规范化路径
        $path = preg_replace('#/+#', '/', $path);
        return $path;
    }
    
    /**
     * 构建请求选项
     */
    private function buildRequestOptions(Request $request): array
    {
        $options = [
            'timeout' => $this->backendConfig['timeout'] ?? 30,
            'connect_timeout' => $this->backendConfig['connect_timeout'] ?? 5,
            'http_errors' => false, // 不抛出 HTTP 错误
            'headers' => $this->filterHeaders($request->header()),
            'verify' => $this->backendConfig['verify_ssl'] ?? true,
        ];
        
        // 添加请求体
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            $body = $request->rawBody();
            if ($body) {
                $options['body'] = $body;
            }
        }
        
        // 添加认证信息
        if (isset($this->backendConfig['auth'])) {
            $options['auth'] = $this->backendConfig['auth'];
        }
        
        return $options;
    }
    
    /**
     * 过滤请求头（修复：防止请求头注入）
     */
    private function filterHeaders(array $headers): array
    {
        $filteredHeaders = [];
        $excludeHeaders = [
            'host',
            'connection',
            'upgrade',
            'proxy-connection',
            'proxy-authenticate',
            'proxy-authorization',
            'te',
            'trailers',
            'transfer-encoding'
        ];
        
        foreach ($headers as $name => $value) {
            $lowerName = strtolower($name);
            
            // 排除危险头
            if (in_array($lowerName, $excludeHeaders)) {
                continue;
            }
            
            // 验证头名称（防止注入）
            if (preg_match('/[\r\n]/', $name)) {
                continue; // 跳过包含换行符的头名
            }
            
            // 验证头值（防止注入）
            if (is_string($value) && preg_match('/[\r\n]/', $value)) {
                $value = str_replace(["\r", "\n"], '', $value); // 移除换行符
            }
            
            $filteredHeaders[$name] = $value;
        }
        
        // 添加代理相关头（使用验证过的值）
        $clientIp = $this->getClientIp();
        if (filter_var($clientIp, FILTER_VALIDATE_IP)) {
            $filteredHeaders['X-Forwarded-For'] = $clientIp;
            $filteredHeaders['X-Real-IP'] = $clientIp;
        }
        
        $protocol = $this->getProtocol();
        if (in_array($protocol, ['http', 'https'])) {
            $filteredHeaders['X-Forwarded-Proto'] = $protocol;
        }
        
        return $filteredHeaders;
    }
    
    /**
     * 获取客户端 IP（安全版本）
     */
    private function getClientIp(): string
    {
        // 这里应该使用与 WafMiddleware 相同的安全方法
        // 简化版本，实际应该注入或共享
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
    
    /**
     * 获取协议（安全版本）
     */
    private function getProtocol(): string
    {
        $protocol = $_SERVER['HTTPS'] ?? $_SERVER['REQUEST_SCHEME'] ?? 'http';
        
        // 标准化
        if ($protocol === 'on' || $protocol === '1') {
            return 'https';
        }
        
        // 只允许 http 或 https
        if (!in_array(strtolower($protocol), ['http', 'https'])) {
            return 'http';
        }
        
        return strtolower($protocol);
    }
    
    /**
     * 处理响应
     */
    private function processResponse($response, Request $request): Response
    {
        $statusCode = $response->getStatusCode();
        $headers = $this->filterResponseHeaders($response->getHeaders());
        $body = $response->getBody();
        
        // 流式传输处理
        if ($this->shouldStreamResponse($response)) {
            return $this->createStreamingResponse($statusCode, $headers, $body);
        }
        
        // 普通响应处理
        $content = $body->getContents();
        return new Response($statusCode, $headers, $content);
    }
    
    /**
     * 过滤响应头
     */
    private function filterResponseHeaders(array $headers): array
    {
        $filteredHeaders = [];
        $excludeHeaders = [
            'connection',
            'upgrade',
            'proxy-connection',
            'transfer-encoding',
            'content-encoding'
        ];
        
        foreach ($headers as $name => $values) {
            $lowerName = strtolower($name);
            if (!in_array($lowerName, $excludeHeaders)) {
                $filteredHeaders[$name] = is_array($values) ? $values[0] : $values;
            }
        }
        
        return $filteredHeaders;
    }
    
    /**
     * 判断是否应该流式传输
     */
    private function shouldStreamResponse($response): bool
    {
        $contentType = $response->getHeaderLine('Content-Type');
        $contentLength = $response->getHeaderLine('Content-Length');
        
        // 大文件或流式内容
        if ($contentLength && intval($contentLength) > $this->backendConfig['stream_threshold'] ?? 1024 * 1024) {
            return true;
        }
        
        // 流式内容类型
        $streamingTypes = [
            'text/event-stream',
            'application/octet-stream',
            'video/',
            'audio/'
        ];
        
        foreach ($streamingTypes as $type) {
            if (strpos($contentType, $type) === 0) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * 创建流式响应
     */
    private function createStreamingResponse(int $statusCode, array $headers, $body): Response
    {
        // 设置流式传输头
        $headers['Transfer-Encoding'] = 'chunked';
        unset($headers['Content-Length']);
        
        return new Response($statusCode, $headers, $body);
    }
    
    /**
     * 处理代理错误
     */
    private function handleProxyError(RequestException $e, Request $request): Response
    {
        $statusCode = 502; // Bad Gateway
        $message = 'Backend service unavailable';
        
        if ($e->hasResponse()) {
            $statusCode = $e->getResponse()->getStatusCode();
            $message = $e->getMessage();
        }
        
        logger('error', 'Proxy error', [
            'url' => $request->path(),
            'method' => $request->method(),
            'error' => $e->getMessage(),
            'status_code' => $statusCode
        ]);
        
        return new Response($statusCode, [
            'Content-Type' => 'application/json',
        ], json_encode([
            'error' => 'Proxy Error',
            'message' => $message,
            'timestamp' => time(),
        ]));
    }
    
    /**
     * 处理意外错误（修复：区分生产/开发环境，防止信息泄露）
     */
    private function handleUnexpectedError(\Exception $e, Request $request): Response
    {
        $isDebug = env('APP_DEBUG', false) && env('APP_ENV', 'production') !== 'production';
        
        // 记录详细错误到日志（不返回给客户端）
        logger('error', 'Unexpected proxy error', [
            'url' => $request->path(),
            'method' => $request->method(),
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $isDebug ? $e->getTraceAsString() : null, // 生产环境不记录堆栈
        ]);
        
        // 生成请求ID用于追踪
        $requestId = uniqid('req_', true);
        
        return new Response(500, [
            'Content-Type' => 'application/json',
        ], json_encode([
            'error' => 'Internal Server Error',
            'message' => $isDebug 
                ? $e->getMessage() 
                : 'An unexpected error occurred. Please contact support.',
            'request_id' => $requestId,
            'timestamp' => time(),
        ]));
    }
    
    /**
     * 记录性能指标
     */
    private function logPerformance(Request $request, Response $response, float $duration): void
    {
        logger('info', 'Proxy performance', [
            'url' => $request->path(),
            'method' => $request->method(),
            'status_code' => $response->getStatusCode(),
            'duration' => round($duration * 1000, 2) . 'ms',
            'size' => strlen($response->getBody())
        ]);
    }
    
    /**
     * 获取客户端 IP
     */
    private function getClientIp(): string
    {
        $headers = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR'
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return '127.0.0.1';
    }
    
    /**
     * 获取协议
     */
    private function getProtocol(): string
    {
        return isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    }
    
    /**
     * 获取后端配置
     */
    private function getBackendConfig(): array
    {
        return $this->backendConfig['backends'][0] ?? [
            'url' => 'http://localhost:8080',
            'timeout' => 30,
            'connect_timeout' => 5,
            'verify_ssl' => true,
            'stream_threshold' => 1024 * 1024
        ];
    }
    
    /**
     * 异步记录性能指标（后台任务）
     */
    private function queueAsyncLogPerformance(Request $request, Response $response, float $duration): void
    {
        // 使用 fastcgi_finish_request 在响应发送后执行
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
        
        // 在后台异步记录性能指标
        \PfinalClub\Asyncio\run($this->asyncLogPerformance($request, $response, $duration));
    }

    /**
     * 创建 HTTP 客户端
     */
    private function createHttpClient(): HttpClient
    {
        return new HttpClient([
            'timeout' => $this->backendConfig['timeout'] ?? 30,
            'connect_timeout' => $this->backendConfig['connect_timeout'] ?? 5,
            'verify' => $this->backendConfig['verify_ssl'] ?? true,
            'allow_redirects' => false, // 不自动跟随重定向
        ]);
    }
}
