# 天罡 WAF API 文档

## 概述

天罡 WAF 提供完整的 REST API 接口，支持规则管理、配置管理、插件管理和系统监控等功能。

## 基础信息

- **Base URL**: `https://your-domain.com/api/v1`
- **认证方式**: Bearer Token
- **数据格式**: JSON
- **字符编码**: UTF-8

## 认证

### 获取访问令牌

```http
POST /api/v1/auth/login
Content-Type: application/json

{
    "username": "admin",
    "password": "password"
}
```

**响应示例:**
```json
{
    "success": true,
    "data": {
        "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
        "expires_in": 3600,
        "token_type": "Bearer"
    }
}
```

### 使用访问令牌

```http
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
```

## 规则管理 API

### 获取所有规则

```http
GET /api/v1/rules
```

**响应示例:**
```json
{
    "success": true,
    "data": {
        "rules": [
            {
                "id": "rule_001",
                "name": "SQL Injection Detection",
                "type": "sql_injection",
                "pattern": "/union.*select/i",
                "enabled": true,
                "severity": "high",
                "created_at": "2023-12-01 10:00:00",
                "updated_at": "2023-12-01 10:00:00"
            }
        ],
        "stats": {
            "total_rules": 10,
            "enabled_rules": 8,
            "disabled_rules": 2
        },
        "total_count": 10
    },
    "timestamp": "2023-12-01 12:00:00"
}
```

### 获取单个规则

```http
GET /api/v1/rules/{id}
```

**响应示例:**
```json
{
    "success": true,
    "data": {
        "rule": {
            "id": "rule_001",
            "name": "SQL Injection Detection",
            "type": "sql_injection",
            "pattern": "/union.*select/i",
            "enabled": true,
            "severity": "high"
        },
        "details": {
            "usage_stats": {
                "total_usage": 150,
                "blocked_count": 25,
                "avg_duration": 0.05
            },
            "test_history": [
                {
                    "test_data": "1' UNION SELECT * FROM users",
                    "test_result": "blocked",
                    "tested_at": "2023-12-01 11:00:00"
                }
            ]
        }
    }
}
```

### 创建规则

```http
POST /api/v1/rules
Content-Type: application/json

{
    "name": "XSS Detection",
    "type": "xss",
    "pattern": "/<script.*?>/i",
    "enabled": true,
    "severity": "high",
    "description": "Detect XSS attacks"
}
```

**响应示例:**
```json
{
    "success": true,
    "data": {
        "rule_id": "rule_002",
        "rule": {
            "name": "XSS Detection",
            "type": "xss",
            "pattern": "/<script.*?>/i",
            "enabled": true,
            "severity": "high"
        },
        "test_result": {
            "test_cases": [
                {
                    "input": "<script>alert('xss')</script>",
                    "expected": true,
                    "actual": true,
                    "passed": true
                }
            ],
            "overall_result": "passed"
        }
    },
    "message": "Rule created successfully"
}
```

### 更新规则

```http
PUT /api/v1/rules/{id}
Content-Type: application/json

{
    "name": "Updated XSS Detection",
    "pattern": "/<script.*?>.*?<\/script>/i",
    "severity": "critical"
}
```

### 删除规则

```http
DELETE /api/v1/rules/{id}
```

### 测试规则

```http
POST /api/v1/rules/{id}/test
Content-Type: application/json

{
    "input": "1' UNION SELECT * FROM users",
    "expected": true
}
```

## 配置管理 API

### 获取所有配置

```http
GET /api/v1/config
```

**响应示例:**
```json
{
    "success": true,
    "data": {
        "configs": {
            "waf": {
                "enabled": true,
                "timeout": 5.0,
                "detection": {
                    "quick_enabled": true,
                    "async_enabled": true
                }
            },
            "proxy": {
                "enabled": true,
                "timeout": 30.0,
                "retry_count": 3
            }
        },
        "stats": {
            "total_configs": 15,
            "config_categories": {
                "waf": 5,
                "proxy": 3,
                "monitoring": 4,
                "logging": 3
            }
        },
        "total_count": 15
    }
}
```

### 获取单个配置

```http
GET /api/v1/config/{key}
```

**响应示例:**
```json
{
    "success": true,
    "data": {
        "key": "waf.timeout",
        "value": 5.0,
        "details": {
            "history": [
                {
                    "old_value": "3.0",
                    "new_value": "5.0",
                    "changed_at": "2023-12-01 10:00:00",
                    "changed_by": "admin"
                }
            ],
            "usage_stats": {
                "change_count": 3,
                "last_changed": "2023-12-01 10:00:00"
            }
        }
    }
}
```

### 更新配置

```http
PUT /api/v1/config/{key}
Content-Type: application/json

{
    "value": 10.0
}
```

### 批量更新配置

```http
POST /api/v1/config/batch
Content-Type: application/json

{
    "waf.timeout": 10.0,
    "proxy.timeout": 60.0,
    "monitoring.interval": 30
}
```

### 重置配置

```http
DELETE /api/v1/config/{key}/reset
```

### 重新加载配置

```http
POST /api/v1/config/reload
```

## 插件管理 API

### 获取所有插件

```http
GET /api/v1/plugins
```

**响应示例:**
```json
{
    "success": true,
    "data": {
        "plugins": [
            {
                "id": "plugin_001",
                "name": "SQL Injection Detector",
                "type": "detector",
                "version": "1.0.0",
                "enabled": true,
                "description": "Advanced SQL injection detection"
            }
        ],
        "stats": {
            "total_plugins": 5,
            "enabled_plugins": 4,
            "disabled_plugins": 1,
            "plugin_types": {
                "detector": 3,
                "rule": 1,
                "action": 1
            }
        },
        "total_count": 5
    }
}
```

### 获取单个插件

```http
GET /api/v1/plugins/{id}
```

### 安装插件

```http
POST /api/v1/plugins
Content-Type: application/json

{
    "name": "Custom Detector",
    "type": "detector",
    "version": "1.0.0",
    "enabled": true,
    "description": "Custom detection plugin"
}
```

### 更新插件

```http
PUT /api/v1/plugins/{id}
Content-Type: application/json

{
    "version": "1.1.0",
    "description": "Updated custom detection plugin"
}
```

### 启用插件

```http
PUT /api/v1/plugins/{id}/enable
```

### 禁用插件

```http
PUT /api/v1/plugins/{id}/disable
```

### 卸载插件

```http
DELETE /api/v1/plugins/{id}
```

### 重新加载插件

```http
POST /api/v1/plugins/reload
```

## 仪表板 API

### 获取仪表板数据

```http
GET /api/v1/dashboard
```

**响应示例:**
```json
{
    "success": true,
    "data": {
        "system_overview": {
            "status": "healthy",
            "uptime": "7 days, 12 hours",
            "version": "1.0.0",
            "components": {
                "waf_middleware": {
                    "status": "running",
                    "uptime": "7d 12h"
                },
                "proxy_handler": {
                    "status": "running",
                    "uptime": "7d 12h"
                }
            }
        },
        "performance_metrics": {
            "response_time": {
                "current": 45.2,
                "average": 42.8,
                "trend": "stable"
            },
            "throughput": {
                "current": 1250,
                "average": 1180,
                "trend": "increasing"
            }
        },
        "security_stats": {
            "total_requests": 10000,
            "blocked_requests": 150,
            "block_rate": 1.5,
            "threats": {
                "sql_injection": 50,
                "xss": 30,
                "rate_limit": 70
            }
        }
    }
}
```

### 获取性能报告

```http
GET /api/v1/dashboard/performance?period=1h
```

### 获取安全报告

```http
GET /api/v1/dashboard/security?period=1d
```

### 导出数据

```http
GET /api/v1/dashboard/export?type=dashboard&format=json
```

## 错误处理

### 错误响应格式

```json
{
    "success": false,
    "error": "Error message",
    "details": "Detailed error information",
    "code": 400,
    "timestamp": "2023-12-01 12:00:00"
}
```

### 常见错误码

| 状态码 | 说明 |
|--------|------|
| 200 | 成功 |
| 400 | 请求参数错误 |
| 401 | 未授权 |
| 403 | 禁止访问 |
| 404 | 资源不存在 |
| 422 | 数据验证失败 |
| 500 | 服务器内部错误 |

## 限流

### 限流规则

- **每分钟**: 100 请求
- **每小时**: 1000 请求
- **每天**: 10000 请求

### 限流响应

```json
{
    "success": false,
    "error": "Rate limit exceeded",
    "details": "Too many requests. Please try again later.",
    "code": 429,
    "retry_after": 60
}
```

## 示例代码

### JavaScript (Fetch API)

```javascript
// 获取所有规则
async function getRules() {
    const response = await fetch('/api/v1/rules', {
        headers: {
            'Authorization': 'Bearer ' + token,
            'Content-Type': 'application/json'
        }
    });
    return await response.json();
}

// 创建规则
async function createRule(ruleData) {
    const response = await fetch('/api/v1/rules', {
        method: 'POST',
        headers: {
            'Authorization': 'Bearer ' + token,
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(ruleData)
    });
    return await response.json();
}
```

### Python (requests)

```python
import requests

# 获取所有规则
def get_rules():
    headers = {
        'Authorization': f'Bearer {token}',
        'Content-Type': 'application/json'
    }
    response = requests.get('/api/v1/rules', headers=headers)
    return response.json()

# 创建规则
def create_rule(rule_data):
    headers = {
        'Authorization': f'Bearer {token}',
        'Content-Type': 'application/json'
    }
    response = requests.post('/api/v1/rules', 
                           headers=headers, 
                           json=rule_data)
    return response.json()
```

### PHP (cURL)

```php
<?php
// 获取所有规则
function getRules($token) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, '/api/v1/rules');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

// 创建规则
function createRule($token, $ruleData) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, '/api/v1/rules');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($ruleData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}
?>
```

## 更新日志

### v1.0.0 (2023-12-01)
- 初始版本发布
- 支持规则管理 API
- 支持配置管理 API
- 支持插件管理 API
- 支持仪表板 API

## 联系支持

- 文档: https://docs.tiangang-waf.com/api
- 问题反馈: https://github.com/your-org/tiangang-waf/issues
- 技术支持: api-support@tiangang-waf.com
