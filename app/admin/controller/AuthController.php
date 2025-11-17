<?php

namespace app\admin\controller;

use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;
use app\admin\middleware\CsrfMiddleware;
use app\admin\helpers\DatabaseHelper;
use app\admin\helpers\OfflineModeHelper;
use Predis\Client as RedisClient;

/**
 * 认证控制器
 * 
 * 处理用户登录、登出、会话管理等功能
 */
class AuthController
{
    private CsrfMiddleware $csrfMiddleware;
    private ?RedisClient $redis;
    
    public function __construct()
    {
        $this->csrfMiddleware = new CsrfMiddleware();
        $this->redis = $this->getRedisClient();
    }
    
    /**
     * 获取 Redis 客户端（用于登录失败计数）
     */
    private function getRedisClient(): ?RedisClient
    {
        try {
            $configManager = new \app\waf\config\ConfigManager();
            $config = $configManager->get('database.redis') ?? [];
            
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
    /**
     * 显示登录页面（修复：确保临时会话ID保存到Cookie，以便CSRF验证）
     */
    public function login(Request $request): Response
    {
        // 如果已经登录，重定向到仪表板
        if ($this->isLoggedIn($request)) {
            return new Response(302, ['Location' => '/admin/dashboard'], '');
        }

        // 获取或生成临时会话ID（用于CSRF Token）
        $sessionId = $request->cookie('waf_session');
        if (!$sessionId) {
            // 生成临时会话ID（基于IP、UA和时间戳，增加熵值）
            $ip = $this->getClientIp($request);
            $ua = $request->header('User-Agent', '');
            $timestamp = time();
            $random = bin2hex(random_bytes(16));
            $sessionId = hash('sha256', $ip . $ua . $timestamp . $random . 'csrf_temp');
            
            // 将临时会话ID保存到Cookie（修复：确保客户端有sessionId用于CSRF验证）
            $tempCookie = sprintf(
                'waf_session=%s; Path=/; HttpOnly; SameSite=Strict; Max-Age=3600',
                urlencode($sessionId)
            );
        } else {
            $tempCookie = null;
        }
        
        // 生成 CSRF Token
        $csrfToken = $this->csrfMiddleware->generateToken($sessionId);
        
        // 使用视图文件渲染登录页面
        $html = $this->renderLoginView($csrfToken);
        
        // 如果有临时Cookie，添加到响应头
        $headers = ['Content-Type' => 'text/html'];
        if ($tempCookie) {
            $headers['Set-Cookie'] = $tempCookie;
        }
        
        return new Response(200, $headers, $html);
    }

    /**
     * 处理登录请求（修复：添加登录失败限制和 CSRF 验证）
     */
    public function doLogin(Request $request): Response
    {
        // 0. 验证 CSRF Token（登录接口特殊处理）
        $csrfToken = $this->extractCsrfToken($request);
        $sessionId = $request->cookie('waf_session') ?? $this->getTempSessionId($request);
        if (!$this->csrfMiddleware->validateTokenForSession($sessionId, $csrfToken)) {
            return new Response(403, ['Content-Type' => 'application/json'], json_encode([
                'code' => 403,
                'msg' => 'CSRF token validation failed'
            ]));
        }
        
        // 1. 检查 IP 是否被临时封禁
        $clientIp = $this->getClientIp($request);
        if ($this->isIpBlocked($clientIp)) {
            return new Response(429, ['Content-Type' => 'application/json'], json_encode([
                'code' => 429,
                'msg' => '登录尝试过于频繁，请稍后再试'
            ]));
        }
        
        // 2. 解析POST数据
        parse_str($request->rawBody(), $postData);
        $username = $postData['username'] ?? '';
        $password = $postData['password'] ?? '';
        $remember = isset($postData['remember']) && $postData['remember'] === 'on';

        // 3. 记录登录尝试
        $this->recordLoginAttempt($clientIp, $username);

        // 4. 验证用户名和密码
        if ($this->validateCredentials($username, $password)) {
            // 成功：清除失败记录（包括IP级别的）
            $this->clearFailedAttempts($clientIp, $username);
            $this->clearIpFailedAttempts($clientIp);
            
            // 从数据库获取用户信息（用于更新登录信息）
            $user = DatabaseHelper::getUserByUsername($username);
            if ($user) {
                // 更新用户登录信息（使用 ORM）
                $user->updateLoginInfo($clientIp, $request->header('User-Agent', ''));
            }
            
            // 创建会话
            $sessionId = $this->createSession($request, $username, $remember);
            
            // 设置Cookie头（修复：添加 Secure 和 SameSite 标志）
            $expires = $remember ? time() + (30 * 24 * 3600) : time() + (24 * 3600);
            
            // 根据环境决定是否使用 Secure（生产环境 HTTPS 时使用）
            $isSecure = (env('APP_ENV', 'development') === 'production') || 
                        (env('FORCE_HTTPS', false) === true);
            $secureFlag = $isSecure ? '; Secure' : '';
            
            // URL 编码会话 ID，添加 SameSite 防止 CSRF
            $cookieValue = sprintf(
                'waf_session=%s; Path=/; HttpOnly%s; SameSite=Strict; Max-Age=%d',
                urlencode($sessionId),
                $secureFlag,
                $expires - time()
            );
            
            return new Response(200, [
                'Content-Type' => 'application/json',
                'Set-Cookie' => $cookieValue
            ], json_encode([
                'code' => 0,
                'msg' => '登录成功',
                'data' => ['redirect' => '/admin/dashboard']
            ]));
        } else {
            // 失败：增加失败计数（包括IP级别的全局计数）
            $failCount = $this->incrementFailedAttempts($clientIp, $username);
            $ipFailCount = $this->incrementIpFailedAttempts($clientIp);
            
            // 从数据库获取用户信息（用于更新失败登录次数）
            $user = DatabaseHelper::getUserByUsername($username);
            if ($user) {
                // 增加失败登录次数（使用 ORM）
                $user->incrementFailedLoginCount();
            }
            
            // IP级别的全局失败计数（防止通过更换用户名绕过）
            if ($ipFailCount >= 10) {
                $this->blockIp($clientIp, 3600); // 封禁1小时
                return new Response(429, ['Content-Type' => 'application/json'], json_encode([
                    'code' => 429,
                    'msg' => '登录失败次数过多，IP已被临时封禁'
                ]));
            }
            
            // 单个用户名的失败计数
            if ($failCount >= 5) {
                $this->blockIp($clientIp, 3600); // 封禁1小时
                return new Response(429, ['Content-Type' => 'application/json'], json_encode([
                    'code' => 429,
                    'msg' => '登录失败次数过多，IP已被临时封禁'
                ]));
            }
            
            // 计算剩余尝试次数（取较小值）
            $remainingAttempts = min(5 - $failCount, 10 - $ipFailCount);
            
            return new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'code' => 1,
                'msg' => "用户名或密码错误（还剩 {$remainingAttempts} 次尝试）"
            ]));
        }
    }
    
    /**
     * 从请求中提取 CSRF Token
     */
    private function extractCsrfToken(Request $request): ?string
    {
        // 优先从 Header 获取（AJAX 请求）
        $headerToken = $request->header('X-CSRF-Token');
        if ($headerToken) {
            return $headerToken;
        }
        
        // 从 POST 数据获取（表单提交）
        parse_str($request->rawBody(), $postData);
        return $postData['_token'] ?? $postData['csrf_token'] ?? null;
    }
    
    /**
     * 获取临时会话 ID（用于未登录用户生成 CSRF Token）
     * 修复：增加熵值，防止同一网络下用户共享临时会话ID
     */
    private function getTempSessionId(Request $request): string
    {
        // 尝试从 Cookie 获取（应该已经由 login() 方法设置）
        $sessionId = $request->cookie('waf_session');
        if ($sessionId) {
            return $sessionId;
        }
        
        // 如果没有（不应该发生，但作为后备方案），基于 IP、UA、时间戳和随机数生成
        // 注意：这种情况下生成的ID可能与 login() 中生成的不一致，可能导致CSRF验证失败
        // 但这是后备方案，正常情况下应该不会执行到这里
        $ip = $this->getClientIp($request);
        $ua = $request->header('User-Agent', '');
        $timestamp = time();
        $random = bin2hex(random_bytes(16));
        return hash('sha256', $ip . $ua . $timestamp . $random . 'csrf_temp');
    }

    /**
     * 处理登出请求
     */
    public function logout(Request $request): Response
    {
        $this->destroySession($request);
        
        // 清除Cookie
        $clearCookie = "waf_session=; Path=/; HttpOnly; Max-Age=0";
        
        return new Response(302, [
            'Location' => '/admin/login',
            'Set-Cookie' => $clearCookie
        ], '');
    }

    /**
     * 检查是否已登录
     */
    public function isLoggedIn(Request $request): bool
    {
        $sessionId = $request->cookie('waf_session');
        if (!$sessionId) {
            return false;
        }

        // 检查会话是否有效
        $sessionData = $this->getSessionData($sessionId);
        return $sessionData !== null && $sessionData['expires'] > time();
    }

    /**
     * 验证用户凭据（修复：从数据库读取用户信息）
     */
    private function validateCredentials(string $username, string $password): bool
    {
        // 输入验证：检查用户名和密码是否为空
        if (empty($username) || empty($password)) {
            return false;
        }
        
        // 输入验证：长度限制
        if (strlen($username) > 50 || strlen($password) > 128) {
            return false;
        }
        
        // 输入验证：格式验证（只允许字母、数字、下划线、短横线）
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $username)) {
            return false;
        }
        
        // 尝试从数据库读取用户信息（使用 ORM）
        $user = DatabaseHelper::getUserByUsername($username);

        if ($user) {
            // 检查用户是否被锁定
            if ($user->isLocked()) {
            return false;
        }

            // 验证密码（使用 Model 方法）
            if ($user->verifyPassword($password)) {
                return true;
            }
        } else {
            // 数据库不可用时的后备方案：使用离线模式的硬编码账户
            // 注意：生产环境应该配置数据库，不要依赖硬编码账户
            $offlineUser = OfflineModeHelper::validateOfflineUser($username, $password);
            return $offlineUser !== null;
        }
        
        return false;
    }

    /**
     * 创建用户会话
     */
    private function createSession(Request $request, string $username, bool $remember = false): string
    {
        // 1. 销毁所有旧会话（防止会话固定攻击）
        $this->destroyAllUserSessions($username);
        
        // 2. 生成新会话ID
        $sessionId = $this->generateSessionId();
        $expires = $remember ? time() + (30 * 24 * 3600) : time() + (24 * 3600); // 30天或1天

        $sessionData = [
            'username' => $username,
            'login_time' => time(),
            'expires' => $expires,
            'ip' => $this->getClientIp($request),
            'user_agent' => $request->header('User-Agent', '')
        ];

        $this->saveSessionData($sessionId, $sessionData);

        // 在 Workerman 环境中，Cookie 通过 Response 头设置
        // 这里只是保存会话数据，Cookie 会在响应中设置
        
        return $sessionId;
    }
    
    /**
     * 销毁用户的所有会话（防止会话固定攻击）
     */
    private function destroyAllUserSessions(string $username): void
    {
        $sessionDir = runtime_path('sessions');
        if (!is_dir($sessionDir)) {
            return;
        }
        
        // 遍历所有会话文件
        foreach (glob($sessionDir . '/*/*.json') as $file) {
            if (!is_file($file)) {
                continue;
            }
            
            $content = file_get_contents($file);
            if ($content === false) {
                continue;
            }
            
            $data = json_decode($content, true);
            
            // 如果会话属于该用户，删除它
            if (is_array($data) && isset($data['username']) && $data['username'] === $username) {
                unlink($file);
            }
        }
    }

    /**
     * 销毁用户会话
     */
    private function destroySession(Request $request): void
    {
        $sessionId = $request->cookie('waf_session');
        if ($sessionId) {
            $this->deleteSessionData($sessionId);
        }

        // Cookie 清除通过 Response 头处理
    }

    /**
     * 生成会话ID
     */
    private function generateSessionId(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * 获取会话数据（修复：加强JSON错误处理和数据结构验证）
     */
    private function getSessionData(string $sessionId): ?array
    {
        $sessionFile = $this->getSessionFilePath($sessionId);
        if (!file_exists($sessionFile)) {
            return null;
        }

        $content = file_get_contents($sessionFile);
        if ($content === false) {
            return null;
        }
        
        $data = json_decode($content, true);
        
        // 验证JSON解码结果和JSON错误
        if (json_last_error() !== JSON_ERROR_NONE) {
            // 文件可能被篡改，删除它
            @unlink($sessionFile);
            return null;
        }
        
        // 验证数据结构完整性
        if (!is_array($data) || 
            !isset($data['username'], $data['login_time'], $data['expires'])) {
            // 数据不完整，删除文件
            @unlink($sessionFile);
            return null;
        }
        
        // 验证过期时间
        if ($data['expires'] < time()) {
            @unlink($sessionFile);
            return null;
        }

        return $data;
    }

    /**
     * 保存会话数据（修复：加强文件权限和JSON错误处理）
     */
    private function saveSessionData(string $sessionId, array $data): void
    {
        $sessionFile = $this->getSessionFilePath($sessionId);
        $sessionDir = dirname($sessionFile);
        
        if (!is_dir($sessionDir)) {
            mkdir($sessionDir, 0700, true); // 更严格的目录权限
        }

        // 编码JSON并验证
        $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE);
        if ($jsonData === false || json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Failed to encode session data: ' . json_last_error_msg());
        }
        
        // 写入文件并设置严格的文件权限（只有所有者可读写）
        file_put_contents($sessionFile, $jsonData, LOCK_EX);
        chmod($sessionFile, 0600);
    }

    /**
     * 删除会话数据
     */
    private function deleteSessionData(string $sessionId): void
    {
        $sessionFile = $this->getSessionFilePath($sessionId);
        if (file_exists($sessionFile)) {
            unlink($sessionFile);
        }
    }

    /**
     * 获取会话文件路径（修复：防止路径遍历攻击）
     */
    private function getSessionFilePath(string $sessionId): string
    {
        // 1. 验证会话ID格式（只允许十六进制字符，64字符长度）
        if (!preg_match('/^[a-f0-9]{64}$/i', $sessionId)) {
            throw new \InvalidArgumentException('Invalid session ID format');
        }
        
        // 2. 获取基础目录（使用 realpath 确保路径安全）
        $baseDir = realpath(runtime_path('sessions'));
        if ($baseDir === false) {
            // 如果目录不存在，创建它
            $baseDir = runtime_path('sessions');
            if (!is_dir($baseDir)) {
                mkdir($baseDir, 0700, true);
            }
            $baseDir = realpath($baseDir);
            if ($baseDir === false) {
                throw new \RuntimeException('Cannot create sessions directory');
            }
        }
        
        // 3. 构建目标路径
        $prefix = substr($sessionId, 0, 2);
        $filename = basename($sessionId . '.json'); // 使用 basename 防止路径遍历
        
        $targetDir = $baseDir . '/' . $prefix;
        $fullPath = $targetDir . '/' . $filename;
        
        // 4. 确保目标目录存在
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0700, true);
        }
        
        // 5. 验证最终路径在预期目录内（防止路径遍历）
        $realFullPath = realpath($fullPath);
        if ($realFullPath !== false) {
            // 如果文件已存在，验证它在正确目录
            if (strpos($realFullPath, $baseDir) !== 0) {
                throw new \InvalidArgumentException('Path traversal detected in session file path');
            }
            return $realFullPath;
        }
        
        // 如果文件不存在，验证将要创建的路径
        $realTargetDir = realpath($targetDir);
        if ($realTargetDir === false || strpos($realTargetDir, $baseDir) !== 0) {
            throw new \InvalidArgumentException('Path traversal detected in session directory');
        }
        
        return $realTargetDir . '/' . $filename;
    }

    /**
     * 获取客户端IP地址（修复：加强验证，防止 IP 伪造）
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
        $configManager = new \app\waf\config\ConfigManager();
        $trustedProxies = $configManager->get('waf.security.trusted_proxies') ?? ['127.0.0.1', '::1'];
        
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
            
            // 验证 IP 格式
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
     * 登录失败限制相关方法
     */
    private function isIpBlocked(string $ip): bool
    {
        if (!$this->redis) {
            return $this->isIpBlockedFile($ip);
        }
        
        try {
            $blockedUntil = $this->redis->get("ip_blocked:{$ip}");
            if ($blockedUntil && $blockedUntil > time()) {
                return true;
            }
        } catch (\Exception $e) {
            return $this->isIpBlockedFile($ip);
        }
        
        return false;
    }
    
    private function isIpBlockedFile(string $ip): bool
    {
        $blockFile = runtime_path("ip_blocks/{$ip}.json");
        if (!file_exists($blockFile)) {
            return false;
        }
        
        $data = @json_decode(file_get_contents($blockFile), true);
        if ($data && isset($data['blocked_until']) && $data['blocked_until'] > time()) {
            return true;
        }
        
        @unlink($blockFile);
        return false;
    }
    
    private function blockIp(string $ip, int $duration): void
    {
        $blockedUntil = time() + $duration;
        
        if ($this->redis) {
            try {
                $this->redis->setex("ip_blocked:{$ip}", $duration, $blockedUntil);
                return;
            } catch (\Exception $e) {
                // Redis 失败时使用文件存储
            }
        }
        
        // 文件存储
        $blockFile = runtime_path("ip_blocks/{$ip}.json");
        $blockDir = dirname($blockFile);
        if (!is_dir($blockDir)) {
            mkdir($blockDir, 0700, true);
        }
        
        file_put_contents($blockFile, json_encode([
            'ip' => $ip,
            'blocked_until' => $blockedUntil,
            'blocked_at' => time(),
        ]), LOCK_EX);
        chmod($blockFile, 0600);
    }
    
    private function recordLoginAttempt(string $ip, string $username): void
    {
        // 记录登录尝试（用于审计）
        $key = "login_attempt:{$ip}:{$username}";
        $attempt = [
            'ip' => $ip,
            'username' => $username,
            'timestamp' => time(),
        ];
        
        if ($this->redis) {
            try {
                $this->redis->lpush("login_attempts", json_encode($attempt));
                $this->redis->ltrim("login_attempts", 0, 999); // 只保留最近1000条
                return;
            } catch (\Exception $e) {
                // Redis 失败时使用文件存储
            }
        }
        
        // 文件存储
        $logFile = runtime_path("logs/login_attempts.log");
        file_put_contents($logFile, json_encode($attempt) . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
    
    private function incrementFailedAttempts(string $ip, string $username): int
    {
        $key = "failed_attempts:{$ip}:{$username}";
        
        if ($this->redis) {
            try {
                $count = $this->redis->incr($key);
                $this->redis->expire($key, 3600); // 1小时过期
                return $count;
            } catch (\Exception $e) {
                // Redis 失败时使用文件存储
            }
        }
        
        // 文件存储
        $failFile = runtime_path("failed_logins/{$ip}_{$username}.txt");
        $failDir = dirname($failFile);
        if (!is_dir($failDir)) {
            mkdir($failDir, 0700, true);
        }
        
        $count = 1;
        if (file_exists($failFile)) {
            $count = (int)file_get_contents($failFile) + 1;
        }
        
        file_put_contents($failFile, (string)$count, LOCK_EX);
        
        // 设置过期时间（1小时后删除）
        touch($failFile, time() + 3600);
        
        return $count;
    }
    
    private function clearFailedAttempts(string $ip, string $username): void
    {
        $key = "failed_attempts:{$ip}:{$username}";
        
        if ($this->redis) {
            try {
                $this->redis->del($key);
                return;
            } catch (\Exception $e) {
                // Redis 失败时使用文件存储
            }
        }
        
        // 文件存储
        $failFile = runtime_path("failed_logins/{$ip}_{$username}.txt");
        if (file_exists($failFile)) {
            @unlink($failFile);
        }
    }
    
    /**
     * 增加IP级别的全局失败计数（修复：防止通过更换用户名绕过封禁）
     */
    private function incrementIpFailedAttempts(string $ip): int
    {
        $key = "failed_attempts_ip:{$ip}";
        
        if ($this->redis) {
            try {
                $count = $this->redis->incr($key);
                $this->redis->expire($key, 3600); // 1小时过期
                return $count;
            } catch (\Exception $e) {
                // Redis 失败时使用文件存储
            }
        }
        
        // 文件存储
        $failFile = runtime_path("failed_logins/ip_{$ip}.txt");
        $failDir = dirname($failFile);
        if (!is_dir($failDir)) {
            mkdir($failDir, 0700, true);
        }
        
        $count = 1;
        if (file_exists($failFile)) {
            $count = (int)file_get_contents($failFile) + 1;
        }
        
        file_put_contents($failFile, (string)$count, LOCK_EX);
        
        // 设置过期时间（1小时后删除）
        touch($failFile, time() + 3600);
        
        return $count;
    }
    
    /**
     * 清除IP级别的全局失败计数
     */
    private function clearIpFailedAttempts(string $ip): void
    {
        $key = "failed_attempts_ip:{$ip}";
        
        if ($this->redis) {
            try {
                $this->redis->del($key);
                return;
            } catch (\Exception $e) {
                // Redis 失败时使用文件存储
            }
        }
        
        // 文件存储
        $failFile = runtime_path("failed_logins/ip_{$ip}.txt");
        if (file_exists($failFile)) {
            @unlink($failFile);
        }
    }

    /**
     * 渲染登录视图
     */
    private function renderLoginView(string $csrfToken = ''): string
    {
        $viewFile = __DIR__ . '/../view/auth/login.php';
        if (!file_exists($viewFile)) {
            // 如果视图文件不存在，使用旧的内联HTML
            return $this->generateLoginPage($csrfToken);
        }
        
        ob_start();
        extract(['csrfToken' => $csrfToken]);
        include $viewFile;
        return ob_get_clean();
    }
    
    /**
     * 生成登录页面HTML（备用方案，已弃用）
     * @deprecated 使用 renderLoginView 代替
     */
    private function generateLoginPage(string $csrfToken = ''): string
    {
        return '<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>天罡 WAF - 管理登录</title>
    <link href="//unpkg.com/layui@2.12.1/dist/css/layui.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            width: 100%;
            max-width: 400px;
            position: relative;
            overflow: hidden;
        }
        
        .login-container::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2);
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-logo {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
            font-weight: bold;
        }
        
        .login-title {
            font-size: 24px;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 8px;
        }
        
        .login-subtitle {
            font-size: 14px;
            color: #718096;
        }
        
        .login-form {
            margin-top: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: #2d3748;
            margin-bottom: 8px;
        }
        
        .form-input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: #f8fafc;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-checkbox {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .form-checkbox input {
            margin-right: 8px;
        }
        
        .form-checkbox label {
            font-size: 14px;
            color: #4a5568;
            cursor: pointer;
        }
        
        .login-button {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .login-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .login-button:active {
            transform: translateY(0);
        }
        
        .login-button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .login-footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }
        
        .login-footer p {
            font-size: 12px;
            color: #718096;
        }
        
        .demo-accounts {
            background: #f7fafc;
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
            font-size: 12px;
        }
        
        .demo-accounts h4 {
            color: #2d3748;
            margin-bottom: 10px;
            font-size: 14px;
        }
        
        .demo-accounts p {
            color: #4a5568;
            margin: 5px 0;
        }
        
        .loading {
            display: none;
            text-align: center;
            margin-top: 20px;
        }
        
        .error-message {
            background: #fed7d7;
            color: #c53030;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            display: none;
        }
        
        .success-message {
            background: #c6f6d5;
            color: #2f855a;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            display: none;
        }
        
        @media (max-width: 480px) {
            .login-container {
                margin: 20px;
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="login-logo">🛡️</div>
            <h1 class="login-title">天罡 WAF</h1>
            <p class="login-subtitle">Web应用防火墙管理控制台</p>
        </div>
        
        <div class="error-message" id="errorMessage"></div>
        <div class="success-message" id="successMessage"></div>
        
        <form class="login-form" id="loginForm">
            <input type="hidden" name="_token" value="' . htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') . '" id="csrfToken">
            <div class="form-group">
                <label class="form-label" for="username">用户名</label>
                <input 
                    type="text" 
                    id="username" 
                    name="username" 
                    class="form-input" 
                    placeholder="请输入用户名"
                    required
                    autocomplete="username"
                >
            </div>
            
            <div class="form-group">
                <label class="form-label" for="password">密码</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    class="form-input" 
                    placeholder="请输入密码"
                    required
                    autocomplete="current-password"
                >
            </div>
            
            <div class="form-checkbox">
                <input type="checkbox" id="remember" name="remember">
                <label for="remember">记住我</label>
            </div>
            
            <button type="submit" class="login-button" id="loginButton">
                登录
            </button>
        </form>
        
        <div class="loading" id="loading">
            <i class="layui-icon layui-icon-loading layui-anim layui-anim-rotate layui-anim-loop"></i>
            <span style="margin-left: 10px;">正在登录...</span>
        </div>
        
        <!-- 默认账户提示已移除，生产环境不应显示 -->
        
        <div class="login-footer">
            <p>© 2024 天罡 WAF. 专业Web应用防火墙</p>
        </div>
    </div>

    <script src="//unpkg.com/layui@2.12.1/dist/layui.js"></script>
    <script>
        layui.use([\'layer\', \'form\'], function(){
            var layer = layui.layer;
            var form = layui.form;
            
            const loginForm = document.getElementById(\'loginForm\');
            const loginButton = document.getElementById(\'loginButton\');
            const loading = document.getElementById(\'loading\');
            const errorMessage = document.getElementById(\'errorMessage\');
            const successMessage = document.getElementById(\'successMessage\');
            
            loginForm.addEventListener(\'submit\', async function(e) {
                e.preventDefault();
                
                const formData = new FormData(loginForm);
                const username = formData.get(\'username\');
                const password = formData.get(\'password\');
                const remember = formData.get(\'remember\') === \'on\';
                
                // 显示加载状态
                loginButton.disabled = true;
                loading.style.display = \'block\';
                errorMessage.style.display = \'none\';
                successMessage.style.display = \'none\';
                
                try {
                    const csrfToken = document.getElementById(\'csrfToken\').value;
                    const response = await fetch(\'/admin/auth/login\', {
                        method: \'POST\',
                        headers: {
                            \'Content-Type\': \'application/x-www-form-urlencoded\',
                            \'X-CSRF-Token\': csrfToken,
                        },
                        body: `username=${encodeURIComponent(username)}&password=${encodeURIComponent(password)}&remember=${remember}&_token=${encodeURIComponent(csrfToken)}`
                    });
                    
                    const result = await response.json();
                    
                    if (result.code === 0) {
                        successMessage.textContent = result.msg;
                        successMessage.style.display = \'block\';
                        
                        // 延迟跳转
                        setTimeout(() => {
                            window.location.href = result.data.redirect;
                        }, 1000);
                    } else {
                        errorMessage.textContent = result.msg;
                        errorMessage.style.display = \'block\';
                    }
                } catch (error) {
                    console.error(\'登录错误:\', error);
                    errorMessage.textContent = \'网络错误，请稍后重试\';
                    errorMessage.style.display = \'block\';
                } finally {
                    loginButton.disabled = false;
                    loading.style.display = \'none\';
                }
            });
            
            // 回车键登录
            document.addEventListener(\'keypress\', function(e) {
                if (e.key === \'Enter\') {
                    loginForm.dispatchEvent(new Event(\'submit\'));
                }
            });
        });
    </script>
</body>
</html>';
    }
}
