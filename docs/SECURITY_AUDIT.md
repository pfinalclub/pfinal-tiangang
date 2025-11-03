# 天罡 WAF 安全审查报告

**审查日期**: 2025-01-17  
**审查人员**: 资深安全工程师  
**审查范围**: 代码安全、架构安全、配置安全

---

## 📋 执行摘要

本报告对天罡 WAF 项目进行了全面的安全审查，发现了 **15 个高/中风险安全问题**，并提供了详细的修复建议。项目整体架构良好，但在认证授权、输入验证、会话管理等方面存在安全隐患。

### 风险等级分布
- 🔴 **高危风险**: 5 项
- 🟡 **中危风险**: 8 项
- 🟢 **低危风险**: 2 项

---

## 🔴 高危安全问题

### 1. 硬编码默认密码（高危）

**位置**: `app/admin/controller/AuthController.php:99-106`

**问题描述**:
```php
private function validateCredentials(string $username, string $password): bool
{
    // 默认管理员账户（生产环境应该从数据库验证）
    $validUsers = [
        'admin' => password_hash('admin123', PASSWORD_DEFAULT),
        'waf' => password_hash('waf2024', PASSWORD_DEFAULT),
        'tiangang' => password_hash('tiangang2024', PASSWORD_DEFAULT)
    ];
```

**风险**:
- ❌ 默认密码硬编码在代码中，容易被逆向工程
- ❌ 登录页面公开显示默认账户（`AuthController.php:519-523`）
- ❌ `password_hash()` 在构造函数中每次执行，导致密码校验失败

**影响**: 攻击者可以使用默认凭据直接登录系统

**修复建议**:
1. 移除硬编码密码，强制使用数据库或配置文件（但不在代码中）
2. 首次登录强制修改密码
3. 实现账户锁定机制（连续失败N次锁定）
4. 从登录页面移除默认账户提示

```php
// 修复示例
private function validateCredentials(string $username, string $password): bool
{
    // 从数据库或加密配置文件读取
    $user = $this->getUserFromDatabase($username);
    if (!$user) {
        $this->logFailedLogin($username); // 记录失败尝试
        return false;
    }
    
    // 检查账户是否被锁定
    if ($user['locked_until'] > time()) {
        return false;
    }
    
    $valid = password_verify($password, $user['password_hash']);
    
    if (!$valid) {
        $this->logFailedLogin($username);
        // 失败次数过多则锁定账户
        if ($user['failed_attempts'] >= 5) {
            $this->lockAccount($username, 3600); // 锁定1小时
        }
    } else {
        $this->resetFailedAttempts($username);
    }
    
    return $valid;
}
```

---

### 2. 密码哈希每次重新生成（高危）

**位置**: `app/admin/controller/AuthController.php:103-105`

**问题描述**:
```php
$validUsers = [
    'admin' => password_hash('admin123', PASSWORD_DEFAULT),
    // ...
];
```

**风险**:
- ❌ `password_hash()` 每次调用都生成新的哈希值，导致 `password_verify()` 永远失败
- ❌ 实际上无法登录系统

**影响**: 登录功能实际上无法正常工作

**修复建议**:
```php
// 使用预生成的哈希值
$validUsers = [
    'admin' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // admin123
    'waf' => '$2y$10$...', // waf2024
    'tiangang' => '$2y$10$...', // tiangang2024
];
```

或使用配置文件（但不要提交到 Git）:
```php
// config/admin.php (不提交到版本控制)
return [
    'users' => [
        'admin' => '$2y$10$...',
        // ...
    ],
];
```

---

### 3. 会话固定攻击（高危）

**位置**: `app/admin/controller/AuthController.php:118-136`

**问题描述**:
```php
private function createSession(Request $request, string $username, bool $remember = false): string
{
    $sessionId = $this->generateSessionId();
    // ...
}
```

**风险**:
- ❌ 登录成功后未销毁旧会话
- ❌ 攻击者可以固定会话ID，然后等待用户登录

**影响**: 攻击者可以劫持用户会话

**修复建议**:
```php
private function createSession(Request $request, string $username, bool $remember = false): string
{
    // 1. 销毁所有旧会话（防止会话固定）
    $this->destroyAllUserSessions($username);
    
    // 2. 生成新会话ID
    $sessionId = $this->generateSessionId();
    
    // 3. 设置会话数据
    // ...
    
    return $sessionId;
}

private function destroyAllUserSessions(string $username): void
{
    // 查找该用户的所有会话并删除
    $sessionDir = runtime_path('sessions');
    foreach (glob($sessionDir . '/*/*.json') as $file) {
        $data = json_decode(file_get_contents($file), true);
        if ($data['username'] === $username) {
            unlink($file);
        }
    }
}
```

---

### 4. Cookie 安全性不足（高危）

**位置**: `app/admin/controller/AuthController.php:47`

**问题描述**:
```php
$cookieValue = "waf_session={$sessionId}; Path=/; HttpOnly; Max-Age=" . ($expires - time());
```

**风险**:
- ❌ 缺少 `Secure` 标志（生产环境 HTTPS 时必须）
- ❌ 缺少 `SameSite` 属性（防 CSRF）
- ❌ Cookie 值未进行 URL 编码

**影响**: 
- HTTP 传输时可能被中间人攻击
- 易受 CSRF 攻击
- Cookie 解析可能出错

**修复建议**:
```php
$cookieValue = sprintf(
    'waf_session=%s; Path=/; HttpOnly; Secure; SameSite=Strict; Max-Age=%d',
    urlencode($sessionId),
    $expires - time()
);
```

并在环境配置中根据协议动态设置：
```php
$secure = ($_ENV['APP_ENV'] ?? 'production') === 'production';
$cookieValue = sprintf(
    'waf_session=%s; Path=/; HttpOnly%s; SameSite=Strict; Max-Age=%d',
    urlencode($sessionId),
    $secure ? '; Secure' : '',
    $expires - time()
);
```

---

### 5. 路径遍历风险（高危）

**位置**: `app/admin/controller/AuthController.php:210`

**问题描述**:
```php
private function getSessionFilePath(string $sessionId): string
{
    return runtime_path('sessions/' . substr($sessionId, 0, 2) . '/' . $sessionId . '.json');
}
```

**风险**:
- ❌ 如果 `$sessionId` 被污染（例如 `../../../etc/passwd`），可能造成路径遍历
- ❌ 虽然使用了 `substr()`，但如果会话ID验证不足，仍可能有问题

**影响**: 可能导致任意文件读取/写入

**修复建议**:
```php
private function getSessionFilePath(string $sessionId): string
{
    // 1. 验证会话ID格式（只允许十六进制字符）
    if (!preg_match('/^[a-f0-9]{64}$/i', $sessionId)) {
        throw new \InvalidArgumentException('Invalid session ID format');
    }
    
    // 2. 使用 basename 防止路径遍历
    $prefix = substr($sessionId, 0, 2);
    $filename = basename($sessionId . '.json');
    
    // 3. 使用 realpath 确保在正确目录
    $baseDir = realpath(runtime_path('sessions'));
    $targetDir = $baseDir . '/' . $prefix;
    
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }
    
    $fullPath = $targetDir . '/' . $filename;
    
    // 4. 验证最终路径在预期目录内（防止路径遍历）
    if (strpos(realpath($fullPath), $baseDir) !== 0) {
        throw new \SecurityException('Path traversal detected');
    }
    
    return $fullPath;
}
```

---

## 🟡 中危安全问题

### 6. 缺少 CSRF 保护（中危）

**位置**: `app/admin/controller/AuthController.php`, `app/admin/routes/AdminRoutes.php`

**问题描述**:
- ❌ 所有 POST 请求（登录、规则修改等）都没有 CSRF Token 验证
- ❌ 缺少 CSRF Token 生成和验证机制

**影响**: 易受跨站请求伪造攻击

**修复建议**:
1. 实现 CSRF Token 生成和验证中间件
2. 在所有表单中添加 CSRF Token
3. 在 AJAX 请求中添加 CSRF Header

```php
// 新增 CSRF 中间件
class CsrfMiddleware
{
    public function process(Request $request, callable $next): Response
    {
        // 跳过 GET、HEAD、OPTIONS 请求
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'])) {
            return $next($request);
        }
        
        // 验证 CSRF Token
        $token = $request->header('X-CSRF-Token') 
              ?? $request->post('_token') 
              ?? '';
              
        if (!$this->validateToken($request, $token)) {
            return new Response(403, [
                'Content-Type' => 'application/json'
            ], json_encode([
                'error' => 'CSRF token validation failed'
            ]));
        }
        
        return $next($request);
    }
}
```

---

### 7. IP 地址伪造风险（中危）

**位置**: `app/admin/controller/AuthController.php:216-239`

**问题描述**:
```php
private function getClientIp(Request $request): string
{
    $headers = [
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_REAL_IP',
        // ...
    ];
    
    foreach ($headers as $header) {
        $ip = $request->header($header);
        if ($ip && filter_var($ip, FILTER_VALIDATE_IP, ...)) {
            return $ip;
        }
    }
}
```

**风险**:
- ❌ 信任了客户端可伪造的 HTTP 头（`X-Forwarded-For` 等）
- ❌ 未验证代理链的真实性
- ❌ 日志记录和会话绑定可能使用伪造的 IP

**影响**: 可能绕过 IP 白名单/黑名单，日志记录不准确

**修复建议**:
```php
private function getClientIp(Request $request): string
{
    // 1. 如果配置了可信代理，才信任代理头
    $trustedProxies = config('app.trusted_proxies', []);
    $remoteIp = $request->connection->getRemoteIp();
    
    if (!in_array($remoteIp, $trustedProxies)) {
        // 不是可信代理，直接返回连接IP
        return $remoteIp ?? '127.0.0.1';
    }
    
    // 2. 只信任最后一个代理（最靠近客户端的）
    $forwardedFor = $request->header('X-Forwarded-For');
    if ($forwardedFor) {
        $ips = explode(',', $forwardedFor);
        $ip = trim(end($ips)); // 取最后一个
        
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return $ip;
        }
    }
    
    // 3. 回退到连接IP
    return $remoteIp ?? '127.0.0.1';
}
```

---

### 8. 输入验证不足（中危）

**位置**: 多处（`AuthController`, `AdminRoutes`, `ProxyHandler`）

**问题描述**:
- ❌ 用户名/密码长度未限制
- ❌ 未对输入进行严格的格式验证
- ❌ 特殊字符可能造成注入

**修复建议**:
```php
private function validateCredentials(string $username, string $password): bool
{
    // 1. 输入验证
    if (empty($username) || empty($password)) {
        return false;
    }
    
    // 2. 长度限制
    if (strlen($username) > 50 || strlen($password) > 128) {
        return false;
    }
    
    // 3. 格式验证（只允许字母、数字、下划线、短横线）
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $username)) {
        return false;
    }
    
    // 4. 防止时间攻击（固定时间比较）
    return $this->securePasswordVerify($username, $password);
}

private function securePasswordVerify(string $username, string $password): bool
{
    $user = $this->getUserFromDatabase($username);
    
    if (!$user) {
        // 即使用户不存在，也要执行哈希验证以保持时间一致
        password_verify($password, '$2y$10$dummy_hash_for_timing_attack_prevention');
        return false;
    }
    
    return password_verify($password, $user['password_hash']);
}
```

---

### 9. JSON 注入风险（中危）

**位置**: `app/admin/controller/AuthController.php:170, 191`

**问题描述**:
```php
$data = json_decode(file_get_contents($sessionFile), true);
file_put_contents($sessionFile, json_encode($data));
```

**风险**:
- ❌ JSON 解码失败时未处理，可能导致错误或安全问题
- ❌ 未验证 JSON 结构完整性

**修复建议**:
```php
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
    
    // 验证 JSON 解码结果和 JSON 错误
    if (json_last_error() !== JSON_ERROR_NONE) {
        // 文件可能被篡改，删除它
        unlink($sessionFile);
        return null;
    }
    
    // 验证数据结构
    if (!is_array($data) || 
        !isset($data['username'], $data['login_time'], $data['expires'])) {
        unlink($sessionFile);
        return null;
    }
    
    if ($data['expires'] < time()) {
        unlink($sessionFile);
        return null;
    }

    return $data;
}
```

---

### 10. 会话文件权限问题（中危）

**位置**: `app/admin/controller/AuthController.php:182-192`

**问题描述**:
```php
if (!is_dir($sessionDir)) {
    mkdir($sessionDir, 0755, true);
}

file_put_contents($sessionFile, json_encode($data));
```

**风险**:
- ❌ 文件权限 0755 对目录来说可能过于宽松
- ❌ 未设置文件权限，可能被其他用户读取
- ❌ 会话文件可能包含敏感信息

**修复建议**:
```php
private function saveSessionData(string $sessionId, array $data): void
{
    $sessionFile = $this->getSessionFilePath($sessionId);
    $sessionDir = dirname($sessionFile);
    
    if (!is_dir($sessionDir)) {
        mkdir($sessionDir, 0700, true); // 更严格的目录权限
    }
    
    // 写入文件并设置权限
    file_put_contents($sessionFile, json_encode($data), LOCK_EX);
    chmod($sessionFile, 0600); // 只有所有者可读写
    
    // 可选：加密会话数据
    $encrypted = $this->encryptSessionData($data);
    file_put_contents($sessionFile, $encrypted, LOCK_EX);
}

private function encryptSessionData(array $data): string
{
    $key = config('app.session_encryption_key');
    $iv = random_bytes(16);
    $encrypted = openssl_encrypt(
        json_encode($data),
        'AES-256-CBC',
        $key,
        0,
        $iv
    );
    return base64_encode($iv . $encrypted);
}
```

---

### 11. 缺少登录失败限制（中危）

**位置**: `app/admin/controller/AuthController.php:32-63`

**问题描述**:
- ❌ 没有记录登录失败次数
- ❌ 没有账户锁定机制
- ❌ 没有验证码或人机验证

**影响**: 易受暴力破解攻击

**修复建议**:
```php
public function doLogin(Request $request): Response
{
    // 1. 检查 IP 是否被临时封禁
    $clientIp = $this->getClientIp($request);
    if ($this->isIpBlocked($clientIp)) {
        return new Response(429, ['Content-Type' => 'application/json'], json_encode([
            'code' => 1,
            'msg' => '登录尝试过于频繁，请稍后再试'
        ]));
    }
    
    // 2. 解析并验证输入
    parse_str($request->rawBody(), $postData);
    $username = $postData['username'] ?? '';
    $password = $postData['password'] ?? '';
    
    // 3. 记录登录尝试
    $this->recordLoginAttempt($clientIp, $username);
    
    // 4. 验证凭据
    if ($this->validateCredentials($username, $password)) {
        // 成功：清除失败记录
        $this->clearFailedAttempts($clientIp, $username);
        // ... 创建会话
    } else {
        // 失败：增加失败计数
        $failCount = $this->incrementFailedAttempts($clientIp, $username);
        
        // 超过阈值则封禁 IP
        if ($failCount >= 5) {
            $this->blockIp($clientIp, 3600); // 封禁1小时
        }
        
        return new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'code' => 1,
            'msg' => '用户名或密码错误'
        ]));
    }
}
```

---

### 12. 敏感信息泄露（中危）

**位置**: `start.php:38-49`, 多处错误处理

**问题描述**:
```php
} catch (Exception $e) {
    $errorResponse = new Response(500, [
        'Content-Type' => 'application/json',
    ], json_encode([
        'error' => 'Internal Server Error',
        'message' => $e->getMessage(), // ⚠️ 泄露异常信息
        'timestamp' => time(),
    ]));
```

**风险**:
- ❌ 生产环境返回详细错误信息
- ❌ 可能泄露文件路径、配置信息等

**修复建议**:
```php
} catch (Exception $e) {
    // 记录详细错误到日志
    error_log(sprintf(
        '[ERROR] %s: %s in %s:%d',
        get_class($e),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine()
    ));
    
    // 生产环境只返回通用错误
    $isDebug = env('APP_DEBUG', false);
    $errorResponse = new Response(500, [
        'Content-Type' => 'application/json',
    ], json_encode([
        'error' => 'Internal Server Error',
        'message' => $isDebug ? $e->getMessage() : 'An error occurred',
        'timestamp' => time(),
    ]));
}
```

---

### 13. 配置加载安全（中危）

**位置**: `app/waf/config/ConfigManager.php:52, 107`

**问题描述**:
```php
$this->config[$configName] = require $filePath;
```

**风险**:
- ❌ 使用 `require` 执行配置文件，如果配置文件被污染可能导致代码注入
- ❌ 未验证配置文件完整性

**修复建议**:
1. 对配置文件进行校验和检查
2. 限制配置文件只能包含数据，不能包含可执行代码
3. 使用更安全的方式加载配置（如只解析数组）

```php
private function loadConfigFile(string $filename): void
{
    $filePath = $this->configPath . '/' . $filename;
    if (!file_exists($filePath)) {
        return;
    }
    
    // 1. 验证文件在配置目录内（防止路径遍历）
    $realPath = realpath($filePath);
    $realConfigPath = realpath($this->configPath);
    if (strpos($realPath, $realConfigPath) !== 0) {
        throw new \SecurityException('Config file path traversal detected');
    }
    
    // 2. 验证文件类型
    if (pathinfo($filePath, PATHINFO_EXTENSION) !== 'php') {
        return; // 只加载 PHP 配置文件
    }
    
    // 3. 验证文件内容（可选：使用 PHP Parser）
    // ...
    
    $configName = pathinfo($filename, PATHINFO_FILENAME);
    
    // 4. 安全加载（隔离执行）
    $config = (function() use ($filePath) {
        return require $filePath;
    })();
    
    if (!is_array($config)) {
        throw new \InvalidArgumentException("Config file must return an array: {$filename}");
    }
    
    $this->config[$configName] = $config;
}
```

---

## 🟢 低危安全问题

### 14. 缺少安全响应头（低危）

**位置**: 所有 Response 生成处

**问题描述**:
- ❌ 缺少 `X-Content-Type-Options: nosniff`
- ❌ 缺少 `X-Frame-Options: DENY`
- ❌ 缺少 `X-XSS-Protection: 1; mode=block`
- ❌ 缺少 `Strict-Transport-Security` (HSTS)

**修复建议**:
创建统一的响应头中间件：

```php
class SecurityHeadersMiddleware
{
    public function process(Request $request, callable $next): Response
    {
        $response = $next($request);
        
        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'X-XSS-Protection' => '1; mode=block',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()',
        ];
        
        // 生产环境添加 HSTS
        if (env('APP_ENV') === 'production') {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }
        
        foreach ($headers as $key => $value) {
            $response->header($key, $value);
        }
        
        return $response;
    }
}
```

---

### 15. 日志敏感信息（低危）

**位置**: `app/waf/logging/AsyncLogger.php`, `app/waf/logging/LogCollector.php`

**问题描述**:
- ❌ 可能记录完整的请求体（包含密码等敏感信息）
- ❌ 日志文件权限可能不安全

**修复建议**:
```php
private function sanitizeLogData(array $data): array
{
    // 敏感字段列表
    $sensitiveFields = ['password', 'passwd', 'pwd', 'secret', 'token', 'api_key'];
    
    foreach ($data as $key => $value) {
        $lowerKey = strtolower($key);
        
        // 检查是否包含敏感字段
        if (in_array($lowerKey, $sensitiveFields) || 
            preg_match('/.*(password|secret|token).*/i', $key)) {
            $data[$key] = '***REDACTED***';
            continue;
        }
        
        // 递归处理数组
        if (is_array($value)) {
            $data[$key] = $this->sanitizeLogData($value);
        }
        
        // 限制日志长度
        if (is_string($value) && strlen($value) > 1000) {
            $data[$key] = substr($value, 0, 1000) . '... [TRUNCATED]';
        }
    }
    
    return $data;
}
```

---

## 📋 安全最佳实践建议

### 1. 输入验证框架
建议实现统一的输入验证类：

```php
class InputValidator
{
    public static function username(string $value): bool
    {
        return preg_match('/^[a-zA-Z0-9_-]{3,50}$/', $value);
    }
    
    public static function password(string $value): bool
    {
        // 至少8位，包含大小写字母、数字、特殊字符
        return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $value);
    }
    
    public static function ip(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_IP) !== false;
    }
}
```

### 2. 安全配置检查清单
- [ ] 禁用生产环境的 `APP_DEBUG`
- [ ] 设置强密码策略
- [ ] 配置 HTTPS（生产环境）
- [ ] 设置合理的会话超时时间
- [ ] 配置可信代理列表
- [ ] 启用安全响应头
- [ ] 配置日志轮转和清理策略

### 3. 代码审计工具集成
建议集成以下工具：
- **PHPStan / Psalm**: 静态代码分析
- **SonarQube**: 代码质量与安全扫描
- **OWASP Dependency Check**: 依赖漏洞扫描

### 4. 安全测试
建议添加安全测试用例：
- 认证绕过测试
- SQL 注入测试
- XSS 测试
- CSRF 测试
- 路径遍历测试

---

## 🎯 优先级修复建议

### 立即修复（P0）
1. ✅ 修复密码哈希问题（问题 #2）
2. ✅ 移除硬编码密码或强制首次修改（问题 #1）
3. ✅ 添加会话固定保护（问题 #3）
4. ✅ 修复 Cookie 安全性（问题 #4）
5. ✅ 修复路径遍历风险（问题 #5）

### 近期修复（P1）
6. ✅ 实现 CSRF 保护（问题 #6）
7. ✅ 改进 IP 地址验证（问题 #7）
8. ✅ 添加输入验证（问题 #8）
9. ✅ 添加登录失败限制（问题 #11）

### 计划修复（P2）
10. ✅ 改进错误处理（问题 #12）
11. ✅ 改进配置加载安全（问题 #13）
12. ✅ 添加安全响应头（问题 #14）
13. ✅ 改进日志安全（问题 #15）

---

## 📝 总结

天罡 WAF 项目在架构设计上较为合理，采用了混合架构模式，代码结构清晰。但在安全方面存在一些关键问题，主要集中在：

1. **认证授权**: 默认密码、密码哈希、会话管理
2. **输入验证**: 缺少统一的输入验证机制
3. **安全防护**: CSRF、XSS、路径遍历等防护不足

建议按照优先级逐步修复这些问题，并在生产环境部署前完成所有 P0 和 P1 级别的修复。

---

**报告生成时间**: 2025-01-17  
**下次审查建议**: 修复完成后进行安全测试和渗透测试

