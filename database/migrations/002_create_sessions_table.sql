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

