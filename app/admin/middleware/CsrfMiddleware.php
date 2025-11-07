<?php

namespace app\admin\middleware;

use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;
use Predis\Client as RedisClient;
use app\waf\config\ConfigManager;

/**
 * CSRF 保护中间件
 * 
 * 防止跨站请求伪造攻击
 */
class CsrfMiddleware
{
    private ConfigManager $configManager;
    private ?RedisClient $redis;
    private int $tokenLifetime = 3600; // Token 有效期（秒）
    
    public function __construct()
    {
        $this->configManager = new ConfigManager();
        $this->redis = $this->getRedisClient();
    }
    
    /**
     * 处理请求
     */
    public function process(Request $request, callable $next): Response
    {
        // 跳过不需要 CSRF 保护的请求
        if ($this->shouldSkip($request)) {
            return $next($request);
        }
        
        // 验证 CSRF Token
        if (!$this->validateToken($request)) {
            return $this->handleInvalidToken($request);
        }
        
        return $next($request);
    }
    
    /**
     * 判断是否应该跳过 CSRF 检查
     */
    private function shouldSkip(Request $request): bool
    {
        $method = $request->method();
        $path = $request->path();
        
        // GET、HEAD、OPTIONS 请求不需要 CSRF 保护
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'])) {
            return true;
        }
        
        // 公开 API 端点（如果配置了）
        $publicPaths = [
            '/health',
            '/api/health',
            '/admin/login', // 登录页面（GET 请求已在上面的检查中处理）
            '/admin/auth/login', // 登录接口：在 AuthController 中自行验证（支持临时会话）
        ];
        
        if (in_array($path, $publicPaths)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * 验证 CSRF Token
     */
    private function validateToken(Request $request): bool
    {
        // 从请求中获取 Token
        $token = $this->extractToken($request);
        
        if (empty($token)) {
            return false;
        }
        
        // 从 Session 中获取预期的 Token
        $sessionId = $request->cookie('waf_session');
        if (!$sessionId) {
            return false;
        }
        
        return $this->validateTokenForSession($sessionId, $token);
    }
    
    /**
     * 验证指定会话的 CSRF Token（公开方法，供 AuthController 使用）
     */
    public function validateTokenForSession(string $sessionId, ?string $token): bool
    {
        if (empty($token)) {
            return false;
        }
        
        $expectedToken = $this->getTokenFromSession($sessionId);
        
        if (empty($expectedToken)) {
            return false;
        }
        
        // 使用时间安全的比较
        return hash_equals($expectedToken, $token);
    }
    
    /**
     * 从请求中提取 Token
     */
    private function extractToken(Request $request): ?string
    {
        // 优先从 Header 获取（AJAX 请求）
        $headerToken = $request->header('X-CSRF-Token');
        if ($headerToken) {
            return $headerToken;
        }
        
        // 检查 Content-Type，如果是 JSON，从 JSON 中提取
        $contentType = $request->header('Content-Type', '');
        if (strpos($contentType, 'application/json') !== false) {
            $rawBody = $request->rawBody();
            if (!empty($rawBody)) {
                $jsonData = @json_decode($rawBody, true);
                if ($jsonData && isset($jsonData['_token'])) {
                    return $jsonData['_token'];
                }
                if ($jsonData && isset($jsonData['csrf_token'])) {
                    return $jsonData['csrf_token'];
                }
            }
        }
        
        // 从 POST 数据获取（表单提交）
        parse_str($request->rawBody(), $postData);
        return $postData['_token'] ?? $postData['csrf_token'] ?? null;
    }
    
    /**
     * 从 Session 获取 Token
     */
    private function getTokenFromSession(string $sessionId): ?string
    {
        if ($this->redis) {
            try {
                return $this->redis->get("csrf_token:{$sessionId}");
            } catch (\Exception $e) {
                // Redis 不可用时，使用文件存储
                return $this->getTokenFromFile($sessionId);
            }
        }
        
        return $this->getTokenFromFile($sessionId);
    }
    
    /**
     * 从文件获取 Token（Redis 不可用时的后备方案）
     */
    private function getTokenFromFile(string $sessionId): ?string
    {
        $tokenFile = runtime_path('csrf_tokens/' . substr($sessionId, 0, 2) . '/' . $sessionId . '.token');
        
        if (!file_exists($tokenFile)) {
            return null;
        }
        
        $tokenData = @json_decode(file_get_contents($tokenFile), true);
        
        if (!$tokenData || !isset($tokenData['token'], $tokenData['expires'])) {
            @unlink($tokenFile);
            return null;
        }
        
        // 检查是否过期
        if ($tokenData['expires'] < time()) {
            @unlink($tokenFile);
            return null;
        }
        
        return $tokenData['token'];
    }
    
    /**
     * 生成 CSRF Token
     */
    public function generateToken(string $sessionId): string
    {
        $token = bin2hex(random_bytes(32));
        $expires = time() + $this->tokenLifetime;
        
        // 优先存储到 Redis
        if ($this->redis) {
            try {
                $this->redis->setex("csrf_token:{$sessionId}", $this->tokenLifetime, $token);
                return $token;
            } catch (\Exception $e) {
                // Redis 失败时使用文件存储
            }
        }
        
        // 文件存储（后备方案）
        $tokenFile = runtime_path('csrf_tokens/' . substr($sessionId, 0, 2) . '/' . $sessionId . '.token');
        $tokenDir = dirname($tokenFile);
        
        if (!is_dir($tokenDir)) {
            mkdir($tokenDir, 0700, true);
        }
        
        file_put_contents($tokenFile, json_encode([
            'token' => $token,
            'expires' => $expires,
        ]), LOCK_EX);
        chmod($tokenFile, 0600);
        
        return $token;
    }
    
    /**
     * 处理无效 Token
     */
    private function handleInvalidToken(Request $request): Response
    {
        // 如果是 AJAX 请求，返回 JSON
        if ($request->header('X-Requested-With') === 'XMLHttpRequest' || 
            $request->header('Accept') === 'application/json') {
            return new Response(403, [
                'Content-Type' => 'application/json'
            ], json_encode([
                'code' => 403,
                'msg' => 'CSRF token validation failed',
                'error' => 'Invalid or missing CSRF token'
            ]));
        }
        
        // 普通请求返回错误页面或重定向
        return new Response(403, [
            'Content-Type' => 'text/html'
        ], '<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body><h1>403 Forbidden</h1><p>CSRF token validation failed. Please refresh the page and try again.</p></body></html>');
    }
    
    /**
     * 获取 Redis 客户端
     */
    private function getRedisClient(): ?RedisClient
    {
        try {
            $config = $this->configManager->get('database.redis') ?? [];
            
            if (empty($config) || !($config['host'] ?? false)) {
                return null;
            }
            
            return new RedisClient([
                'scheme' => 'tcp',
                'host' => $config['host'] ?? '127.0.0.1',
                'port' => $config['port'] ?? 6379,
                'password' => $config['password'] ?? null,
                'database' => $config['database'] ?? 0,
            ]);
        } catch (\Exception $e) {
            return null;
        }
    }
}

