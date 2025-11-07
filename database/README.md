# 数据库初始化说明

## 概述

天罡 WAF 使用 MySQL 数据库存储用户信息。系统支持离线模式（无数据库），但建议在生产环境中配置数据库。

## 快速开始

### 1. 创建数据库

```bash
# 使用 MySQL 客户端
mysql -u root -p

# 创建数据库
CREATE DATABASE IF NOT EXISTS `tiangang_waf` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 2. 导入初始化脚本

```bash
# 方法1：使用 MySQL 客户端
mysql -u root -p tiangang_waf < database/init.sql

# 方法2：使用 Docker（如果使用 docker-compose）
docker exec -i tiangang-mysql mysql -uroot -proot_password tiangang_waf < database/init.sql
```

### 3. 配置环境变量

编辑 `.env` 文件，配置数据库连接信息：

```env
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=tiangang_waf
DB_USERNAME=root
DB_PASSWORD=your_password
```

## 数据库表结构

### users 表

用户表，存储管理员账户信息。

**字段说明**：
- `id`: 用户ID（主键）
- `username`: 用户名（唯一）
- `password`: 密码哈希（使用 password_hash 生成）
- `email`: 邮箱（唯一，可选）
- `real_name`: 真实姓名（可选）
- `role`: 角色（admin, waf_admin, user）
- `status`: 状态（1=启用，0=禁用）
- `last_login_at`: 最后登录时间
- `last_login_ip`: 最后登录IP
- `login_count`: 登录次数
- `failed_login_count`: 失败登录次数
- `locked_until`: 锁定到期时间
- `remember_token`: 记住我Token
- `created_at`: 创建时间
- `updated_at`: 更新时间

### sessions 表（可选）

会话表，用于数据库存储会话（当前使用文件存储，此表为可选）。

## 默认账户

初始化脚本会创建以下默认管理员账户：

| 用户名 | 密码 | 角色 | 说明 |
|--------|------|------|------|
| admin | admin123 | admin | 系统管理员 |
| waf | waf2024 | waf_admin | WAF管理员 |
| tiangang | tiangang2024 | admin | 天罡管理员 |

**⚠️ 重要提示**：
- 生产环境请立即修改默认密码
- 建议删除或禁用不需要的默认账户
- 使用强密码策略

## 离线模式

如果数据库不可用，系统会自动切换到离线模式：
- 使用硬编码的默认账户（仅用于开发/测试）
- 会话存储在文件系统中
- 功能受限，建议生产环境配置数据库

## 迁移文件

数据库迁移文件位于 `database/migrations/` 目录：

- `001_create_users_table.sql` - 创建用户表
- `002_create_sessions_table.sql` - 创建会话表（可选）

## 密码哈希生成

如果需要创建新用户或修改密码，可以使用以下 PHP 代码生成密码哈希：

```php
<?php
// 生成密码哈希
$password = 'your_password';
$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
echo $hash;
```

或者使用命令行：

```bash
php -r "echo password_hash('your_password', PASSWORD_BCRYPT, ['cost' => 12]);"
```

## 安全建议

1. **修改默认密码**：首次部署后立即修改所有默认账户密码
2. **使用强密码**：密码长度至少12位，包含大小写字母、数字和特殊字符
3. **定期更新密码**：建议每3-6个月更新一次密码
4. **限制数据库访问**：只允许必要的IP访问数据库
5. **启用SSL**：生产环境使用SSL连接数据库
6. **定期备份**：定期备份数据库，防止数据丢失

## 故障排查

### 数据库连接失败

1. 检查数据库服务是否运行
2. 验证 `.env` 文件中的数据库配置
3. 检查数据库用户权限
4. 查看错误日志：`runtime/logs/app.log`

### 用户无法登录

1. 检查用户是否存在：`SELECT * FROM users WHERE username = 'your_username';`
2. 检查用户状态：确保 `status = 1`
3. 检查用户是否被锁定：查看 `locked_until` 字段
4. 验证密码哈希是否正确

### 性能问题

1. 确保数据库表有适当的索引
2. 定期清理过期会话
3. 考虑使用 Redis 缓存用户信息

