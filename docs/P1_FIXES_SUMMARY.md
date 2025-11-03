# P1 中危安全问题修复总结

**修复日期**: 2025-01-17  
**修复内容**: P1 高优先级中危安全问题（前4个）

---

## ✅ 已修复的问题

### 1. ✅ IP 地址验证改进（中危 - 高优先级）

**问题**: 直接信任客户端可伪造的 HTTP 头，可能绕过 IP 白名单/黑名单

**修复**:
- 优先使用连接的真实 IP（最可靠）
- 添加可信代理列表配置
- 只有来自可信代理的请求才信任代理头
- 使用最后一个 IP（最靠近客户端）
- 统一修复了 `WafMiddleware` 和 `AuthController` 中的 IP 获取方法

**修复位置**:
- `app/waf/middleware/WafMiddleware.php:169-208`
- `app/admin/controller/AuthController.php:354-397`
- `config/waf.php:92-101`（添加安全配置）

**配置示例**:
```env
TRUSTED_PROXIES=127.0.0.1,::1,10.0.0.1
```

---

### 2. ✅ SSRF 防护（中危 - 高优先级）

**问题**: 代理 URL 构建未验证，可能导致服务器端请求伪造攻击

**修复**:
- 验证基础 URL 格式和协议（只允许 http/https）
- 添加允许的后端主机列表配置
- 阻止访问私有 IP（可配置）
- 清理路径，防止路径遍历
- 验证查询字符串，移除危险参数
- 最终验证目标主机

**修复位置**:
- `app/waf/proxy/ProxyHandler.php:204-299`
- `config/proxy.php:122-126`（添加 SSRF 防护配置）

**新增方法**:
- `sanitizePath()`: 清理路径，防止路径遍历
- `getClientIp()`: 安全获取客户端 IP
- `getProtocol()`: 安全获取协议

**配置示例**:
```env
ALLOWED_BACKEND_HOSTS=backend.example.com,api.example.com
BLOCK_PRIVATE_IPS=true
```

---

### 3. ✅ CSRF 保护（中危 - 高优先级）

**问题**: 所有 POST 请求都没有 CSRF Token 验证

**修复**:
- 创建了 `CsrfMiddleware` 中间件
- 实现 CSRF Token 生成和验证
- 支持 Redis 和文件两种存储方式
- 登录页面自动生成 CSRF Token
- AJAX 请求和表单提交都支持
- 使用时间安全的比较（`hash_equals`）

**修复位置**:
- `app/admin/middleware/CsrfMiddleware.php`（新建）
- `app/admin/controller/AuthController.php`: 集成 CSRF Token 生成
- `app/waf/TiangangGateway.php`: 应用 CSRF 中间件
- 登录页面 HTML: 添加 CSRF Token 隐藏字段和 AJAX Header

**功能特性**:
- Token 有效期: 1 小时
- 自动跳过 GET/HEAD/OPTIONS 请求
- 支持从 Header (`X-CSRF-Token`) 或表单字段 (`_token`) 获取
- 文件存储后备方案（Redis 不可用时）

---

### 4. ✅ 登录失败限制（中危 - 高优先级）

**问题**: 没有登录失败限制，易受暴力破解攻击

**修复**:
- 记录登录尝试（IP + 用户名）
- 登录失败计数（每 IP+用户组合）
- IP 封禁机制（失败 5 次封禁 1 小时）
- 显示剩余尝试次数
- 支持 Redis 和文件两种存储方式

**修复位置**:
- `app/admin/controller/AuthController.php:71-143, 467-609`

**新增方法**:
- `isIpBlocked()`: 检查 IP 是否被封禁
- `blockIp()`: 封禁 IP
- `recordLoginAttempt()`: 记录登录尝试
- `incrementFailedAttempts()`: 增加失败计数
- `clearFailedAttempts()`: 清除失败记录

**保护机制**:
- 失败 5 次后封禁 IP 1 小时
- 每次失败显示剩余尝试次数
- 登录成功自动清除失败记录

---

## 🔒 额外安全增强

### 请求头注入防护

在 `ProxyHandler::filterHeaders()` 中添加了：
- 验证头名称和值（移除换行符）
- 防止 HTTP 头注入攻击
- 安全的 IP 和协议获取方法

### 配置更新

在 `env.example` 中添加了新的安全配置项：
```env
TRUSTED_PROXIES=127.0.0.1,::1
MAX_BODY_SIZE=10485760
MAX_URL_LENGTH=2048
MAX_HEADER_SIZE=8192
ALLOWED_BACKEND_HOSTS=
BLOCK_PRIVATE_IPS=true
FORCE_HTTPS=false
```

---

## 📋 待修复的 P1 问题

以下 P1 问题将在后续修复：

5. 日志敏感信息脱敏
6. 正则表达式 DoS 防护
7. 数据库查询安全（表名列名验证）
8. 错误信息处理（区分生产/开发环境）
9. 输入长度限制
10. 配置加载安全
11. 正则性能优化

---

## 🧪 测试建议

### 1. IP 地址验证测试
```bash
# 测试不可信代理的请求（应该使用连接 IP）
curl -H "X-Forwarded-For: 1.2.3.4" http://localhost:8787/

# 测试可信代理的请求（应该使用代理头中的 IP）
# 需要先配置 TRUSTED_PROXIES
```

### 2. SSRF 防护测试
```bash
# 尝试访问私有 IP（应该被阻止）
# 需要修改配置或后端 URL 为私有 IP

# 验证 URL 格式验证
# 应该只允许 http/https
```

### 3. CSRF 保护测试
```bash
# 不带 Token 的 POST 请求（应该失败）
curl -X POST http://localhost:8787/admin/dashboard -d "data=test"

# 带有效 Token 的请求（应该成功）
# 需要先登录获取 Token
```

### 4. 登录失败限制测试
```bash
# 连续5次错误登录（IP 应该被封禁）
for i in {1..6}; do
  curl -X POST http://localhost:8787/admin/auth/login \
    -d "username=admin&password=wrong"
done

# 第6次应该返回 429 错误
```

---

## ⚠️ 注意事项

1. **可信代理配置**: 生产环境必须正确配置 `TRUSTED_PROXIES`，否则可能无法正确获取客户端 IP

2. **CSRF Token**: 
   - 登录页面会自动生成 Token
   - AJAX 请求需要设置 `X-CSRF-Token` Header
   - Token 有效期 1 小时

3. **登录失败限制**:
   - 默认失败 5 次封禁 1 小时
   - 可在代码中调整阈值和封禁时间
   - 支持 Redis 和文件两种存储（自动降级）

4. **SSRF 防护**:
   - 默认阻止私有 IP
   - 可以配置允许的后端主机列表
   - 生产环境建议配置 `ALLOWED_BACKEND_HOSTS`

---

## 📊 修复进度

- ✅ P0 高危问题: 5/5 (100%)
- ✅ P1 高优先级: 4/4 (100%)
- ⏳ P1 其他: 8/8 (待修复)
- ⏳ P2 低危: 6/6 (待修复)

**总体修复进度**: 9/23 (39%)

---

**修复完成时间**: 2025-01-17  
**修复状态**: ✅ 前 4 个 P1 高优先级问题已修复

