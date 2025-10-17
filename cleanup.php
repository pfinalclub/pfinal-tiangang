<?php

/**
 * 天罡 WAF 项目清理脚本
 * 
 * 清理项目中的临时文件、缓存和测试文件
 */

echo "天罡 WAF 项目清理脚本\n";
echo "====================\n\n";

// 清理配置
$cleanupConfig = [
    'temp_files' => [
        '*.tmp',
        '*.log',
        '*.cache',
        '*.pid'
    ],
    'test_files' => [
        'test_*.php',
        '*_test.php',
        'debug_*.php',
        'demo_*.php'
    ],
    'cache_dirs' => [
        'cache/',
        'tmp/',
        'logs/',
        'var/cache/',
        'var/logs/'
    ],
    'backup_files' => [
        '*.bak',
        '*.backup',
        '*.old'
    ]
];

// 清理临时文件
function cleanupTempFiles(): void
{
    global $cleanupConfig;
    
    echo "清理临时文件...\n";
    
    $tempFiles = $cleanupConfig['temp_files'];
    $cleanedCount = 0;
    
    foreach ($tempFiles as $pattern) {
        $files = glob($pattern);
        foreach ($files as $file) {
            if (file_exists($file) && is_file($file)) {
                unlink($file);
                $cleanedCount++;
                echo "  删除: {$file}\n";
            }
        }
    }
    
    echo "清理完成: {$cleanedCount} 个临时文件\n\n";
}

// 清理测试文件
function cleanupTestFiles(): void
{
    global $cleanupConfig;
    
    echo "清理测试文件...\n";
    
    $testFiles = $cleanupConfig['test_files'];
    $cleanedCount = 0;
    
    foreach ($testFiles as $pattern) {
        $files = glob($pattern);
        foreach ($files as $file) {
            if (file_exists($file) && is_file($file)) {
                unlink($file);
                $cleanedCount++;
                echo "  删除: {$file}\n";
            }
        }
    }
    
    echo "清理完成: {$cleanedCount} 个测试文件\n\n";
}

// 清理缓存目录
function cleanupCacheDirs(): void
{
    global $cleanupConfig;
    
    echo "清理缓存目录...\n";
    
    $cacheDirs = $cleanupConfig['cache_dirs'];
    $cleanedCount = 0;
    
    foreach ($cacheDirs as $dir) {
        if (is_dir($dir)) {
            $files = glob($dir . '*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                    $cleanedCount++;
                    echo "  删除: {$file}\n";
                } elseif (is_dir($file)) {
                    rmdir($file);
                    $cleanedCount++;
                    echo "  删除目录: {$file}\n";
                }
            }
        }
    }
    
    echo "清理完成: {$cleanedCount} 个缓存文件/目录\n\n";
}

// 清理备份文件
function cleanupBackupFiles(): void
{
    global $cleanupConfig;
    
    echo "清理备份文件...\n";
    
    $backupFiles = $cleanupConfig['backup_files'];
    $cleanedCount = 0;
    
    foreach ($backupFiles as $pattern) {
        $files = glob($pattern);
        foreach ($files as $file) {
            if (file_exists($file) && is_file($file)) {
                unlink($file);
                $cleanedCount++;
                echo "  删除: {$file}\n";
            }
        }
    }
    
    echo "清理完成: {$cleanedCount} 个备份文件\n\n";
}

// 清理日志文件
function cleanupLogFiles(): void
{
    echo "清理日志文件...\n";
    
    $logFiles = [
        '*.log',
        'logs/*.log',
        'var/logs/*.log'
    ];
    
    $cleanedCount = 0;
    
    foreach ($logFiles as $pattern) {
        $files = glob($pattern);
        foreach ($files as $file) {
            if (file_exists($file) && is_file($file)) {
                // 只删除超过7天的日志文件
                if (time() - filemtime($file) > 7 * 24 * 3600) {
                    unlink($file);
                    $cleanedCount++;
                    echo "  删除: {$file}\n";
                }
            }
        }
    }
    
    echo "清理完成: {$cleanedCount} 个日志文件\n\n";
}

// 清理空目录
function cleanupEmptyDirs(): void
{
    echo "清理空目录...\n";
    
    $dirs = [
        'cache/',
        'tmp/',
        'logs/',
        'var/cache/',
        'var/logs/'
    ];
    
    $cleanedCount = 0;
    
    foreach ($dirs as $dir) {
        if (is_dir($dir) && count(scandir($dir)) <= 2) {
            rmdir($dir);
            $cleanedCount++;
            echo "  删除空目录: {$dir}\n";
        }
    }
    
    echo "清理完成: {$cleanedCount} 个空目录\n\n";
}

// 显示项目结构
function showProjectStructure(): void
{
    echo "项目结构:\n";
    echo "========\n";
    
    $structure = [
        'app/' => '应用核心代码',
        'tests/' => '测试代码',
        'tests/debug/' => '调试工具',
        'tests/performance/' => '性能测试',
        'tests/examples/' => '使用示例',
        'docs/' => '文档',
        'docker/' => 'Docker 配置',
        'vendor/' => '依赖包'
    ];
    
    foreach ($structure as $dir => $description) {
        if (is_dir($dir)) {
            $fileCount = count(glob($dir . '*'));
            echo "  {$dir} - {$description} ({$fileCount} 项)\n";
        }
    }
    
    echo "\n";
}

// 显示清理统计
function showCleanupStats(): void
{
    echo "清理统计:\n";
    echo "========\n";
    
    $stats = [
        '临时文件' => count(glob('*.tmp')) + count(glob('*.log')) + count(glob('*.cache')),
        '测试文件' => count(glob('test_*.php')) + count(glob('*_test.php')),
        '缓存目录' => is_dir('cache/') ? count(glob('cache/*')) : 0,
        '日志文件' => count(glob('*.log')) + (is_dir('logs/') ? count(glob('logs/*.log')) : 0)
    ];
    
    foreach ($stats as $type => $count) {
        echo "  {$type}: {$count} 个\n";
    }
    
    echo "\n";
}

// 主函数
function main(): void
{
    echo "开始清理项目...\n\n";
    
    // 显示清理前的统计
    showCleanupStats();
    
    // 执行清理
    cleanupTempFiles();
    cleanupTestFiles();
    cleanupCacheDirs();
    cleanupBackupFiles();
    cleanupLogFiles();
    cleanupEmptyDirs();
    
    // 显示项目结构
    showProjectStructure();
    
    echo "项目清理完成！\n";
    echo "==============\n";
    echo "清理内容:\n";
    echo "1. 临时文件: 清理所有临时文件和缓存\n";
    echo "2. 测试文件: 清理根目录的测试文件\n";
    echo "3. 缓存目录: 清理缓存目录内容\n";
    echo "4. 备份文件: 清理备份文件\n";
    echo "5. 日志文件: 清理过期日志文件\n";
    echo "6. 空目录: 清理空目录\n\n";
    
    echo "项目结构已整理:\n";
    echo "- 应用代码: app/\n";
    echo "- 测试代码: tests/\n";
    echo "- 文档: docs/\n";
    echo "- Docker 配置: docker/\n";
    echo "- 依赖包: vendor/\n\n";
    
    echo "建议:\n";
    echo "- 定期运行清理脚本保持项目整洁\n";
    echo "- 将测试文件放在 tests/ 目录下\n";
    echo "- 使用版本控制管理代码变更\n";
    echo "- 定期备份重要数据\n";
}

// 运行清理
main();
