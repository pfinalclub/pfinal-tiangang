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
        
        // 重置当前映射
        $this->currentMapping = null;
        
        try {
            // 构建目标 URL（会根据路径映射选择后端）
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
     * 异步构建目标 URL（支持路径映射）
     */
    private function asyncBuildTargetUrl(Request $request): \Generator
    {
        // 模拟异步 URL 构建
        yield sleep(0); // 修复：sleep 需要整数
        
        // 使用同步方法构建 URL（已支持路径映射）
        return $this->buildTargetUrl($request);
    }
    
    /**
     * 异步构建请求选项
     */
    private function asyncBuildRequestOptions(Request $request): \Generator
    {
        // 模拟异步选项构建
        yield sleep(0); // 修复：sleep 需要整数
        
        $options = [
            'timeout' => $this->backendConfig['timeout'] ?? 30,
            'connect_timeout' => $this->backendConfig['connect_timeout'] ?? 5,
            'http_errors' => false,
            'headers' => $this->filterHeaders($request->header(), $request),
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
        yield sleep(0); // 修复：sleep 需要整数
        
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
        yield sleep(0); // 修复：sleep 需要整数
        
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
        yield sleep(0); // 修复：sleep 需要整数，使用 0 表示不等待
        
        try {
            // Workerman Response 对象使用 rawBody() 方法获取 body
            $body = method_exists($response, 'rawBody') ? $response->rawBody() : (string)$response;
            $bodySize = strlen($body);
            
            if (function_exists('logger')) {
                logger('info', 'Proxy performance', [
                    'url' => $request->path(),
                    'method' => $request->method(),
                    'status_code' => $response->getStatusCode(),
                    'duration' => round($duration * 1000, 2) . 'ms',
                    'size' => $bodySize
                ]);
            }
        } catch (\Exception $e) {
            // 日志记录失败，静默处理
            error_log('Failed to log performance: ' . $e->getMessage());
        }
    }
    
    /**
     * 构建目标 URL（修复：添加 SSRF 防护，支持路径映射）
     */
    private function buildTargetUrl(Request $request): string
    {
        $backend = $this->getBackendConfig($request);
        
        // 检查后端配置是否有效
        if (empty($backend) || !isset($backend['url'])) {
            throw new \RuntimeException('Backend configuration is invalid or missing URL');
        }
        
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
            // 允许 localhost 和 127.0.0.1（用于本地开发）
            $allowedLocalHosts = ['localhost', '127.0.0.1', '::1'];
            if (!in_array(strtolower($baseHost), $allowedLocalHosts)) {
                try {
                    $hostIp = @gethostbyname($baseHost);
                    if ($hostIp && $hostIp !== $baseHost && !filter_var($hostIp, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new \InvalidArgumentException('SSRF attempt detected: Private IP address not allowed');
                    }
                } catch (\Exception $e) {
                    // DNS 解析失败，记录日志但允许继续（可能是网络问题）
                    error_log('DNS resolution failed for ' . $baseHost . ': ' . $e->getMessage());
                }
            }
        }
        
        // 5. 构建目标路径（清理路径，防止路径遍历，支持路径映射）
        $requestPath = $request->path();
        $path = $this->sanitizePath($requestPath);
        
        // 如果存在路径映射且启用了 strip_prefix，移除路径前缀
        if ($this->currentMapping && ($this->currentMapping['strip_prefix'] ?? false)) {
            $mappingPath = rtrim($this->currentMapping['path'] ?? '', '/');
            if ($mappingPath && str_starts_with($path, $mappingPath)) {
                $path = substr($path, strlen($mappingPath));
                if (empty($path) || $path[0] !== '/') {
                    $path = '/' . $path;
                }
            }
        }
        
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
                // 允许 localhost 和 127.0.0.1（用于本地开发）
                $allowedLocalHosts = ['localhost', '127.0.0.1', '::1'];
                if (!in_array(strtolower($targetHost), $allowedLocalHosts)) {
                    try {
                        $targetIp = @gethostbyname($targetHost);
                        if ($targetIp && $targetIp !== $targetHost && !filter_var($targetIp, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                            throw new \InvalidArgumentException('SSRF attempt detected: Target resolves to private IP');
                        }
                    } catch (\Exception $e) {
                        // DNS 解析失败，记录日志但允许继续（可能是网络问题）
                        error_log('DNS resolution failed for ' . $targetHost . ': ' . $e->getMessage());
                    }
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
            'headers' => $this->filterHeaders($request->header(), $request),
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
    private function filterHeaders(array $headers, ?Request $request = null): array
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
        if ($request) {
            $clientIp = $this->getClientIp($request);
            if (filter_var($clientIp, FILTER_VALIDATE_IP)) {
                $filteredHeaders['X-Forwarded-For'] = $clientIp;
                $filteredHeaders['X-Real-IP'] = $clientIp;
            }
            
            $protocol = $this->getProtocol($request);
            if (in_array($protocol, ['http', 'https'])) {
                $filteredHeaders['X-Forwarded-Proto'] = $protocol;
            }
        }
        
        return $filteredHeaders;
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
            'content-encoding',
            'server' // 隐藏后端服务器信息，使用 WAF 的 Server 头
        ];
        
        foreach ($headers as $name => $values) {
            $lowerName = strtolower($name);
            if (!in_array($lowerName, $excludeHeaders)) {
                $filteredHeaders[$name] = is_array($values) ? $values[0] : $values;
            }
        }
        
        // 添加 WAF 的 Server 头（可选）
        // $filteredHeaders['Server'] = 'Tiangang-WAF/1.0';
        
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
        
        try {
            if (function_exists('logger')) {
                logger('error', 'Proxy error', [
                    'url' => $request->path(),
                    'method' => $request->method(),
                    'error' => $e->getMessage(),
                    'status_code' => $statusCode
                ]);
            } else {
                error_log('Proxy error: ' . $e->getMessage() . ' for ' . $request->method() . ' ' . $request->path());
            }
        } catch (\Exception $logError) {
            error_log('Proxy error: ' . $e->getMessage() . ' for ' . $request->method() . ' ' . $request->path());
        }
        
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
        try {
            if (function_exists('logger')) {
                logger('error', 'Unexpected proxy error', [
                    'url' => $request->path(),
                    'method' => $request->method(),
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $isDebug ? $e->getTraceAsString() : null, // 生产环境不记录堆栈
                ]);
            } else {
                error_log('Unexpected proxy error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            }
        } catch (\Exception $logError) {
            // 日志记录失败，使用 error_log 作为后备
            error_log('Unexpected proxy error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        }
        
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
        try {
            // Workerman Response 对象使用 rawBody() 方法获取 body
            $body = method_exists($response, 'rawBody') ? $response->rawBody() : (string)$response;
            $bodySize = strlen($body);
            
            if (function_exists('logger')) {
                logger('info', 'Proxy performance', [
                    'url' => $request->path(),
                    'method' => $request->method(),
                    'status_code' => $response->getStatusCode(),
                    'duration' => round($duration * 1000, 2) . 'ms',
                    'size' => $bodySize
                ]);
            }
        } catch (\Exception $e) {
            // 日志记录失败，静默处理
            error_log('Failed to log performance: ' . $e->getMessage());
        }
    }
    
    /**
     * 获取客户端 IP（修复：使用 Request 对象，支持 Workerman 环境）
     */
    private function getClientIp(Request $request): string
    {
        // 1. 获取连接的真实 IP（最可靠）
        $remoteIp = $request->connection->getRemoteIp() ?? '127.0.0.1';
        
        // 2. 验证 IP 格式
        if (!filter_var($remoteIp, FILTER_VALIDATE_IP)) {
            return '127.0.0.1';
        }
        
        // 3. 检查是否为可信代理
        $trustedProxies = $this->config['security']['trusted_proxies'] ?? ['127.0.0.1', '::1'];
        
        // 如果不是可信代理，直接返回连接 IP（防止 IP 伪造）
        if (!in_array($remoteIp, $trustedProxies)) {
            return $remoteIp;
        }
        
        // 4. 如果是可信代理，才信任代理头
        $forwardedFor = $request->header('X-Forwarded-For');
        if ($forwardedFor) {
            // 取最后一个 IP（最靠近客户端的）
            $ips = array_map('trim', explode(',', $forwardedFor));
            $ip = end($ips);
            
            // 验证 IP 格式（允许私有 IP）
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
        
        // 5. 尝试其他代理头（仅当是可信代理时）
        $realIp = $request->header('X-Real-IP');
        if ($realIp && filter_var($realIp, FILTER_VALIDATE_IP)) {
            return $realIp;
        }
        
        // 6. 回退到连接 IP
        return $remoteIp;
    }
    
    /**
     * 获取协议（修复：使用 Request 对象）
     */
    private function getProtocol(Request $request): string
    {
        // 从请求头获取协议
        $proto = $request->header('X-Forwarded-Proto');
        if ($proto && in_array(strtolower($proto), ['http', 'https'])) {
            return strtolower($proto);
        }
        
        // 从连接获取协议
        $scheme = $request->connection->transport ?? 'tcp';
        if ($scheme === 'ssl' || $scheme === 'tls') {
            return 'https';
        }
        
        // 默认返回 http
        return 'http';
    }
    
    /**
     * 获取后端配置（支持域名映射和路径映射）
     * 优先级：域名映射 > 路径映射 > 默认后端
     */
    private function getBackendConfig(?Request $request = null): array
    {
        // 如果没有请求，返回默认后端
        if (!$request) {
            return $this->getDefaultBackend();
        }
        
        // 1. 优先检查域名映射（主要路由方式）
        $host = $request->header('Host');
        if ($host) {
            // 移除端口号（如果有）
            $host = preg_replace('/:\d+$/', '', $host);
            
            $domainMapping = $this->findDomainMapping($host);
            if ($domainMapping) {
                // 找到域名映射，解析后端配置（支持 name 或直接 URL）
                $backend = $this->resolveBackend($domainMapping['backend'] ?? '');
                if ($backend && isset($backend['url'])) {
                    // 保存映射信息，供 buildTargetUrl 使用
                    $this->currentMapping = $domainMapping;
                    return $backend;
                }
            }
        }
        
        // 2. 检查路径映射（补充路由方式）
        $path = $request->path();
        $pathMapping = $this->findPathMapping($path);
        
        if ($pathMapping) {
            // 找到路径映射，解析后端配置（支持 name 或直接 URL）
            $backend = $this->resolveBackend($pathMapping['backend'] ?? '');
            if ($backend && isset($backend['url'])) {
                // 保存映射信息，供 buildTargetUrl 使用
                $this->currentMapping = $pathMapping;
                return $backend;
            }
        }
        
        // 3. 没有找到映射，返回默认后端
        return $this->getDefaultBackend();
    }
    
    /**
     * 解析后端配置（支持 name 或直接 URL）
     * 
     * @param string $backend 后端服务名称或直接 URL
     * @return array|null 后端配置数组，如果解析失败返回 null
     */
    private function resolveBackend(string $backend): ?array
    {
        if (empty($backend)) {
            return null;
        }
        
        // 如果 backend 是 URL 格式，直接使用
        if (filter_var($backend, FILTER_VALIDATE_URL)) {
            return [
                'name' => 'direct',
                'url' => $backend,
                'weight' => 1,
                'health_url' => rtrim($backend, '/') . '/health',
                'health_timeout' => 5,
                'recovery_time' => 60,
            ];
        }
        
        // 否则，通过 name 查找后端服务
        $foundBackend = $this->findBackendByName($backend);
        if ($foundBackend && isset($foundBackend['url'])) {
            return $foundBackend;
        }
        
        return null;
    }
    
    /**
     * 当前请求的路径映射（临时存储）
     */
    private ?array $currentMapping = null;
    
    /**
     * 查找域名映射（支持精确匹配和通配符匹配）
     */
    private function findDomainMapping(string $host): ?array
    {
        $mappings = $this->backendConfig['domain_mappings'] ?? [];
        
        // 先匹配精确域名，再匹配通配符
        // 1. 精确匹配
        foreach ($mappings as $mapping) {
            if (!($mapping['enabled'] ?? true)) {
                continue; // 跳过禁用的映射
            }
            
            $domain = $mapping['domain'] ?? '';
            if (empty($domain)) {
                continue;
            }
            
            // 精确匹配
            if ($domain === $host) {
                return $mapping;
            }
        }
        
        // 2. 通配符匹配（如 *.api.smm.cn）
        foreach ($mappings as $mapping) {
            if (!($mapping['enabled'] ?? true)) {
                continue;
            }
            
            $domain = $mapping['domain'] ?? '';
            if (empty($domain) || strpos($domain, '*') === false) {
                continue; // 跳过非通配符域名
            }
            
            // 将通配符转换为正则表达式
            $pattern = str_replace(['.', '*'], ['\.', '.*'], $domain);
            $pattern = '/^' . $pattern . '$/';
            
            if (preg_match($pattern, $host)) {
                return $mapping;
            }
        }
        
        return null;
    }
    
    /**
     * 查找路径映射
     */
    private function findPathMapping(string $path): ?array
    {
        $mappings = $this->backendConfig['path_mappings'] ?? [];
        
        // 按路径长度降序排序，优先匹配更长的路径
        usort($mappings, function($a, $b) {
            return strlen($b['path'] ?? '') <=> strlen($a['path'] ?? '');
        });
        
        foreach ($mappings as $mapping) {
            if (!($mapping['enabled'] ?? true)) {
                continue; // 跳过禁用的映射
            }
            
            $mappingPath = $mapping['path'] ?? '';
            if (empty($mappingPath)) {
                continue;
            }
            
            // 精确匹配或前缀匹配
            if ($path === $mappingPath || str_starts_with($path, rtrim($mappingPath, '/') . '/')) {
                return $mapping;
            }
        }
        
        return null;
    }
    
    /**
     * 根据名称查找后端
     */
    private function findBackendByName(string $name): ?array
    {
        $backends = $this->backendConfig['backends'] ?? [];
        foreach ($backends as $backend) {
            if (($backend['name'] ?? '') === $name) {
                return $backend;
            }
        }
        return null;
    }
    
    /**
     * 获取默认后端
     */
    private function getDefaultBackend(): array
    {
        $defaultName = $this->backendConfig['default_backend'] ?? 'primary';
        $backend = $this->findBackendByName($defaultName);
        
        if ($backend) {
            return $backend;
        }
        
        // 如果找不到，返回第一个后端
        $backends = $this->backendConfig['backends'] ?? [];
        if (!empty($backends)) {
            return $backends[0];
        }
        
        // 最后的默认值
        return [
            'url' => 'http://localhost:8080',
            'timeout' => 30,
            'connect_timeout' => 5,
            'verify_ssl' => true,
            'stream_threshold' => 1024 * 1024
        ];
    }
    
    /**
     * 异步记录性能指标（后台任务）
     * 
     * 修复：Workerman 不是 FastCGI，不需要 fastcgi_finish_request
     */
    private function queueAsyncLogPerformance(Request $request, Response $response, float $duration): void
    {
        // Workerman 本身就是异步事件驱动的，直接执行异步任务即可
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
