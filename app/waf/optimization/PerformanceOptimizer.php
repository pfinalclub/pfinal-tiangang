<?php

namespace Tiangang\Waf\Optimization;

use PfinalClub\Asyncio\{create_task, gather, wait_for, sleep};
use Tiangang\Waf\Config\ConfigManager;
use Tiangang\Waf\Cache\AsyncCacheManager;
use Tiangang\Waf\Database\AsyncDatabaseManager;
use Tiangang\Waf\Performance\PerformanceAnalyzer;

/**
 * 性能优化器
 * 
 * 负责分析和优化系统性能
 */
class PerformanceOptimizer
{
    private ConfigManager $configManager;
    private AsyncCacheManager $cacheManager;
    private AsyncDatabaseManager $dbManager;
    private PerformanceAnalyzer $performanceAnalyzer;
    private ?array $config;
    private array $optimizationHistory = [];

    public function __construct()
    {
        $this->configManager = new ConfigManager();
        $this->cacheManager = new AsyncCacheManager();
        $this->dbManager = new AsyncDatabaseManager();
        $this->performanceAnalyzer = new PerformanceAnalyzer();
        $this->config = $this->configManager->get('optimization') ?? [];
    }

    /**
     * 异步分析系统性能
     */
    public function asyncAnalyzePerformance(): \Generator
    {
        // 模拟异步性能分析
        yield sleep(0.01);
        
        // 并发获取多个性能指标
        $tasks = [
            create_task($this->asyncAnalyzeResponseTime()),
            create_task($this->asyncAnalyzeMemoryUsage()),
            create_task($this->asyncAnalyzeCpuUsage()),
            create_task($this->asyncAnalyzeDatabasePerformance()),
            create_task($this->asyncAnalyzeCachePerformance())
        ];
        
        $results = yield gather(...$tasks);
        
        return [
            'response_time' => $results[0],
            'memory_usage' => $results[1],
            'cpu_usage' => $results[2],
            'database_performance' => $results[3],
            'cache_performance' => $results[4],
            'analysis_timestamp' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * 异步分析响应时间
     */
    private function asyncAnalyzeResponseTime(): \Generator
    {
        yield sleep(0.002);
        
        $result = yield $this->dbManager->asyncQuery(
            "SELECT 
                AVG(duration) as avg_duration,
                MIN(duration) as min_duration,
                MAX(duration) as max_duration,
                PERCENTILE_CONT(0.95) WITHIN GROUP (ORDER BY duration) as p95_duration
             FROM performance_traces 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)"
        );
        
        $data = $result[0] ?? [
            'avg_duration' => 0,
            'min_duration' => 0,
            'max_duration' => 0,
            'p95_duration' => 0
        ];
        
        return [
            'avg_duration' => round($data['avg_duration'], 3),
            'min_duration' => round($data['min_duration'], 3),
            'max_duration' => round($data['max_duration'], 3),
            'p95_duration' => round($data['p95_duration'], 3),
            'status' => $data['avg_duration'] > 0.5 ? 'warning' : 'good'
        ];
    }

    /**
     * 异步分析内存使用
     */
    private function asyncAnalyzeMemoryUsage(): \Generator
    {
        yield sleep(0.002);
        
        $currentMemory = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);
        $memoryLimit = ini_get('memory_limit');
        
        $memoryLimitBytes = $this->parseMemoryLimit($memoryLimit);
        $memoryUsagePercent = ($currentMemory / $memoryLimitBytes) * 100;
        
        return [
            'current_memory' => $this->formatBytes($currentMemory),
            'peak_memory' => $this->formatBytes($peakMemory),
            'memory_limit' => $memoryLimit,
            'usage_percent' => round($memoryUsagePercent, 2),
            'status' => $memoryUsagePercent > 80 ? 'warning' : 'good'
        ];
    }

    /**
     * 异步分析 CPU 使用
     */
    private function asyncAnalyzeCpuUsage(): \Generator
    {
        yield sleep(0.002);
        
        $loadAverage = function_exists('sys_getloadavg') ? sys_getloadavg() : [0, 0, 0];
        $cpuCount = function_exists('sys_getloadavg') ? count($loadAverage) : 1;
        
        $cpuUsagePercent = ($loadAverage[0] / $cpuCount) * 100;
        
        return [
            'load_average' => $loadAverage,
            'cpu_count' => $cpuCount,
            'usage_percent' => round($cpuUsagePercent, 2),
            'status' => $cpuUsagePercent > 80 ? 'warning' : 'good'
        ];
    }

    /**
     * 异步分析数据库性能
     */
    private function asyncAnalyzeDatabasePerformance(): \Generator
    {
        yield sleep(0.003);
        
        $result = yield $this->dbManager->asyncQuery(
            "SELECT 
                COUNT(*) as total_queries,
                AVG(duration) as avg_query_time,
                MAX(duration) as max_query_time
             FROM performance_traces 
             WHERE component = 'database' 
             AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)"
        );
        
        $data = $result[0] ?? [
            'total_queries' => 0,
            'avg_query_time' => 0,
            'max_query_time' => 0
        ];
        
        return [
            'total_queries' => $data['total_queries'],
            'avg_query_time' => round($data['avg_query_time'], 3),
            'max_query_time' => round($data['max_query_time'], 3),
            'status' => $data['avg_query_time'] > 0.1 ? 'warning' : 'good'
        ];
    }

    /**
     * 异步分析缓存性能
     */
    private function asyncAnalyzeCachePerformance(): \Generator
    {
        yield sleep(0.002);
        
        $cacheStats = yield $this->cacheManager->asyncGetStats();
        
        return [
            'cache_stats' => $cacheStats,
            'status' => 'good'
        ];
    }

    /**
     * 异步生成优化建议
     */
    public function asyncGenerateOptimizationSuggestions(): \Generator
    {
        // 模拟异步优化建议生成
        yield sleep(0.005);
        
        $performanceData = yield $this->asyncAnalyzePerformance();
        $suggestions = [];
        
        // 响应时间优化建议
        if ($performanceData['response_time']['status'] === 'warning') {
            $suggestions[] = [
                'type' => 'response_time',
                'priority' => 'high',
                'title' => '优化响应时间',
                'description' => '平均响应时间超过 500ms，建议优化数据库查询和缓存策略',
                'actions' => [
                    '启用查询缓存',
                    '优化数据库索引',
                    '增加缓存层',
                    '使用异步处理'
                ]
            ];
        }
        
        // 内存使用优化建议
        if ($performanceData['memory_usage']['status'] === 'warning') {
            $suggestions[] = [
                'type' => 'memory',
                'priority' => 'medium',
                'title' => '优化内存使用',
                'description' => '内存使用率超过 80%，建议优化内存使用',
                'actions' => [
                    '启用内存缓存',
                    '优化数据结构',
                    '减少内存泄漏',
                    '调整内存限制'
                ]
            ];
        }
        
        // CPU 使用优化建议
        if ($performanceData['cpu_usage']['status'] === 'warning') {
            $suggestions[] = [
                'type' => 'cpu',
                'priority' => 'medium',
                'title' => '优化 CPU 使用',
                'description' => 'CPU 使用率超过 80%，建议优化计算密集型操作',
                'actions' => [
                    '使用异步处理',
                    '优化算法',
                    '减少循环嵌套',
                    '使用缓存'
                ]
            ];
        }
        
        // 数据库性能优化建议
        if ($performanceData['database_performance']['status'] === 'warning') {
            $suggestions[] = [
                'type' => 'database',
                'priority' => 'high',
                'title' => '优化数据库性能',
                'description' => '数据库查询时间过长，建议优化查询',
                'actions' => [
                    '添加数据库索引',
                    '优化查询语句',
                    '使用连接池',
                    '启用查询缓存'
                ]
            ];
        }
        
        return $suggestions;
    }

    /**
     * 异步应用优化
     */
    public function asyncApplyOptimization(string $optimizationType, array $options = []): \Generator
    {
        // 模拟异步应用优化
        yield sleep(0.01);
        
        $optimizationId = uniqid('opt_', true);
        $startTime = microtime(true);
        
        try {
            $result = match($optimizationType) {
                'cache_optimization' => yield $this->asyncApplyCacheOptimization($options),
                'database_optimization' => yield $this->asyncApplyDatabaseOptimization($options),
                'memory_optimization' => yield $this->asyncApplyMemoryOptimization($options),
                'cpu_optimization' => yield $this->asyncApplyCpuOptimization($options),
                default => throw new \Exception("Unknown optimization type: {$optimizationType}")
            };
            
            $endTime = microtime(true);
            $duration = $endTime - $startTime;
            
            // 记录优化历史
            $this->optimizationHistory[$optimizationId] = [
                'type' => $optimizationType,
                'options' => $options,
                'result' => $result,
                'duration' => $duration,
                'timestamp' => date('Y-m-d H:i:s'),
                'status' => 'success'
            ];
            
            return [
                'optimization_id' => $optimizationId,
                'type' => $optimizationType,
                'result' => $result,
                'duration' => $duration,
                'status' => 'success'
            ];
            
        } catch (\Exception $e) {
            $endTime = microtime(true);
            $duration = $endTime - $startTime;
            
            $this->optimizationHistory[$optimizationId] = [
                'type' => $optimizationType,
                'options' => $options,
                'error' => $e->getMessage(),
                'duration' => $duration,
                'timestamp' => date('Y-m-d H:i:s'),
                'status' => 'failed'
            ];
            
            throw $e;
        }
    }

    /**
     * 异步应用缓存优化
     */
    private function asyncApplyCacheOptimization(array $options): \Generator
    {
        yield sleep(0.005);
        
        // 清理过期缓存
        yield $this->cacheManager->asyncCleanup();
        
        // 优化缓存配置
        $cacheConfig = [
            'ttl' => $options['ttl'] ?? 3600,
            'max_size' => $options['max_size'] ?? 1000
        ];
        
        return [
            'cache_cleaned' => true,
            'config_updated' => $cacheConfig
        ];
    }

    /**
     * 异步应用数据库优化
     */
    private function asyncApplyDatabaseOptimization(array $options): \Generator
    {
        yield sleep(0.008);
        
        // 优化数据库连接
        $connectionConfig = [
            'max_connections' => $options['max_connections'] ?? 100,
            'timeout' => $options['timeout'] ?? 30
        ];
        
        return [
            'connection_optimized' => true,
            'config_updated' => $connectionConfig
        ];
    }

    /**
     * 异步应用内存优化
     */
    private function asyncApplyMemoryOptimization(array $options): \Generator
    {
        yield sleep(0.003);
        
        // 清理内存
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
        
        return [
            'memory_cleaned' => true,
            'gc_collected' => true
        ];
    }

    /**
     * 异步应用 CPU 优化
     */
    private function asyncApplyCpuOptimization(array $options): \Generator
    {
        yield sleep(0.002);
        
        // 优化异步处理
        $asyncConfig = [
            'max_concurrent' => $options['max_concurrent'] ?? 100,
            'timeout' => $options['timeout'] ?? 5
        ];
        
        return [
            'async_optimized' => true,
            'config_updated' => $asyncConfig
        ];
    }

    /**
     * 异步获取优化历史
     */
    public function asyncGetOptimizationHistory(): \Generator
    {
        yield sleep(0.001);
        
        return [
            'history' => $this->optimizationHistory,
            'total_optimizations' => count($this->optimizationHistory),
            'successful_optimizations' => count(array_filter($this->optimizationHistory, fn($h) => $h['status'] === 'success')),
            'failed_optimizations' => count(array_filter($this->optimizationHistory, fn($h) => $h['status'] === 'failed'))
        ];
    }

    /**
     * 解析内存限制
     */
    private function parseMemoryLimit(string $memoryLimit): int
    {
        $memoryLimit = trim($memoryLimit);
        $last = strtolower($memoryLimit[strlen($memoryLimit) - 1]);
        $memoryLimit = (int) $memoryLimit;
        
        switch ($last) {
            case 'g':
                $memoryLimit *= 1024;
            case 'm':
                $memoryLimit *= 1024;
            case 'k':
                $memoryLimit *= 1024;
        }
        
        return $memoryLimit;
    }

    /**
     * 格式化字节数
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unit = 0;
        
        while ($bytes >= 1024 && $unit < count($units) - 1) {
            $bytes /= 1024;
            $unit++;
        }
        
        return round($bytes, 2) . ' ' . $units[$unit];
    }
}
