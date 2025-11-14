<?php

/**
 * 数据库安装脚本
 * 
 * 用于快速初始化数据库和创建默认用户
 */

require_once __DIR__ . '/../vendor/autoload.php';

// 加载环境变量
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

function getDbConfig(): array
{
    return [
        'host' => env('DB_HOST', '127.0.0.1'),
        'port' => env('DB_PORT', 3306),
        'database' => env('DB_DATABASE', 'tiangang_waf'),
        'username' => env('DB_USERNAME', 'root'),
        'password' => env('DB_PASSWORD', ''),
        'charset' => 'utf8mb4',
    ];
}

function createDatabase(PDO $pdo, string $dbName): bool
{
    try {
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        echo "✓ 数据库创建成功: {$dbName}\n";
        return true;
    } catch (PDOException $e) {
        echo "✗ 数据库创建失败: " . $e->getMessage() . "\n";
        return false;
    }
}

function executeSqlFile(PDO $pdo, string $file): bool
{
    if (!file_exists($file)) {
        echo "✗ SQL文件不存在: {$file}\n";
        return false;
    }
    
    $sql = file_get_contents($file);
    if ($sql === false) {
        echo "✗ 无法读取SQL文件: {$file}\n";
        return false;
    }
    
    try {
        // 分割SQL语句（以分号分隔）
        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            function($stmt) {
                return !empty($stmt) && !preg_match('/^--/', $stmt);
            }
        );
        
        foreach ($statements as $statement) {
            if (!empty($statement)) {
                $pdo->exec($statement);
            }
        }
        
        echo "✓ SQL文件执行成功: {$file}\n";
        return true;
    } catch (PDOException $e) {
        echo "✗ SQL文件执行失败: " . $e->getMessage() . "\n";
        return false;
    }
}

function main(): void
{
    echo "========================================\n";
    echo "  天罡 WAF 数据库安装脚本\n";
    echo "========================================\n\n";
    
    $config = getDbConfig();
    
    // 连接数据库（不指定数据库名，用于创建数据库）
    try {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;charset=%s',
            $config['host'],
            $config['port'],
            $config['charset']
        );
        
        $pdo = new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        
        echo "✓ 数据库连接成功\n\n";
    } catch (PDOException $e) {
        echo "✗ 数据库连接失败: " . $e->getMessage() . "\n";
        echo "\n请检查：\n";
        echo "1. 数据库服务是否运行\n";
        echo "2. .env 文件中的数据库配置是否正确\n";
        echo "3. 数据库用户是否有创建数据库的权限\n";
        exit(1);
    }
    
    // 创建数据库
    if (!createDatabase($pdo, $config['database'])) {
        exit(1);
    }
    
    // 选择数据库
    $pdo->exec("USE `{$config['database']}`");
    
    // 执行初始化脚本
    $initFile = __DIR__ . '/init.sql';
    if (!executeSqlFile($pdo, $initFile)) {
        exit(1);
    }
    
    // 执行迁移文件
    $migrationFiles = glob(__DIR__ . '/migrations/*.sql');
    sort($migrationFiles); // 按文件名排序
    
    foreach ($migrationFiles as $migrationFile) {
        if (!executeSqlFile($pdo, $migrationFile)) {
            exit(1);
        }
    }
    
    echo "\n========================================\n";
    echo "  安装完成！\n";
    echo "========================================\n\n";
    echo "默认账户信息：\n";
    echo "  用户名: admin, 密码: admin123\n";
    echo "  用户名: waf, 密码: waf2024\n";
    echo "  用户名: tiangang, 密码: tiangang2024\n\n";
    echo "⚠️  请在生产环境中立即修改默认密码！\n";
}

// 运行安装脚本
main();

