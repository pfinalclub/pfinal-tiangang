# 天罡 WAF - Nginx 前置代理部署指南

## 🎯 架构概述

天罡 WAF 已经升级为支持**前置 Nginx 模式**，推荐生产环境使用。

```
┌─────────┐     ┌──────────┐     ┌──────────────┐     ┌────────────┐
│ 浏览器  │────▶│  Nginx   │────▶│  天罡 WAF    │────▶│ 后端应用   │
│         │     │ 80/443   │     │ 8787 (代理)  │     │            │
└─────────┘     └──────────┘     │ 8989 (管理)  │     └────────────┘
                                 └──────────────┘
```

### 优势
- ✅ SSL/TLS 卸载在 Nginx 层
- ✅ 静态资源加速和缓存
- ✅ 负载均衡和故障转移
- ✅ **动态配置更新（通过 Lua 脚本）**
- ✅ **正确传递 Host 头，解决框架域名验证问题**
- ✅ WebSocket 和 HTTP/2 支持

---

## 🚀 快速开始

### 1. 安装 OpenResty

**Ubuntu/Debian:**
```bash
sudo apt update
sudo apt install -y openresty
```

**CentOS/RHEL:**
```bash
sudo yum install -y openresty
```

**macOS:**
```bash
brew install openresty/brew/openresty
```

### 2. 复制配置文件

```bash
# 复制 Lua 脚本
sudo cp nginx/lua/waf_config.lua /usr/local/openresty/lualib/

# 复制 Nginx 配置（或手动整合到你的 nginx.conf）
sudo cp nginx/conf/waf.conf /usr/local/openresty/nginx/conf/
```

### 3. 配置 Nginx

编辑 `/usr/local/openresty/nginx/conf/nginx.conf`，在 `http {}` 块中添加：

```nginx
http {
    # Lua 配置缓存
    lua_shared_dict waf_config 10m;
    
    # 初始化 WAF 配置模块
    init_by_lua_block {
        waf_config = require "waf_config"
        ngx.log(ngx.INFO, "[WAF] Configuration module loaded")
    }
    
    # WebSocket 支持
    map $http_upgrade $connection_upgrade {
        default upgrade;
        '' close;
    }
    
    # 引入 WAF 配置
    include waf.conf;
}
```

### 4. 启动服务

```bash
# 测试配置
sudo openresty -t

# 启动 OpenResty
sudo systemctl start openresty
sudo systemctl enable openresty

# 启动天罡 WAF
cd /path/to/tiangang
php start.php start -d
```

### 5. 配置域名映射

访问 WAF 管理后台：`http://your-server:8989/admin/config`

添加域名映射，例如：
- **域名**: `example.com`
- **后端地址**: `http://localhost:8080`
- **保留 Host 头**: ✅ 启用（重要！）

---

## 🔧 配置说明

### 环境变量

创建 `.env` 文件（或修改 `env.example`）：

```bash
# WAF 端口
PROXY_PORT=8787
ADMIN_PORT=8989

# Nginx 后端配置（用于配置刷新）
NGINX_BACKEND_HOST=127.0.0.1
NGINX_BACKEND_PORT=8788

# WAF API 地址（供 Nginx Lua 脚本读取）
WAF_API_HOST=127.0.0.1:8989
```

### Nginx 配置要点

**1. 传递原始 Host 头（重要！）**

```nginx
location / {
    proxy_pass http://tiangang_waf;
    
    # 使用 $http_host 保留原始 Host（包括端口）
    proxy_set_header Host $http_host;
    
    # 不修改重定向 URL
    proxy_redirect off;
    
    # 其他必要的头
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
}
```

**2. 动态配置更新（Lua）**

`waf_config.lua` 会每 60 秒自动从 WAF API 读取最新配置：

```lua
-- 配置 API 地址
local WAF_API_HOST = os.getenv("WAF_API_HOST") or "127.0.0.1:8989"
local WAF_API_ENDPOINT = "http://" .. WAF_API_HOST .. "/admin/api/nginx/config"

-- 缓存时间
local CACHE_TTL = 60  -- 60 秒
```

**手动刷新缓存：**

```bash
# 方式 1：通过 Nginx 内部接口
curl http://127.0.0.1:8788/_internal/refresh-config

# 方式 2：通过 WAF 管理后台 API
curl -X POST http://127.0.0.1:8989/admin/api/nginx/reload
```

---

## 🛠 解决 WordPress/Laravel 域名验证问题

### 问题描述

在多层代理环境下，后端应用（如 WordPress、Laravel）可能会接收到错误的 Host 头，导致：
- ✖️ URL 跳转错误
- ✖️ 静态资源加载失败
- ✖️ CSRF Token 验证失败

### 解决方案

#### 1. Nginx 层面（已配置）

```nginx
proxy_set_header Host $http_host;  # ✅ 传递原始 Host
proxy_redirect off;                # ✅ 不修改 Location 头
```

#### 2. WAF 层面（已实现）

天罡 WAF 在转发请求时会：
- ✅ 保留原始 `Host` 头
- ✅ 传递 `X-Forwarded-Host`、`X-Forwarded-Proto` 等头
- ✅ 支持「保留 Host 头」选项（域名映射配置）

#### 3. 应用层面（需配置）

**WordPress:**

在 `wp-config.php` 中添加：

```php
// 信任代理
if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
    $_SERVER['HTTPS'] = 'on';
}

// 如果 Host 头不正确，可手动设置
define('WP_HOME', 'https://your-domain.com');
define('WP_SITEURL', 'https://your-domain.com');
```

**Laravel:**

在 `app/Http/Middleware/TrustProxies.php` 中：

```php
<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    /**
     * 信任的代理
     */
    protected $proxies = '*';  // 或者指定 IP: ['192.168.1.1', '10.0.0.0/8']
    
    /**
     * 信任的头
     */
    protected $headers = Request::HEADER_X_FORWARDED_ALL;
}
```

在 `app/Http/Kernel.php` 中确保已启用：

```php
protected $middleware = [
    // ...
    \App\Http\Middleware\TrustProxies::class,
];
```

---

## 📊 监控和调试

### 查看 Nginx 日志

```bash
# 访问日志
tail -f /var/log/nginx/waf_access.log

# 错误日志（包含 Lua 输出）
tail -f /var/log/nginx/waf_error.log
```

### 查看 WAF 日志

```bash
tail -f /path/to/tiangang/runtime/logs/waf.log
tail -f /path/to/tiangang/runtime/logs/app.log
```

### 测试配置 API

```bash
# 获取域名映射配置
curl http://127.0.0.1:8989/admin/api/nginx/config

# 查看缓存状态
curl http://127.0.0.1:8788/_internal/cache-stats

# 刷新配置缓存
curl -X POST http://127.0.0.1:8989/admin/api/nginx/reload
```

### 测试 Host 头传递

```bash
# 通过 Nginx 访问
curl -H "Host: example.com" http://localhost/

# 检查后端接收到的头
# 在后端应用中输出 $_SERVER['HTTP_HOST']
```

---

## 🔥 常见问题

### 1. Lua 脚本找不到

**错误：**
```
module 'waf_config' not found
```

**解决：**
```bash
# 确认文件存在
ls -l /usr/local/openresty/lualib/waf_config.lua

# 确认权限
sudo chmod 644 /usr/local/openresty/lualib/waf_config.lua

# 检查 Lua 路径配置
nginx -V 2>&1 | grep lua_package_path
```

### 2. 配置未生效

**问题：** 修改了域名映射，但 Nginx 仍使用旧配置

**解决：**
```bash
# 手动刷新缓存
curl http://127.0.0.1:8788/_internal/refresh-config

# 或重启 OpenResty
sudo systemctl reload openresty
```

### 3. Host 头不正确

**问题：** 后端收到的 Host 是 WAF 的地址

**检查清单：**
- ☑ Nginx 配置：`proxy_set_header Host $http_host;`
- ☑ WAF 域名映射：启用「保留 Host 头」
- ☑ 应用配置：信任代理（TrustProxies）

### 4. SSL 证书问题

**问题：** HTTPS 证书不匹配

**解决：** 在 Nginx 配置中为每个域名设置证书：

```nginx
# 为 example.com 配置独立证书
server {
    listen 443 ssl http2;
    server_name example.com;
    
    ssl_certificate /path/to/example.com.crt;
    ssl_certificate_key /path/to/example.com.key;
    
    location / {
        proxy_pass http://tiangang_waf;
        proxy_set_header Host $http_host;
        # ...
    }
}
```

或使用泛域名证书：

```nginx
server {
    listen 443 ssl http2;
    server_name _;
    
    ssl_certificate /path/to/wildcard.crt;
    ssl_certificate_key /path/to/wildcard.key;
    
    location / {
        proxy_pass http://tiangang_waf;
        proxy_set_header Host $http_host;
        # ...
    }
}
```

---

## 📖 相关文档

- [完整部署文档](docs/nginx-deployment.md)
- [Nginx 配置示例](nginx/conf/waf.conf)
- [Lua 脚本说明](nginx/lua/waf_config.lua)
- [管理后台 - Nginx 模式](http://localhost:8989/admin/config/nginx-mode)

---

## 🎉 总结

采用前置 Nginx 模式后，天罡 WAF 的架构更加清晰：

1. **Nginx** 负责：
   - SSL/TLS 终止
   - 静态资源缓存
   - 负载均衡
   - 接收所有外部请求

2. **天罡 WAF** 负责：
   - 安全检测（SQL注入、XSS、CSRF等）
   - 请求转发到后端
   - 日志记录和审计

3. **Lua 脚本** 负责：
   - 从 WAF API 动态读取配置
   - 域名映射查找
   - 配置缓存管理

这样的架构既保证了性能，又确保了正确的 Host 头传递，完美解决了 WordPress/Laravel 等框架的域名验证问题！

---

**需要帮助？**
- 查看详细文档：`docs/nginx-deployment.md`
- 访问管理后台：`http://your-server:8989/admin/config/nginx-mode`
- 提交 Issue：GitHub Issues

