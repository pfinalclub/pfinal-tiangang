# 天罡 WAF 部署文档

## 概述

天罡 WAF 是一个基于 PHP 8.2 和 Workerman 的高性能 Web 应用防火墙系统，支持异步处理、插件化架构和容器化部署。

## 系统要求

### 最低要求
- PHP 8.2+
- MySQL 8.0+
- Redis 6.0+
- 内存: 2GB+
- 磁盘: 10GB+

### 推荐配置
- PHP 8.2+
- MySQL 8.0+
- Redis 7.0+
- 内存: 8GB+
- 磁盘: 50GB+
- CPU: 4核心+

## 安装方式

### 1. 源码安装

#### 1.1 克隆代码
```bash
git clone https://github.com/your-org/tiangang-waf.git
cd tiangang-waf
```

#### 1.2 安装依赖
```bash
composer install --no-dev --optimize-autoloader
```

#### 1.3 配置环境
```bash
cp .env.example .env
# 编辑 .env 文件配置数据库和 Redis 连接
```

#### 1.4 初始化数据库
```bash
php artisan migrate
php artisan db:seed
```

#### 1.5 启动服务
```bash
php start.php start -d
```

### 2. Docker 部署

#### 2.1 使用 Docker Compose
```bash
# 克隆代码
git clone https://github.com/your-org/tiangang-waf.git
cd tiangang-waf

# 启动所有服务
docker-compose up -d

# 查看服务状态
docker-compose ps
```

#### 2.2 自定义配置
```bash
# 编辑 docker-compose.yml 文件
# 修改端口、密码等配置

# 重新启动服务
docker-compose down
docker-compose up -d
```

### 3. Kubernetes 部署

#### 3.1 创建命名空间
```bash
kubectl create namespace tiangang-waf
```

#### 3.2 部署配置
```bash
# 部署 ConfigMap
kubectl apply -f k8s/configmap.yaml

# 部署 Secret
kubectl apply -f k8s/secret.yaml

# 部署应用
kubectl apply -f k8s/deployment.yaml

# 部署服务
kubectl apply -f k8s/service.yaml

# 部署 Ingress
kubectl apply -f k8s/ingress.yaml
```

## 配置说明

### 1. 基础配置

#### 1.1 WAF 配置
```php
// config/waf.php
return [
    'enabled' => true,
    'timeout' => 5.0,
    'detection' => [
        'quick_enabled' => true,
        'async_enabled' => true,
        'timeout' => 5.0
    ],
    'rules' => [
        'sql_injection' => [
            'enabled' => true,
            'severity' => 'high'
        ],
        'xss' => [
            'enabled' => true,
            'severity' => 'high'
        ]
    ]
];
```

#### 1.2 代理配置
```php
// config/proxy.php
return [
    'enabled' => true,
    'timeout' => 30.0,
    'retry_count' => 3,
    'backends' => [
        'default' => [
            'host' => '127.0.0.1',
            'port' => 8080,
            'weight' => 100
        ]
    ]
];
```

#### 1.3 监控配置
```php
// config/monitoring.php
return [
    'enabled' => true,
    'metrics' => [
        'enabled' => true,
        'interval' => 60
    ],
    'alerts' => [
        'enabled' => true,
        'channels' => ['email', 'webhook']
    ]
];
```

### 2. 数据库配置

#### 2.1 MySQL 配置
```sql
-- 创建数据库
CREATE DATABASE tiangang CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 创建用户
CREATE USER 'tiangang'@'%' IDENTIFIED BY 'password';
GRANT ALL PRIVILEGES ON tiangang.* TO 'tiangang'@'%';
FLUSH PRIVILEGES;
```

#### 2.2 Redis 配置
```conf
# redis.conf
bind 0.0.0.0
port 6379
requirepass your_password
maxmemory 2gb
maxmemory-policy allkeys-lru
```

### 3. 安全配置

#### 3.1 SSL 证书
```bash
# 生成自签名证书
openssl req -x509 -newkey rsa:4096 -keyout key.pem -out cert.pem -days 365 -nodes

# 或使用 Let's Encrypt
certbot --nginx -d your-domain.com
```

#### 3.2 防火墙配置
```bash
# UFW 配置
ufw allow 22/tcp
ufw allow 80/tcp
ufw allow 443/tcp
ufw enable

# iptables 配置
iptables -A INPUT -p tcp --dport 80 -j ACCEPT
iptables -A INPUT -p tcp --dport 443 -j ACCEPT
iptables -A INPUT -p tcp --dport 22 -j ACCEPT
```

## 监控和日志

### 1. 监控配置

#### 1.1 Prometheus 配置
```yaml
# prometheus.yml
global:
  scrape_interval: 15s

scrape_configs:
  - job_name: 'tiangang-waf'
    static_configs:
      - targets: ['tiangang:80']
    metrics_path: '/metrics'
    scrape_interval: 5s
```

#### 1.2 Grafana 配置
```json
{
  "dashboard": {
    "title": "天罡 WAF 监控",
    "panels": [
      {
        "title": "请求量",
        "type": "graph",
        "targets": [
          {
            "expr": "rate(waf_requests_total[5m])"
          }
        ]
      }
    ]
  }
}
```

### 2. 日志配置

#### 2.1 应用日志
```php
// config/logging.php
return [
    'default' => 'stack',
    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => ['single', 'slack']
        ],
        'single' => [
            'driver' => 'single',
            'path' => '/var/log/tiangang/app.log',
            'level' => 'debug'
        ]
    ]
];
```

#### 2.2 ELK 配置
```yaml
# filebeat.yml
filebeat.inputs:
- type: log
  enabled: true
  paths:
    - /var/log/tiangang/*.log

output.elasticsearch:
  hosts: ["elasticsearch:9200"]

processors:
- add_host_metadata:
    when.not.contains.tags: forwarded
```

## 性能优化

### 1. PHP 优化

#### 1.1 OPcache 配置
```ini
; php.ini
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0
opcache.save_comments=0
```

#### 1.2 内存优化
```ini
; php.ini
memory_limit=512M
max_execution_time=300
max_input_time=300
```

### 2. 数据库优化

#### 2.1 MySQL 配置
```ini
# my.cnf
[mysqld]
innodb_buffer_pool_size=2G
innodb_log_file_size=256M
innodb_flush_log_at_trx_commit=2
query_cache_size=128M
query_cache_type=1
```

#### 2.2 Redis 配置
```conf
# redis.conf
maxmemory 2gb
maxmemory-policy allkeys-lru
tcp-keepalive 300
timeout 300
```

### 3. 系统优化

#### 3.1 内核参数
```bash
# /etc/sysctl.conf
net.core.somaxconn = 65535
net.core.netdev_max_backlog = 5000
net.ipv4.tcp_max_syn_backlog = 65535
net.ipv4.tcp_fin_timeout = 30
```

#### 3.2 文件描述符
```bash
# /etc/security/limits.conf
* soft nofile 65535
* hard nofile 65535
```

## 故障排查

### 1. 常见问题

#### 1.1 服务启动失败
```bash
# 检查日志
tail -f /var/log/tiangang/error.log

# 检查端口占用
netstat -tlnp | grep :80

# 检查进程
ps aux | grep tiangang
```

#### 1.2 性能问题
```bash
# 检查 CPU 使用
top -p $(pgrep tiangang)

# 检查内存使用
free -h

# 检查磁盘 I/O
iostat -x 1
```

#### 1.3 数据库连接问题
```bash
# 检查数据库连接
mysql -h localhost -u tiangang -p

# 检查 Redis 连接
redis-cli -h localhost -p 6379 -a password
```

### 2. 日志分析

#### 2.1 应用日志
```bash
# 查看错误日志
tail -f /var/log/tiangang/error.log

# 查看访问日志
tail -f /var/log/tiangang/access.log

# 查看安全日志
tail -f /var/log/tiangang/security.log
```

#### 2.2 系统日志
```bash
# 查看系统日志
journalctl -u tiangang -f

# 查看 Nginx 日志
tail -f /var/log/nginx/access.log
tail -f /var/log/nginx/error.log
```

## 备份和恢复

### 1. 数据备份

#### 1.1 数据库备份
```bash
# 创建备份脚本
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
mysqldump -u tiangang -p tiangang > /backup/tiangang_$DATE.sql
gzip /backup/tiangang_$DATE.sql
```

#### 1.2 配置文件备份
```bash
# 备份配置文件
tar -czf /backup/config_$(date +%Y%m%d).tar.gz /var/www/html/config/
```

### 2. 数据恢复

#### 2.1 数据库恢复
```bash
# 恢复数据库
gunzip /backup/tiangang_20231201_120000.sql.gz
mysql -u tiangang -p tiangang < /backup/tiangang_20231201_120000.sql
```

#### 2.2 配置文件恢复
```bash
# 恢复配置文件
tar -xzf /backup/config_20231201.tar.gz -C /
```

## 升级指南

### 1. 版本升级

#### 1.1 备份数据
```bash
# 备份数据库
mysqldump -u tiangang -p tiangang > backup.sql

# 备份配置文件
cp -r config/ config_backup/
```

#### 1.2 更新代码
```bash
# 拉取最新代码
git pull origin main

# 更新依赖
composer install --no-dev --optimize-autoloader
```

#### 1.3 数据库迁移
```bash
# 运行数据库迁移
php artisan migrate
```

#### 1.4 重启服务
```bash
# 重启服务
php start.php restart
```

## 安全建议

### 1. 系统安全

#### 1.1 用户权限
```bash
# 创建专用用户
useradd -r -s /bin/false tiangang
chown -R tiangang:tiangang /var/www/html
```

#### 1.2 文件权限
```bash
# 设置文件权限
chmod 755 /var/www/html
chmod 644 /var/www/html/config/*.php
chmod 600 /var/www/html/.env
```

### 2. 网络安全

#### 2.1 防火墙配置
```bash
# 只允许必要端口
ufw allow 22/tcp
ufw allow 80/tcp
ufw allow 443/tcp
ufw deny 3306/tcp
ufw deny 6379/tcp
```

#### 2.2 SSL 配置
```nginx
# nginx.conf
server {
    listen 443 ssl http2;
    ssl_certificate /path/to/cert.pem;
    ssl_certificate_key /path/to/key.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-RSA-AES256-GCM-SHA384:ECDHE-RSA-AES128-GCM-SHA256;
}
```

## 联系支持

- 文档: https://docs.tiangang-waf.com
- 问题反馈: https://github.com/your-org/tiangang-waf/issues
- 技术支持: support@tiangang-waf.com
