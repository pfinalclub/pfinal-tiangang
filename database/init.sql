-- 天罡 WAF 数据库初始化脚本
-- 创建数据库（如果不存在）
CREATE DATABASE IF NOT EXISTS `tiangang_waf` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `tiangang_waf`;

-- 用户表
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '用户ID',
  `username` VARCHAR(50) NOT NULL COMMENT '用户名',
  `password` VARCHAR(255) NOT NULL COMMENT '密码哈希',
  `email` VARCHAR(100) DEFAULT NULL COMMENT '邮箱',
  `real_name` VARCHAR(50) DEFAULT NULL COMMENT '真实姓名',
  `role` VARCHAR(20) NOT NULL DEFAULT 'user' COMMENT '角色：admin, waf_admin, user',
  `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '状态：1=启用，0=禁用',
  `last_login_at` DATETIME DEFAULT NULL COMMENT '最后登录时间',
  `last_login_ip` VARCHAR(45) DEFAULT NULL COMMENT '最后登录IP',
  `login_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '登录次数',
  `failed_login_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '失败登录次数',
  `locked_until` DATETIME DEFAULT NULL COMMENT '锁定到期时间',
  `remember_token` VARCHAR(100) DEFAULT NULL COMMENT '记住我Token',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_username` (`username`),
  UNIQUE KEY `uk_email` (`email`),
  KEY `idx_status` (`status`),
  KEY `idx_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户表';

-- 插入默认管理员账户
-- 密码：admin123
INSERT INTO `users` (`username`, `password`, `email`, `real_name`, `role`, `status`) VALUES
('admin', '$2y$12$YLJMW2EePx8Oa7uMkbfvne1lzmpAxlo5lruaERM.qPLv78L/Dpuu2', 'admin@tiangang.local', '系统管理员', 'admin', 1),
-- 密码：waf2024
('waf', '$2y$12$QSzjcbBTJZhDqNplSbDXQ.ve.Lqv0dQVztsu3INh8wnNV.C6md216', 'waf@tiangang.local', 'WAF管理员', 'waf_admin', 1),
-- 密码：tiangang2024
('tiangang', '$2y$12$41ejLNKxqpeXerzfm.3H.el.RVxCHXMGqDUhQQNeUBHAEXdznZUs.', 'tiangang@tiangang.local', '天罡管理员', 'admin', 1)
ON DUPLICATE KEY UPDATE `username`=`username`;

-- 会话表（可选，用于数据库存储会话）
CREATE TABLE IF NOT EXISTS `sessions` (
  `id` VARCHAR(128) NOT NULL COMMENT '会话ID',
  `user_id` INT UNSIGNED NOT NULL COMMENT '用户ID',
  `ip_address` VARCHAR(45) NOT NULL COMMENT 'IP地址',
  `user_agent` VARCHAR(255) DEFAULT NULL COMMENT 'User Agent',
  `payload` TEXT NOT NULL COMMENT '会话数据',
  `last_activity` INT UNSIGNED NOT NULL COMMENT '最后活动时间',
  `expires_at` INT UNSIGNED NOT NULL COMMENT '过期时间',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_expires_at` (`expires_at`),
  KEY `idx_last_activity` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='会话表';

