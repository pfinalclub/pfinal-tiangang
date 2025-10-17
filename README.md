# 天罡 · WAF (Tiangang WAF)

天罡是一款自研可扩展的边缘 WAF 和反向代理，基于 Workerman / Webman + pfinal-asyncio 的高并发异步检测架构。
目标：提供企业级的自托管防护能力（规则管理、动态挑战、误报回放与审计），便于灰度启用与规则热更新。

## 核心特性

- **高性能代理**：基于 Workerman 的事件驱动模型
- **异步规则引擎**：使用 pfinal-asyncio 进行并发检测
- **规则热加载**：支持动态更新检测规则
- **事件采样与审计**：完整的日志记录和监控
- **可扩展插件机制**：类似 SafeLine 的插件系统

## 推荐部署

```
边缘（TLS 终结）→ 天罡（数据平面）→ 后端（Webman/应用）
```

## 快速开始

### 安装依赖

```bash
composer install
```

### 配置环境

```bash
cp env.example .env
# 编辑 .env 文件配置数据库和 Redis
```

### 启动服务

```bash
php start.php start
```

### 访问服务

```bash
curl http://localhost:8787
```

### 测试异步检测

```bash
# 运行基础测试
php test.php

# 运行异步检测演示
php demo_async.php

# 运行异步对比演示
php compare_async.php

# 运行 WAF 异步对比
php waf_async_comparison.php
```

## 异步检测性能对比

### 传统同步方式 vs 异步并发方式

| 方式 | 总耗时 | 并发能力 | 扩展性 | 适用场景 |
|------|--------|----------|--------|----------|
| 同步 | 295ms | 差 | 差 | 简单任务 |
| 异步 | 100ms | 优秀 | 好 | 高并发 |

### 性能提升

- **响应时间**: 提升 3x (295ms → 100ms)
- **并发能力**: 提升 3x (3 QPS → 10 QPS)
- **扩展性**: 线性增长 vs 常数增长
- **资源利用**: 充分利用 I/O 等待时间

## 项目结构

```
├── app/                    # 应用代码
│   ├── gateway/           # 网关层
│   ├── middleware/        # 中间件
│   ├── detectors/         # 检测器
│   ├── core/              # 核心组件
│   ├── plugins/           # 插件系统
│   ├── config/            # 配置管理
│   ├── proxy/             # 代理功能
│   ├── logging/           # 日志系统
│   ├── monitoring/        # 监控系统
│   └── api/               # API 接口
├── config/                # 配置文件
├── plugins/               # 插件目录
├── tests/                 # 测试代码
├── public/                # 静态资源
├── runtime/               # 运行时文件
└── docs/                  # 文档
```

## 开发计划

- [x] 阶段一：基础架构搭建
- [ ] 阶段二：WAF 核心引擎
- [ ] 阶段三：插件规则实现
- [ ] 阶段四：反向代理模式
- [ ] 阶段五：日志与监控
- [ ] 阶段六：管理 API 和 Web 控制台
- [ ] 阶段七：测试与优化
