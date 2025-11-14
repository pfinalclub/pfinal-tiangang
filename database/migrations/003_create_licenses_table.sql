-- 许可证表
CREATE TABLE IF NOT EXISTS `licenses` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '许可证ID',
  `license_key` VARCHAR(255) NOT NULL COMMENT '许可证密钥',
  `plugin_name` VARCHAR(100) NOT NULL COMMENT '插件名称',
  `plugin_version` VARCHAR(20) DEFAULT NULL COMMENT '插件版本',
  `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '状态：1=启用，0=禁用',
  `valid_from` DATETIME NOT NULL COMMENT '生效时间',
  `valid_until` DATETIME NOT NULL COMMENT '过期时间',
  `max_instances` INT UNSIGNED DEFAULT 1 COMMENT '最大实例数',
  `features` JSON DEFAULT NULL COMMENT '功能特性',
  `metadata` JSON DEFAULT NULL COMMENT '元数据',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_license_key` (`license_key`),
  KEY `idx_plugin_name` (`plugin_name`),
  KEY `idx_status` (`status`),
  KEY `idx_valid_until` (`valid_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='许可证表';

-- 许可证使用记录表
CREATE TABLE IF NOT EXISTS `license_usage` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '使用记录ID',
  `license_id` INT UNSIGNED NOT NULL COMMENT '许可证ID',
  `instance_id` VARCHAR(255) NOT NULL COMMENT '实例ID',
  `plugin_name` VARCHAR(100) NOT NULL COMMENT '插件名称',
  `action` VARCHAR(50) NOT NULL COMMENT '操作类型：activate, deactivate, check, validate',
  `ip_address` VARCHAR(45) DEFAULT NULL COMMENT 'IP地址',
  `user_agent` VARCHAR(255) DEFAULT NULL COMMENT 'User Agent',
  `result` TINYINT(1) NOT NULL COMMENT '结果：1=成功，0=失败',
  `error_message` TEXT DEFAULT NULL COMMENT '错误信息',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_license_id` (`license_id`),
  KEY `idx_plugin_name` (`plugin_name`),
  KEY `idx_action` (`action`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_license_usage_license` FOREIGN KEY (`license_id`) REFERENCES `licenses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='许可证使用记录表';

-- 插件配置表
CREATE TABLE IF NOT EXISTS `plugin_configs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '配置ID',
  `plugin_name` VARCHAR(100) NOT NULL COMMENT '插件名称',
  `config_key` VARCHAR(100) NOT NULL COMMENT '配置键',
  `config_value` TEXT DEFAULT NULL COMMENT '配置值',
  `config_type` VARCHAR(20) DEFAULT 'string' COMMENT '配置类型：string, int, bool, json',
  `description` VARCHAR(255) DEFAULT NULL COMMENT '描述',
  `is_encrypted` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否加密：1=是，0=否',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_plugin_config` (`plugin_name`, `config_key`),
  KEY `idx_plugin_name` (`plugin_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='插件配置表';

-- 插入默认的WAF插件许可证（用于测试）
INSERT INTO `licenses` (
  `license_key`, 
  `plugin_name`, 
  `plugin_version`, 
  `status`, 
  `valid_from`, 
  `valid_until`, 
  `max_instances`, 
  `features`
) VALUES (
  'WAF-PLUGIN-DEMO-KEY-2024', 
  'waf', 
  '1.0.0', 
  1, 
  NOW(), 
  DATE_ADD(NOW(), INTERVAL 1 YEAR), 
  10, 
  '{"ip_blacklist": true, "rate_limit": true, "sql_injection": true, "xss": true}'
);

-- 插入默认的WAF插件配置
INSERT INTO `plugin_configs` (
  `plugin_name`, 
  `config_key`, 
  `config_value`, 
  `config_type`, 
  `description`
) VALUES 
('waf', 'enabled', '1', 'bool', 'WAF插件是否启用'),
('waf', 'block_threshold', '80', 'int', '拦截阈值'),
('waf', 'log_level', 'info', 'string', '日志级别'),
('waf', 'rate_limit_enabled', '1', 'bool', '是否启用速率限制'),
('waf', 'rate_limit_requests', '100', 'int', '每分钟请求限制'),
('waf', 'ip_blacklist_enabled', '1', 'bool', '是否启用IP黑名单'),
('waf', 'sql_injection_enabled', '1', 'bool', '是否启用SQL注入检测'),
('waf', 'xss_enabled', '1', 'bool', '是否启用XSS检测');