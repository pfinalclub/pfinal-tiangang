<?php

namespace app\admin\config;

use Illuminate\Database\Capsule\Manager as Capsule;
use app\waf\config\ConfigManager;

/**
 * 数据库配置和初始化
 * 
 * 使用 Illuminate Database ORM
 */
class Database
{
    private static bool $initialized = false;
    private static bool $available = false; // 数据库是否可用
    
    /**
     * 初始化数据库连接（支持离线模式）
     */
    public static function initialize(): void
    {
        if (self::$initialized) {
            return;
        }
        
        self::$initialized = true;
        
        $configManager = new ConfigManager();
        $config = $configManager->get('database.connections.mysql') ?? [];
        
        // 如果没有配置数据库，跳过初始化（离线模式）
        if (empty($config) || !($config['host'] ?? false)) {
            self::$available = false;
            return;
        }
        
        try {
            $capsule = new Capsule();
            
            $capsule->addConnection([
                'driver' => 'mysql',
                'host' => $config['host'] ?? '127.0.0.1',
                'port' => $config['port'] ?? 3306,
                'database' => $config['database'] ?? 'tiangang_waf',
                'username' => $config['username'] ?? 'root',
                'password' => $config['password'] ?? '',
                'charset' => $config['charset'] ?? 'utf8mb4',
                'collation' => $config['collation'] ?? 'utf8mb4_unicode_ci',
                'prefix' => $config['prefix'] ?? '',
                'strict' => $config['strict'] ?? true,
                'engine' => $config['engine'] ?? null,
                'options' => [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                    \PDO::ATTR_EMULATE_PREPARES => false,
                    // Workerman 环境优化：使用持久连接
                    \PDO::ATTR_PERSISTENT => false, // Workerman 多进程环境不建议使用持久连接
                ],
            ]);
            
            // 设置为全局可用
            $capsule->setAsGlobal();
            
            // 启动 Eloquent ORM
            $capsule->bootEloquent();
            
            // 测试连接是否可用
            self::$available = self::testConnection();
            
            if (!self::$available) {
                error_log('Database initialized but connection test failed. Running in offline mode.');
            }
        } catch (\Exception $e) {
            // 初始化失败，进入离线模式
            self::$available = false;
            error_log('Database initialization failed: ' . $e->getMessage() . '. Running in offline mode.');
        }
    }
    
    /**
     * 测试数据库连接是否可用
     */
    private static function testConnection(): bool
    {
        try {
            $connection = Capsule::connection();
            $connection->getPdo()->query('SELECT 1');
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * 检查数据库是否可用
     */
    public static function isAvailable(): bool
    {
        if (!self::$initialized) {
            self::initialize();
        }
        return self::$available;
    }
    
    /**
     * 获取数据库连接
     */
    public static function connection(): ?\Illuminate\Database\Connection
    {
        if (!self::$initialized) {
            self::initialize();
        }
        
        if (!self::$available) {
            return null;
        }
        
        try {
            return Capsule::connection();
        } catch (\Exception $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            self::$available = false; // 标记为不可用
            return null;
        }
    }
}

