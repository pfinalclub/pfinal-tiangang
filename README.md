# 天罡 · WAF (Tiangang WAF)

天罡是一款自研可扩展的边缘 WAF 和反向代理，基于 Workerman + pfinal-asyncio 的高并发混合架构。
目标：提供企业级的自托管防护能力（规则管理、动态挑战、误报回放与审计），便于灰度启用与规则热更新。

## 🚀 核心特性

- **高性能代理**：基于 Workerman 的事件驱动模型
- **混合架构**：核心功能同步 + 后台任务异步
- **异步规则引擎**：使用 pfinal-asyncio 进行并发检测
- **Web管理界面**：现代化响应式管理控制台
- **规则热加载**：支持动态更新检测规则
- **事件采样与审计**：完整的日志记录和监控
- **可扩展插件机制**：类似 SafeLine 的插件系统
- **离线模式**：无需数据库即可运行

## 🏗️ 推荐部署

```
边缘（TLS 终结）→ 天罡（数据平面）→ 后端（Webman/应用）
```

## 🚀 快速开始

### 安装依赖

```bash
composer install
```

### 配置环境（可选）

```bash
cp env.example .env
# 编辑 .env 文件配置数据库和 Redis（可选，支持离线模式）
```

### 启动服务

```bash
php start.php start
```

### 访问服务

```bash
# 访问Web管理界面
curl http://localhost:8787/

# 访问API接口
curl http://localhost:8787/api/dashboard

# 健康检查
curl http://localhost:8787/health
```

### 🌐 Web管理界面

访问 `http://localhost:8787/` 查看现代化管理界面：

- 📊 **实时监控**：请求统计、拦截率、响应时间
- 🔒 **安全报告**：威胁分析、攻击统计
- ⚡ **性能分析**：系统状态、资源使用
- 📤 **数据导出**：支持JSON、CSV、XML格式

### 🧪 测试功能

```bash
# 运行单元测试
php tests/run_unit_tests.php

# 运行集成测试
php tests/run_all_tests.php

# 性能基准测试
php tests/performance/benchmark.php

# 生成测试报告
php tests/coverage_report.php
```

### 🔧 规则管理

```bash
# 测试WAF规则
curl "http://localhost:8787/?test=<script>alert('xss')</script>"

# 测试SQL注入检测
curl "http://localhost:8787/?id=1' OR '1'='1"

# 测试频率限制
for i in {1..10}; do curl http://localhost:8787/; done
```

## ⚡ 性能特点

### 混合架构优势

| 特性 | 同步核心 | 异步后台 | 混合架构 |
|------|----------|----------|----------|
| **稳定性** | ⭐⭐⭐⭐⭐ | ⭐⭐⭐ | ⭐⭐⭐⭐⭐ |
| **性能** | ⭐⭐⭐ | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐ |
| **可维护性** | ⭐⭐⭐⭐⭐ | ⭐⭐ | ⭐⭐⭐⭐ |
| **扩展性** | ⭐⭐ | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐ |

### 🚀 技术优势

- **混合架构**：核心功能同步处理，后台任务异步执行
- **高性能**：基于Workerman事件驱动模型
- **异步检测**：使用pfinal-asyncio进行并发检测
- **离线模式**：无需数据库即可运行
- **现代化UI**：响应式Web管理界面

## 📁 项目结构

```
天罡WAF/
├── 🚪 入口层
│   ├── start.php              # 服务器启动入口
│   └── app/gateway/           # 核心网关
│       └── TiangangGateway.php
│
├── 🛡️ WAF核心层
│   ├── app/middleware/        # WAF中间件
│   ├── app/detectors/         # 检测器
│   │   ├── QuickDetector.php  # 快速检测
│   │   └── AsyncDetector.php  # 异步检测
│   └── app/core/              # 核心组件
│       ├── WafResult.php      # 检测结果
│       ├── DecisionEngine.php # 决策引擎
│       └── CoroutinePool.php  # 协程池
│
├── 🔌 插件系统
│   ├── app/plugins/           # 插件管理
│   └── plugins/waf/           # WAF规则插件
│
├── 🌐 Web管理界面
│   ├── app/web/               # Web控制台
│   │   ├── routes/WebRoutes.php
│   │   └── controllers/DashboardController.php
│   └── app/api/               # REST API
│
├── 📊 监控与日志
│   ├── app/monitoring/        # 监控系统
│   ├── app/logging/           # 日志系统
│   └── app/performance/       # 性能分析
│
└── ⚙️ 配置与测试
    ├── config/                # 配置文件
    ├── tests/                 # 测试代码
    └── docs/                  # 文档
```

## 🎯 功能特性

### ✅ 已完成功能

- [x] **基础架构**：Workerman + 异步协程
- [x] **WAF核心**：检测引擎 + 决策系统
- [x] **代理功能**：反向代理 + 负载均衡
- [x] **Web界面**：管理控制台 + API
- [x] **监控系统**：性能分析 + 日志记录
- [x] **测试框架**：单元测试 + 集成测试
- [x] **混合架构**：同步核心 + 异步后台
- [x] **离线模式**：无需数据库即可运行

### 🚀 技术亮点

- **混合架构**：核心功能同步处理，后台任务异步执行
- **插件系统**：可扩展规则引擎
- **Web管理**：现代化管理界面
- **实时监控**：完整的监控体系
- **企业级**：生产环境就绪

## 📚 API文档

### Web管理界面

- **主页**：`GET /` - 管理控制台界面
- **仪表板**：`GET /dashboard` - 仪表板页面
- **健康检查**：`GET /health` - 系统健康状态

### REST API

- **仪表板数据**：`GET /api/dashboard` - 获取仪表板数据
- **性能报告**：`GET /api/performance?period=1h` - 获取性能报告
- **安全报告**：`GET /api/security?period=1d` - 获取安全报告
- **数据导出**：`GET /api/export?type=dashboard&format=json` - 导出数据

### 响应格式

```json
{
  "success": true,
  "data": {
    "overview": {
      "total_requests": 1500,
      "blocked_requests": 50,
      "block_rate": 3.3
    },
    "performance": {
      "avg_response_time": 120,
      "throughput": 250
    }
  },
  "timestamp": 1640995200
}
```

## 🐳 Docker部署

### 使用Docker Compose

```bash
# 启动所有服务
docker-compose up -d

# 查看服务状态
docker-compose ps

# 查看日志
docker-compose logs -f tiangang
```

### 环境变量配置

```bash
# .env 文件配置
WAF_ENABLED=true
SERVER_HOST=0.0.0.0
SERVER_PORT=8787
BACKEND_URL=http://backend:8080
REDIS_HOST=redis
REDIS_PORT=6379
```

## 📊 监控与运维

### 性能监控

- **响应时间**：毫秒级检测响应
- **吞吐量**：高并发请求处理
- **内存使用**：资源使用监控
- **错误率**：异常请求统计

### 日志管理

- **访问日志**：请求/响应记录
- **安全日志**：攻击检测记录
- **错误日志**：系统异常记录
- **性能日志**：性能指标记录

### 告警配置

```php
// 配置告警阈值
'monitoring' => [
    'alerts' => [
        'block_rate' => 0.1,      // 10% 拦截率告警
        'response_time' => 1000,  // 1秒响应时间告警
        'error_rate' => 0.05,     // 5% 错误率告警
    ]
]
```

## 🤝 贡献指南

### 开发环境

```bash
# 克隆项目
git clone https://github.com/your-org/tiangang-waf.git
cd tiangang-waf

# 安装依赖
composer install

# 运行测试
php tests/run_all_tests.php

# 启动开发服务器
php start.php start
```

### 代码规范

- 遵循PSR-12代码规范
- 使用PHP 8.3+特性
- 编写完整的单元测试
- 添加详细的文档注释

## 📄 许可证

本项目采用 MIT 许可证 - 查看 [LICENSE](LICENSE) 文件了解详情。

## 🙏 致谢

- [Workerman](https://github.com/walkor/Workerman) - 高性能网络框架
- [pfinal-asyncio](https://github.com/pfinalclub/asyncio) - 异步协程库
- [Monolog](https://github.com/Seldaek/monolog) - 日志处理
- [Guzzle](https://github.com/guzzle/guzzle) - HTTP客户端
