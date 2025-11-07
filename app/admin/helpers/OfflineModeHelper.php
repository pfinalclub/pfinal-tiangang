<?php

namespace app\admin\helpers;

use app\admin\config\Database;

/**
 * 离线模式辅助类
 * 
 * 提供离线模式相关的工具方法
 */
class OfflineModeHelper
{
    /**
     * 检查是否处于离线模式
     */
    public static function isOfflineMode(): bool
    {
        return !Database::isAvailable();
    }
    
    /**
     * 获取离线模式下的默认用户列表
     */
    public static function getOfflineUsers(): array
    {
        return [
            'admin' => [
                'password_hash' => '$2y$12$YLJMW2EePx8Oa7uMkbfvne1lzmpAxlo5lruaERM.qPLv78L/Dpuu2', // admin123
                'role' => 'admin',
                'email' => 'admin@tiangang.local',
                'real_name' => '系统管理员',
            ],
            'waf' => [
                'password_hash' => '$2y$12$QSzjcbBTJZhDqNplSbDXQ.ve.Lqv0dQVztsu3INh8wnNV.C6md216', // waf2024
                'role' => 'waf_admin',
                'email' => 'waf@tiangang.local',
                'real_name' => 'WAF管理员',
            ],
            'tiangang' => [
                'password_hash' => '$2y$12$41ejLNKxqpeXerzfm.3H.el.RVxCHXMGqDUhQQNeUBHAEXdznZUs.', // tiangang2024
                'role' => 'admin',
                'email' => 'tiangang@tiangang.local',
                'real_name' => '天罡管理员',
            ],
        ];
    }
    
    /**
     * 验证离线模式下的用户凭据
     */
    public static function validateOfflineUser(string $username, string $password): ?array
    {
        $users = self::getOfflineUsers();
        
        if (!isset($users[$username])) {
            // 即使用户不存在，也执行一次哈希验证以保持时间一致（防止时间攻击）
            password_verify($password, '$2y$10$dummy_hash_for_timing_attack_prevention');
            return null;
        }
        
        $user = $users[$username];
        
        if (password_verify($password, $user['password_hash'])) {
            return [
                'id' => 0, // 离线模式下使用虚拟 ID
                'username' => $username,
                'email' => $user['email'],
                'real_name' => $user['real_name'],
                'role' => $user['role'],
                'status' => 1,
            ];
        }
        
        return null;
    }
    
    /**
     * 获取离线模式状态信息
     */
    public static function getStatusInfo(): array
    {
        return [
            'is_offline' => self::isOfflineMode(),
            'database_available' => Database::isAvailable(),
            'offline_users_count' => count(self::getOfflineUsers()),
            'message' => self::isOfflineMode() 
                ? '系统运行在离线模式，使用文件存储和硬编码账户' 
                : '系统运行在数据库模式，使用数据库存储',
        ];
    }
}

