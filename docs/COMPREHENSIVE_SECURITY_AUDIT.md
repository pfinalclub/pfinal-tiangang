# 天罡 WAF 全面安全审查报告

**审查日期**: 2025-01-17  
**审查人员**: 资深安全工程师  
**审查范围**: 全项目代码安全审查  
**审查方法**: 静态代码分析、架构审查、最佳实践对比

---

## 📊 执行摘要

本次审查对天罡 WAF 项目进行了全面的安全评估，涵盖了：
- ✅ 认证授权系统
- ✅ 输入验证与输出编码
- ✅ 数据库安全
- ✅ 代理转发安全
- ✅ 文件操作安全
- ✅ 日志与敏感信息
- ✅ 配置安全
- ✅ 架构安全

**审查结果**:
- 🔴 **P0 高危问题**: 5 个（已全部修复）
- 🟡 **P1 中危问题**: 12 个（需要尽快修复）
- 🟢 **P2 低危问题**: 6 个（建议修复）
- ✅ **已修复问题**: 5 个

---

## ✅ 已修复的 P0 问题

所有 P0 高危问题已在之前的修复中解决，详情请参考 `docs/P0_FIXES_SUMMARY.md`：

1. ✅ 密码哈希问题
2. ✅ 移除默认账户提示
3. ✅ 会话固定攻击保护
4. ✅ Cookie 安全性增强
5. ✅ 路径遍历攻击防护

---

## 🟡 P1 中危安全问题（需尽快修复）

### 1. IP 地址伪造风险（中危）

**位置**: 
- `app/waf/middleware/WafMiddleware.php:169-189`
- `app/admin/controller/AuthController.php:327-353`

**问题描述**:
```php
private function getRealIp(Request $request): string
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
            $ip = trim($ips[0]); // ⚠️ 直接信任第一个IP
            if (filter_var($ip, FILTER_VALIDATE_IP, ...)) {
                return $ip;
            }
        }
    }
}
```

**风险**:
- ❌ 直接信任客户端可伪造的 HTTP 头（`X-Forwarded-For`, `X-Real-IP` 等）
- ❌ 未验证代理链的真实性
- ❌ 未配置可信代理列表
- ❌ IP 白名单/黑名单可能被绕过

**影响**: 
- 可能绕过 IP 黑名单/白名单检测
- 日志记录的 IP 不准确
- 会话绑定到错误的 IP

**修复建议**:
```php
private function getRealIp(Request $request): string
{
    // 1. 获取连接的真实 IP（最可靠）
    $remoteIp = $request->connection->getRemoteIp() ?? '127.0.0.1';
    
    // 2. 检查是否为可信代理
    $trustedProxies = config('app.trusted_proxies', []);
    if (!in_array($remoteIp, $trustedProxies)) {
        // 不是可信代理，直接返回连接IP
        return $remoteIp;
    }
    
    // 3. 如果是可信代理，才信任代理头
    $forwardedFor = $request->header('X-Forwarded-For');
    if ($forwardedFor) {
        // 取最后一个IP（最靠近客户端的）
        $ips = array_map('trim', explode(',', $forwardedFor));
        $ip = end($ips);
        
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return $ip;
        }
    }
    
    // 4. 回退到连接IP
    return $remoteIp;
}
```

**配置文件添加**:
```php
// config/app.php
'trusted_proxies' => [
    '127.0.0.1',
    '::1',
    // 添加实际使用的负载均衡器/代理 IP
],
```

---

### 2. SSRF 风险 - 代理 URL 未验证（中危）

**位置**: `app/waf/proxy/ProxyHandler.php:207-221`

**问题描述**:
```php
private function buildTargetUrl(Request $request): string
{
    $backend = $this->getBackendConfig();
    $baseUrl = $backend['url'];
    $path = $request->path();
    $query = $request->queryString();
    
    $targetUrl = rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
    
    if ($query) {
        $targetUrl .= '?' . $query; // ⚠️ 未验证查询字符串
    }
    
    return $targetUrl;
}
```

**风险**:
- ❌ 如果 `$baseUrl` 配置错误或被污染，可能导致 SSRF
- ❌ 查询字符串未验证，可能包含恶意参数
- ❌ 未限制目标协议（可能允许 `file://`, `gopher://` 等）

**影响**: 可能导致服务器端请求伪造（SSRF）攻击

**修复建议**:
```php
private function buildTargetUrl(Request $request): string
{
    $backend = $this->getBackendConfig();
    $baseUrl = $backend['url'];
    
    // 1. 验证基础 URL
    if (!filter_var($baseUrl, FILTER_VALIDATE_URL)) {
        throw new \InvalidArgumentException('Invalid backend URL');
    }
    
    $parsedBase = parse_url($baseUrl);
    if (!in_array($parsedBase['scheme'] ?? '', ['http', 'https'])) {
        throw new \InvalidArgumentException('Backend URL must use http or https');
    }
    
    // 2. 验证目标主机（防止 SSRF）
    $allowedHosts = config('proxy.allowed_backend_hosts', [
        parse_url($baseUrl, PHP_URL_HOST)
    ]);
    
    $path = $request->path();
    $query = $request->queryString();
    
    // 3. 验证路径（防止路径遍历）
    $path = $this->sanitizePath($path);
    
    // 4. 验证查询字符串（防止注入）
    if ($query) {
        parse_str($query, $queryParams);
        $query = http_build_query($queryParams);
    }
    
    $targetUrl = rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
    
    if ($query) {
        $targetUrl .= '?' . $query;
    }
    
    // 5. 最终验证：确保目标主机在允许列表中
    $parsedTarget = parse_url($targetUrl);
    if (!in_array($parsedTarget['host'] ?? '', $allowedHosts)) {
        throw new \SecurityException('SSRF attempt detected: Host not in allowed list');
    }
    
    return $targetUrl;
}

private function sanitizePath(string $path): string
{
    // 移除危险字符和路径遍历
    $path = str_replace(['../', '..\\', '//'], '', $path);
    $path = preg_replace('/[^a-zA-Z0-9\-_\/\.]/', '', $path);
    return $path;
}
```

---

### 3. 正则表达式 DoS 风险（中危）

**位置**: `app/api/controllers/RuleController.php:366-370`

**问题描述**:
```php
// 验证正则表达式
if (isset($ruleData['pattern'])) {
    $pattern = $ruleData['pattern'];
    if (@preg_match($pattern, '') === false) { // ⚠️ 使用 @ 抑制错误
        $errors[] = "Invalid regex pattern: {$pattern}";
    }
}
```

**风险**:
- ❌ 使用 `@` 抑制错误，可能隐藏安全问题
- ❌ 未限制正则表达式复杂度（ReDoS 风险）
- ❌ 未验证反向引用、递归等危险特性
- ❌ 用户可以提交恶意正则表达式导致 DoS

**影响**: 
- 可能导致正则表达式 DoS（ReDoS）
- 系统资源耗尽

**修复建议**:
```php
private function validateRegexPattern(string $pattern): array
{
    $errors = [];
    
    // 1. 检查基本格式
    if (@preg_match($pattern, '') === false) {
        $errors[] = "Invalid regex pattern syntax";
        return ['valid' => false, 'errors' => $errors];
    }
    
    // 2. 检查反向引用（可能导致 ReDoS）
    if (preg_match('/\\\\\d+/', $pattern)) {
        $errors[] = "Backreferences not allowed (ReDoS risk)";
    }
    
    // 3. 检查递归模式（可能导致 ReDoS）
    if (preg_match('/(\([^)]*\)\+|\+\+|\\*\\*)/', $pattern)) {
        $errors[] = "Nested quantifiers not allowed (ReDoS risk)";
    }
    
    // 4. 限制模式长度
    if (strlen($pattern) > 500) {
        $errors[] = "Pattern too long (max 500 characters)";
    }
    
    // 5. 性能测试（限制执行时间）
    $startTime = microtime(true);
    preg_match($pattern, str_repeat('a', 100));
    $duration = microtime(true) - $startTime;
    
    if ($duration > 0.01) { // 10ms 阈值
        $errors[] = "Pattern too slow (possible ReDoS)";
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}
```

---

### 4. 日志敏感信息泄露（中危）

**位置**: `app/waf/logging/LogCollector.php:38-68`

**问题描述**:
```php
$logData = [
    'timestamp' => time(),
    'ip' => $this->getRealIp($request),
    'uri' => $request->path(),
    'method' => $request->method(),
    'user_agent' => $request->header('User-Agent', ''),
    'referer' => $request->header('Referer', ''),
    'blocked' => $result->isBlocked(),
    // ⚠️ 可能包含敏感信息的字段未过滤
];
```

**风险**:
- ❌ 日志可能记录完整的请求数据（包含密码、token 等）
- ❌ 查询参数可能包含敏感信息
- ❌ POST 数据未进行脱敏处理

**影响**: 日志泄露可能导致敏感信息暴露

**修复建议**:
```php
public function log(Request $request, WafResult $result, float $responseTime): void
{
    if (!$this->config['enabled']) {
        return;
    }
    
    $logData = [
        'timestamp' => time(),
        'ip' => $this->getRealIp($request),
        'uri' => $this->sanitizeUri($request->path()), // 移除敏感参数
        'method' => $request->method(),
        'user_agent' => substr($request->header('User-Agent', ''), 0, 255), // 限制长度
        'referer' => $this->sanitizeReferer($request->header('Referer', '')),
        'blocked' => $result->isBlocked(),
        'rule' => $result->getRule(),
        'message' => $result->getMessage(),
        'status_code' => $result->getStatusCode(),
        'response_time' => $responseTime,
        'details' => $this->sanitizeDetails($result->getDetails()), // 脱敏
    ];
    
    // ... 记录日志
}

private function sanitizeDetails(array $details): array
{
    $sensitiveFields = ['password', 'passwd', 'pwd', 'secret', 'token', 'api_key', 'authorization'];
    
    foreach ($details as $key => $value) {
        $lowerKey = strtolower($key);
        
        if (in_array($lowerKey, $sensitiveFields) || 
            preg_match('/.*(password|secret|token|key).*/i', $key)) {
            $details[$key] = '***REDACTED***';
            continue;
        }
        
        if (is_array($value)) {
            $details[$key] = $this->sanitizeDetails($value);
        } elseif (is_string($value) && strlen($value) > 1000) {
            $details[$key] = substr($value, 0, 1000) . '... [TRUNCATED]';
        }
    }
    
    return $details;
}

private function sanitizeUri(string $uri): string
{
    // 移除查询参数中的敏感信息
    if (strpos($uri, '?') !== false) {
        list($path, $query) = explode('?', $uri, 2);
        parse_str($query, $params);
        
        foreach ($params as $key => $value) {
            if (preg_match('/.*(password|token|key|secret).*/i', $key)) {
                $params[$key] = '***';
            }
        }
        
        $query = http_build_query($params);
        return $path . '?' . $query;
    }
    
    return $uri;
}
```

---

### 5. 缺少 CSRF 保护（中危）

**位置**: 所有 POST/PUT/DELETE 请求处理

**问题描述**:
- ❌ 所有 POST 请求（登录、规则创建/更新等）都没有 CSRF Token 验证
- ❌ 表单未包含 CSRF Token
- ❌ AJAX 请求未包含 CSRF Header

**影响**: 易受跨站请求伪造攻击

**修复建议**: 已在安全审计报告中详细说明，需要实现 CSRF 中间件

---

### 6. 登录失败限制不足（中危）

**位置**: `app/admin/controller/AuthController.php:32-63`

**问题描述**:
- ❌ 没有记录登录失败次数
- ❌ 没有账户锁定机制
- ❌ 没有 IP 封禁机制

**影响**: 易受暴力破解攻击

**修复建议**: 已在安全审计报告中详细说明

---

### 7. 数据库查询未完全使用预处理（中危）

**位置**: `app/waf/database/AsyncDatabaseManager.php:114-124, 130-159`

**问题描述**:
```php
$columns = implode(',', array_keys($data));
$placeholders = ':' . implode(', :', array_keys($data));
$sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})"; // ⚠️ $table 未使用预处理
```

**风险**:
- ❌ 表名未使用预处理（虽然来自配置，但理论上可被污染）
- ❌ UPDATE/DELETE 的 WHERE 子句构建可能不安全

**修复建议**:
```php
private function validateTableName(string $table): bool
{
    // 只允许字母、数字、下划线
    return preg_match('/^[a-zA-Z0-9_]+$/', $table) === 1;
}

public function asyncInsert(string $table, array $data): \Generator
{
    if (!$this->validateTableName($table)) {
        throw new \InvalidArgumentException('Invalid table name');
    }
    
    // 验证列名
    $validatedColumns = [];
    foreach (array_keys($data) as $column) {
        if (!$this->validateTableName($column)) {
            throw new \InvalidArgumentException("Invalid column name: {$column}");
        }
        $validatedColumns[] = $column;
    }
    
    $columns = implode(',', $validatedColumns);
    $placeholders = ':' . implode(', :', $validatedColumns);
    $sql = "INSERT INTO `{$table}` ({$columns}) VALUES ({$placeholders})";
    
    // 使用预处理
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($data);
    return $this->pdo->lastInsertId();
}
```

---

### 8. 错误信息泄露（中危）

**位置**: `start.php:38-49`, `app/waf/TiangangGateway.php:82-88`

**问题描述**:
```php
} catch (\Exception $e) {
    return new Response(500, [
        'Content-Type' => 'application/json',
    ], json_encode([
        'error' => 'Internal Server Error',
        'message' => $e->getMessage(), // ⚠️ 生产环境不应显示详细错误
        'timestamp' => time(),
    ]));
}
```

**风险**:
- ❌ 生产环境返回详细错误信息
- ❌ 可能泄露文件路径、配置信息等

**修复建议**: 已在安全审计报告中详细说明

---

### 9. 请求头注入风险（中危）

**位置**: `app/waf/proxy/ProxyHandler.php:278-281`

**问题描述**:
```php
$filteredHeaders['X-Forwarded-For'] = $this->getClientIp();
$filteredHeaders['X-Forwarded-Proto'] = $this->getProtocol();
$filteredHeaders['X-Real-IP'] = $this->getClientIp();
```

**风险**:
- ❌ 如果 `getClientIp()` 返回未验证的值，可能导致 HTTP 头注入
- ❌ 协议头未验证

**修复建议**:
```php
private function getClientIp(): string
{
    // ... 使用安全的 IP 获取方法
    $ip = $this->getRealIp(); // 已验证的方法
    
    // 验证 IP 格式（防止注入）
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        return '127.0.0.1';
    }
    
    return $ip;
}

private function getProtocol(): string
{
    $protocol = $_SERVER['HTTPS'] ?? $_SERVER['REQUEST_SCHEME'] ?? 'http';
    
    // 只允许 http 或 https
    if (!in_array(strtolower($protocol), ['http', 'https'])) {
        return 'http';
    }
    
    return strtolower($protocol);
}
```

---

### 10. 配置加载安全问题（中危）

**位置**: `app/waf/config/ConfigManager.php:102-109`

**问题描述**: 已在之前的审计中说明，需要加强配置文件验证

---

### 11. 缺少输入长度限制（中危）

**位置**: 多处请求处理

**问题描述**:
- ❌ 请求体大小未限制
- ❌ URL 长度未限制
- ❌ 请求头大小未限制

**影响**: 可能导致内存耗尽 DoS

**修复建议**:
```php
// 在网关层添加限制
private function validateRequestSize(Request $request): bool
{
    $maxBodySize = config('waf.max_body_size', 10 * 1024 * 1024); // 10MB
    $maxUrlLength = config('waf.max_url_length', 2048);
    $maxHeaderSize = config('waf.max_header_size', 8192);
    
    // 检查请求体大小
    $bodySize = strlen($request->rawBody());
    if ($bodySize > $maxBodySize) {
        return false;
    }
    
    // 检查 URL 长度
    if (strlen($request->path() . '?' . $request->queryString()) > $maxUrlLength) {
        return false;
    }
    
    // 检查总头大小
    $totalHeaderSize = 0;
    foreach ($request->header() as $name => $value) {
        $totalHeaderSize += strlen($name) + strlen($value);
    }
    if ($totalHeaderSize > $maxHeaderSize) {
        return false;
    }
    
    return true;
}
```

---

### 12. 正则表达式性能问题（中危）

**位置**: `app/waf/detectors/QuickDetector.php:191-199`

**问题描述**:
```php
private function getBasicPatterns(): array
{
    return [
        '/(union\s+select)/i' => 'sql_injection',
        '/(<script[^>]*>)/i' => 'xss',
        '/(javascript\s*:)/i' => 'xss',
        '/(on\w+\s*=)/i' => 'xss', // ⚠️ 可能导致性能问题
    ];
}
```

**风险**:
- ❌ 某些正则表达式可能在高负载下造成性能问题
- ❌ 未对正则匹配进行超时控制

**修复建议**: 添加超时机制和性能监控

---

## 🟢 P2 低危安全问题（建议修复）

### 13. 缺少安全响应头（低危）

**位置**: 所有响应生成

**问题描述**: 已在之前的审计中说明

---

### 14. 日志轮转策略不明确（低危）

**位置**: `app/waf/logging/AsyncLogger.php`

**问题描述**:
- ❌ 日志文件可能无限增长
- ❌ 未配置日志轮转策略

**修复建议**: 使用 Monolog 的 RotatingFileHandler

---

### 15. 配置文件权限问题（低危）

**位置**: 配置文件目录

**问题描述**:
- ❌ 配置文件可能被其他用户读取
- ❌ `.env` 文件权限未检查

**修复建议**:
```bash
chmod 600 .env
chmod 644 config/*.php
```

---

### 16. 会话清理机制不完善（低危）

**位置**: `app/admin/controller/AuthController.php`

**问题描述**:
- ❌ 过期会话文件未自动清理
- ❌ 可能导致磁盘空间耗尽

**修复建议**: 添加定期清理任务

---

### 17. 缺少请求ID追踪（低危）

**位置**: 请求处理流程

**问题描述**:
- ❌ 没有唯一请求ID
- ❌ 日志关联困难

**修复建议**: 为每个请求生成唯一ID并在响应头返回

---

### 18. API 版本控制缺失（低危）

**位置**: API 路由

**问题描述**:
- ❌ API 未进行版本控制
- ❌ 未来升级可能破坏兼容性

**修复建议**: 添加 API 版本前缀（如 `/api/v1/...`）

---

## 📋 安全最佳实践建议

### 1. 实现安全中间件链

```php
class SecurityMiddlewareChain
{
    public function process(Request $request, callable $next): Response
    {
        // 1. 请求大小验证
        if (!$this->validateRequestSize($request)) {
            return new Response(413, [], 'Request too large');
        }
        
        // 2. 频率限制
        if (!$this->checkRateLimit($request)) {
            return new Response(429, [], 'Rate limit exceeded');
        }
        
        // 3. CSRF 验证
        if (!$this->validateCsrf($request)) {
            return new Response(403, [], 'CSRF token invalid');
        }
        
        // 4. 安全响应头
        $response = $next($request);
        return $this->addSecurityHeaders($response);
    }
}
```

### 2. 统一输入验证框架

建议创建 `InputValidator` 类统一处理所有输入验证

### 3. 安全配置检查清单

- [ ] 禁用生产环境的 `APP_DEBUG`
- [ ] 设置强密码策略
- [ ] 配置 HTTPS
- [ ] 设置合理的会话超时
- [ ] 配置可信代理列表
- [ ] 启用安全响应头
- [ ] 配置日志轮转
- [ ] 设置请求大小限制

### 4. 代码审计工具集成

建议集成：
- **PHPStan/Psalm**: 静态分析
- **SonarQube**: 代码质量与安全扫描
- **OWASP Dependency Check**: 依赖漏洞扫描

---

## 🎯 修复优先级

### 立即修复（P0）- ✅ 已完成
1. ✅ 密码哈希问题
2. ✅ Cookie 安全性
3. ✅ 会话固定攻击
4. ✅ 路径遍历
5. ✅ 默认账户提示

### 近期修复（P1）
1. 🔴 IP 地址验证改进（高优先级）
2. 🔴 SSRF 防护（高优先级）
3. 🔴 CSRF 保护
4. 🔴 登录失败限制
5. 🔴 日志敏感信息脱敏
6. 🔴 正则表达式 DoS 防护
7. 🟡 数据库查询安全
8. 🟡 错误信息处理
9. 🟡 请求头注入防护
10. 🟡 输入长度限制
11. 🟡 配置加载安全
12. 🟡 正则性能优化

### 计划修复（P2）
- 安全响应头
- 日志轮转
- 配置文件权限
- 会话清理
- 请求ID追踪
- API版本控制

---

## 📊 安全评分

| 类别 | 评分 | 说明 |
|------|------|------|
| **认证授权** | ⭐⭐⭐⭐☆ | P0 问题已修复，但缺少失败限制 |
| **输入验证** | ⭐⭐⭐☆☆ | 部分验证，需要加强 |
| **输出编码** | ⭐⭐⭐⭐☆ | 基本良好 |
| **会话管理** | ⭐⭐⭐⭐☆ | P0 问题已修复 |
| **数据库安全** | ⭐⭐⭐⭐☆ | 基本使用预处理，表名需验证 |
| **加密存储** | ⭐⭐⭐☆☆ | 密码哈希正确，但会话未加密 |
| **错误处理** | ⭐⭐⭐☆☆ | 需要区分生产/开发环境 |
| **日志安全** | ⭐⭐⭐☆☆ | 需要敏感信息脱敏 |
| **配置安全** | ⭐⭐⭐☆☆ | 需要加强验证 |
| **架构安全** | ⭐⭐⭐⭐☆ | 整体架构合理 |

**总体评分**: ⭐⭐⭐⭐☆ (4/5)

---

## 📝 总结

天罡 WAF 项目在架构设计上较为合理，已经修复了所有 P0 高危问题。但在以下方面仍需改进：

1. **输入验证**: 需要统一的验证框架
2. **CSRF 保护**: 必须实现
3. **SSRF 防护**: 代理转发需要加强验证
4. **日志安全**: 敏感信息需要脱敏
5. **错误处理**: 区分生产/开发环境

建议按照优先级逐步修复 P1 问题，并在生产环境部署前完成所有关键安全修复。

---

**报告生成时间**: 2025-01-17  
**下次审查建议**: 修复 P1 问题后进行安全测试和渗透测试

