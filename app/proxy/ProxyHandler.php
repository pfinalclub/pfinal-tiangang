<?php

namespace Tiangang\Waf\Proxy;

use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;
use Tiangang\Waf\Config\ConfigManager;
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
    private array $config;
    private array $backendConfig;
    
    public function __construct()
    {
        $this->configManager = new ConfigManager();
        $this->config = $this->configManager->get('waf');
        $this->backendConfig = $this->configManager->get('proxy');
        $this->httpClient = $this->createHttpClient();
    }
    
    /**
     * 异步转发请求到后端
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
     * 构建目标 URL（同步版本，保留兼容性）
     */
    private function buildTargetUrl(Request $request): string
    {
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
     * 过滤请求头
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
            if (!in_array($lowerName, $excludeHeaders)) {
                $filteredHeaders[$name] = $value;
            }
        }
        
        // 添加代理相关头
        $filteredHeaders['X-Forwarded-For'] = $this->getClientIp();
        $filteredHeaders['X-Forwarded-Proto'] = $this->getProtocol();
        $filteredHeaders['X-Real-IP'] = $this->getClientIp();
        
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
     * 处理意外错误
     */
    private function handleUnexpectedError(\Exception $e, Request $request): Response
    {
        logger('error', 'Unexpected proxy error', [
            'url' => $request->path(),
            'method' => $request->method(),
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return new Response(500, [
            'Content-Type' => 'application/json',
        ], json_encode([
            'error' => 'Internal Server Error',
            'message' => 'An unexpected error occurred',
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
