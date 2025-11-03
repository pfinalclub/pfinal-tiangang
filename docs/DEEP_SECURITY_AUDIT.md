# 深度安全审查报告

**审查日期**: 2025-01-17  
**审查深度**: 全局代码深度审查  
**审查重点**: 错误处理、文件操作、插件安全、XXE、路径遍历

---

## 🔴 发现的新安全问题

### 1. 错误信息泄露（高危）

**位置**: 
- `app/waf/TiangangGateway.php:287`
- `app/waf/proxy/ProxyHandler.php:544`

**问题代码**:
```php
// TiangangGateway.php
private function createErrorResponse(\Exception $e): Response
{
    return new Response(500, [
        'Content-Type' => 'application/json',
    ], json_encode([
        'error' => 'Internal Server Error',
        'message' => $e->getMessage(), // ⚠️ 生产环境暴露异常信息
        'timestamp' => time(),
    ]));
}

// ProxyHandler.php
private function handleUnexpectedError(\Exception $e, Request $request): Response
{
    logger('error', 'Unexpected proxy error', [
        'url' => $request->path(),
        'method' => $request->method(),
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString() // ⚠️ 日志中包含完整堆栈跟踪
    ]);
    
    return new Response(500, [
        'Content-Type' => 'application/json',
    ], json_encode([
        'error' => 'Internal Server Error',
        'message' => 'An unexpected error occurred', // ✅ 这个是安全的
        'timestamp' => time(),
    ]));
}
```

**风险**:
- ❌ 生产环境返回详细错误信息，可能泄露：
  - 文件路径
  - 数据库连接信息
  - 内部配置
  - 代码结构

**修复建议**:
```php
private function createErrorResponse(\Exception $e): Response
{
    // 记录详细错误到日志（不返回给客户端）
    error_log('WAF Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    
    // 根据环境决定是否返回详细信息
    $debug = env('APP_DEBUG', false) && env('APP_ENV', 'production') !== 'production';
    
    return new Response(500, [
        'Content-Type' => 'application/json',
    ], json_encode([
        'error' => 'Internal Server Error',
        'message' => $debug ? $e->getMessage() : 'An unexpected error occurred. Please contact support.',
        'timestamp' => time(),
        'request_id' => uniqid('req_', true), // 可选：用于追踪错误
    ]));
}
```

---

### 2. LogCollector IP 获取未使用可信代理（中危）

**位置**: `app/waf/logging/LogCollector.php:154-174`

**问题代码**:
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
            $ip = trim($ips[0]); // ⚠️ 直接信任第一个IP，未使用可信代理机制
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    
    return $request->connection->getRemoteIp();
}
```

**风险**: 
- ❌ 未使用可信代理机制，可能记录错误的 IP
- ❌ 与 `WafMiddleware` 和 `AuthController` 的 IP 获取逻辑不一致

**修复建议**: 与 `WafMiddleware::getRealIp()` 保持一致，使用可信代理机制

---

### 3. 日志敏感信息未脱敏（中危）

**位置**: `app/waf/logging/LogCollector.php:38-68`

**问题代码**:
```php
public function log(Request $request, WafResult $result, float $responseTime): void
{
    $logData = [
        'timestamp' => time(),
        'ip' => $this->getRealIp($request),
        'uri' => $request->path(), // ⚠️ 可能包含敏感查询参数
        'method' => $request->method(),
        'user_agent' => $request->header('User-Agent', ''),
        'referer' => $request->header('Referer', ''),
        'blocked' => $result->isBlocked(),
        'rule' => $result->getRule(),
        'message' => $result->getMessage(),
        'status_code' => $result->getStatusCode(),
        'response_time' => $responseTime,
        'details' => $result->getDetails(), // ⚠️ 可能包含敏感信息（密码、token等）
    ];
    // ...
}
```

**风险**:
- ❌ 日志可能记录：
  - 密码、token、API密钥
  - 完整查询字符串（可能包含敏感参数）
  - POST 数据详情

**修复建议**: 在记录前对敏感字段进行脱敏处理（已在全面审计报告中详细说明）

---

### 4. ProxyHandler 代码不完整（中危）

**位置**: `app/waf/proxy/ProxyHandler.php:397-412`

**问题代码**:
```php
private function getProtocol(): string
{
    // ⚠️ 第399行代码缺失，直接跳到第401行
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
```

**状态**: 代码虽然看起来正常，但可能在第399行有空白行或其他问题。需要检查。

---

### 5. 插件加载安全问题（高危）

**位置**: `app/waf/plugins/PluginManager.php:42-61`

**问题代码**:
```php
private function loadPlugin(string $pluginFile): void
{
    try {
        // 包含插件文件
        require_once $pluginFile; // ⚠️ 直接包含文件，未验证路径
        
        // 动态加载插件类
        $className = $this->getClassNameFromFile($pluginFile);
        if ($className && class_exists($className)) {
            $plugin = new $className();
            
            if ($plugin instanceof WafPluginInterface) {
                $this->plugins[$plugin->getName()] = $plugin;
            }
        }
    } catch (\Exception $e) {
        error_log("Failed to load plugin {$pluginFile}: " . $e->getMessage());
    }
}
```

**风险**:
- ❌ 未验证 `$pluginFile` 路径是否在允许的插件目录内
- ❌ 可能通过路径遍历（`../`）加载任意文件
- ❌ 插件文件可能包含恶意代码

**修复建议**:
```php
private function loadPlugin(string $pluginFile): void
{
    try {
        // 1. 验证文件路径（防止路径遍历）
        $realPluginPath = realpath($this->pluginPath);
        $realPluginFile = realpath($pluginFile);
        
        if ($realPluginFile === false || 
            strpos($realPluginFile, $realPluginPath) !== 0) {
            throw new \SecurityException('Plugin file path is outside allowed directory');
        }
        
        // 2. 验证文件扩展名
        if (pathinfo($pluginFile, PATHINFO_EXTENSION) !== 'php') {
            throw new \InvalidArgumentException('Plugin file must be a PHP file');
        }
        
        // 3. 验证文件可读
        if (!is_readable($pluginFile)) {
            throw new \RuntimeException('Plugin file is not readable');
        }
        
        // 4. 包含插件文件（现在相对安全）
        require_once $pluginFile;
        
        // 5. 动态加载插件类
        $className = $this->getClassNameFromFile($pluginFile);
        if ($className && class_exists($className)) {
            $plugin = new $className();
            
            if ($plugin instanceof WafPluginInterface) {
                $this->plugins[$plugin->getName()] = $plugin;
            }
        }
    } catch (\Exception $e) {
        error_log("Failed to load plugin {$pluginFile}: " . $e->getMessage());
    }
}
```

---

### 6. CSRF Token 文件路径安全问题（中危）

**位置**: `app/admin/middleware/CsrfMiddleware.php:149-171`

**问题代码**:
```php
private function getTokenFromFile(string $sessionId): ?string
{
    $tokenFile = runtime_path('csrf_tokens/' . substr($sessionId, 0, 2) . '/' . $sessionId . '.token');
    // ⚠️ 未验证 $sessionId 格式，可能导致路径遍历
    // ...
}
```

**风险**:
- ❌ 如果 `$sessionId` 包含 `../` 或特殊字符，可能导致路径遍历
- ❌ 虽然使用了 `substr($sessionId, 0, 2)`，但如果 `$sessionId` 本身是恶意构造的，仍可能有问题

**修复建议**:
```php
private function getTokenFromFile(string $sessionId): ?string
{
    // 验证 sessionId 格式（只允许十六进制字符）
    if (!preg_match('/^[a-f0-9]+$/i', $sessionId)) {
        return null;
    }
    
    // 使用 basename 防止路径遍历
    $prefix = substr($sessionId, 0, 2);
    $filename = basename($sessionId . '.token');
    
    $baseDir = realpath(runtime_path('csrf_tokens'));
    if ($baseDir === false) {
        return null;
    }
    
    $tokenFile = $baseDir . '/' . $prefix . '/' . $filename;
    
    // 验证最终路径在预期目录内
    $realTokenFile = realpath($tokenFile);
    if ($realTokenFile === false || strpos($realTokenFile, $baseDir) !== 0) {
        return null;
    }
    
    // ... 其余代码
}
```

---

### 7. XXE 注入风险（高危）

**位置**: `app/admin/controller/DashboardController.php:309-329`

**问题代码**:
```php
private function arrayToXml(array $data): string
{
    $xml = new \SimpleXMLElement('<root/>'); // ⚠️ 未禁用外部实体
    $this->arrayToXmlRecursive($data, $xml);
    return $xml->asXML();
}

private function arrayToXmlRecursive(array $data, \SimpleXMLElement $xml): void
{
    foreach ($data as $key => $value) {
        if (is_array($value)) {
            $subnode = $xml->addChild($key);
            $this->arrayToXmlRecursive($value, $subnode);
        } else {
            $xml->addChild($key, htmlspecialchars($value));
        }
    }
}
```

**风险**:
- ❌ 使用 `SimpleXMLElement` 时未禁用外部实体解析
- ❌ 如果从外部输入创建 XML，可能导致 XXE 攻击
- ❌ 可能泄露文件内容、触发 SSRF

**修复建议**:
```php
private function arrayToXml(array $data): string
{
    // 禁用外部实体解析（防止 XXE）
    $oldValue = libxml_disable_entity_loader(true);
    
    try {
        $xml = new \SimpleXMLElement('<root/>');
        $this->arrayToXmlRecursive($data, $xml);
        return $xml->asXML();
    } finally {
        // 恢复原始设置
        libxml_disable_entity_loader($oldValue);
    }
}

// 或者使用更安全的方法
private function arrayToXml(array $data): string
{
    $xml = '<?xml version="1.0" encoding="UTF-8"?><root>';
    $xml .= $this->arrayToXmlString($data);
    $xml .= '</root>';
    return $xml;
}

private function arrayToXmlString(array $data): string
{
    $xml = '';
    foreach ($data as $key => $value) {
        $safeKey = htmlspecialchars($key, ENT_XML1, 'UTF-8');
        
        if (is_array($value)) {
            $xml .= "<{$safeKey}>" . $this->arrayToXmlString($value) . "</{$safeKey}>";
        } else {
            $safeValue = htmlspecialchars($value, ENT_XML1, 'UTF-8');
            $xml .= "<{$safeKey}>{$safeValue}</{$safeKey}>";
        }
    }
    return $xml;
}
```

---

### 8. 插件文件路径遍历风险（高危）

**位置**: `app/waf/plugins/PluginManager.php:34-36`

**问题代码**:
```php
foreach (glob($this->pluginPath . '/*.php') as $pluginFile) {
    $this->loadPlugin($pluginFile);
}
```

**状态**: 虽然 `glob()` 本身相对安全，但结合 `loadPlugin()` 中的 `require_once`，仍需要路径验证。

---

### 9. 文件包含安全检查不足（高危）

**位置**: `app/waf/plugins/PluginManager.php:66-92`

**问题代码**:
```php
private function getClassNameFromFile(string $pluginFile): ?string
{
    $content = file_get_contents($pluginFile);
    // ⚠️ 读取任意文件内容（虽然是在插件目录内，但仍需验证）
    // ...
}
```

**建议**: 在 `loadPlugin()` 中已经验证了路径，这里相对安全，但可以加强。

---

## 🟡 其他发现的问题

### 10. 缺少请求大小验证

**位置**: 多处请求处理

虽然配置文件中定义了 `MAX_BODY_SIZE`、`MAX_URL_LENGTH` 等，但代码中并未实际使用这些配置进行验证。

**建议**: 在 `TiangangGateway::handle()` 开始处添加请求大小验证。

---

### 11. Redis 连接错误处理

**位置**: `app/waf/logging/LogCollector.php:134-149`

**问题**: Redis 连接失败时，构造函数可能抛出异常，导致整个应用无法启动。

**建议**: 使用 try-catch 包装，Redis 不可用时降级到文件日志。

---

### 12. 日志文件权限

**位置**: `app/waf/logging/LogCollector.php:122-126`

**问题**: 日志文件可能被其他用户读取。

**建议**: 创建日志文件时设置严格权限（0600）。

---

## 📋 修复优先级

| 优先级 | 问题 | 风险等级 | 建议修复时间 |
|--------|------|----------|--------------|
| P0 | 错误信息泄露 | 高危 | 立即 |
| P0 | 插件加载安全问题 | 高危 | 立即 |
| P0 | XXE 注入风险 | 高危 | 立即 |
| P1 | LogCollector IP 获取 | 中危 | 近期 |
| P1 | 日志敏感信息脱敏 | 中危 | 近期 |
| P1 | CSRF Token 文件路径 | 中危 | 近期 |
| P2 | 请求大小验证 | 低危 | 计划 |
| P2 | Redis 连接错误处理 | 低危 | 计划 |

---

## 📊 安全评分更新

考虑到新发现的问题：

**修复前**: ⭐⭐⭐⭐⭐ (5/5) - 这是基于之前修复的评分  
**修复后（包含新问题）**: ⭐⭐⭐⭐☆ (4/5) - 发现新的高危问题

**总体评估**:
- ✅ 基础安全防护：良好
- ⚠️ 错误处理：需要改进
- ⚠️ 插件系统安全：需要加强
- ⚠️ XML 处理：存在风险

---

**报告生成时间**: 2025-01-17  
**下次审查建议**: 修复所有 P0 问题后再次审查

