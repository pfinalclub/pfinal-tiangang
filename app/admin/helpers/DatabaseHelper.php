<?php

namespace app\admin\helpers;

use app\admin\models\User;
use app\admin\config\Database;

/**
 * 数据库辅助类（使用 ORM，支持离线模式）
 * 
 * 提供数据库操作的便捷方法，使用 Illuminate Database ORM
 * 支持离线模式：数据库不可用时自动降级到文件存储
 */
class DatabaseHelper
{
    /**
     * 初始化数据库连接
     */
    public static function initialize(): void
    {
        Database::initialize();
    }
    
    /**
     * 检查数据库是否可用
     */
    public static function isAvailable(): bool
    {
        return Database::isAvailable();
    }
    
    /**
     * 查询用户信息（使用 ORM）
     */
    public static function getUserByUsername(string $username): ?User
    {
        try {
            self::initialize();
            return User::findByUsername($username);
        } catch (\Exception $e) {
            error_log('Database query failed: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * 更新用户登录信息（使用 ORM）
     */
    public static function updateUserLoginInfo(int $userId, string $ip, ?string $userAgent = null): bool
    {
        try {
            self::initialize();
            $user = User::find($userId);
            if (!$user) {
                return false;
            }
            
            return $user->updateLoginInfo($ip, $userAgent);
        } catch (\Exception $e) {
            error_log('Database update failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 增加用户失败登录次数（使用 ORM）
     */
    public static function incrementFailedLoginCount(int $userId): bool
    {
        try {
            self::initialize();
            $user = User::find($userId);
            if (!$user) {
                return false;
            }
            
            return $user->incrementFailedLoginCount();
        } catch (\Exception $e) {
            error_log('Database update failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 检查用户是否被锁定（使用 ORM）
     */
    public static function isUserLocked(int $userId): bool
    {
        try {
            self::initialize();
            $user = User::find($userId);
            if (!$user) {
                return false;
            }
            
            return $user->isLocked();
        } catch (\Exception $e) {
            error_log('Database query failed: ' . $e->getMessage());
            return false;
        }
    }
}

