<?php

namespace Tiangang\Waf\Performance;

use PfinalClub\Asyncio\{create_task, gather, wait_for, sleep};
use Tiangang\Waf\Config\ConfigManager;
use Tiangang\Waf\Cache\AsyncCacheManager;
use Tiangang\Waf\Database\AsyncDatabaseManager;

/**
 * 性能分析器
 * 
 * 负责分析 WAF 系统性能，包括响应时间、吞吐量、资源使用等
 */
class PerformanceAnalyzer
{
    private ConfigManager $configManager;
    private AsyncCacheManager $cacheManager;
    private AsyncDatabaseManager $dbManager;
    private array $config;
    private array $performanceData = [];
    private array $benchmarks = [];

    public function __construct()
    {
        $this->configManager = new ConfigManager();
        $this->cacheManager = new AsyncCacheManager();
        $this->dbManager = new AsyncDatabaseManager();
        $this->config = $this->configManager->get('performance');
    }

    /**
     * 异步开始性能分析
     */
    public function asyncStartAnalysis(string $operation, array $context = []): \Generator
    {
        // 模拟异步性能分析开始
        yield sleep(0.001);
        
        $analysisId = uniqid('perf_', true);
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        
        $this->performanceData[$analysisId] = [
            'operation' => $operation,
            'context' => $context,
            'start_time' => $startTime,
            'start_memory' => $startMemory,
            'start_peak_memory' => memory_get_peak_usage(true),
            'status' => 'running'
        ];
        
        return $analysisId;
    }

    /**
     * 异步结束性能分析
     */
    public function asyncEndAnalysis(string $analysisId, array $metrics = []): \Generator
    {
        // 模拟异步性能分析结束
        yield sleep(0.001);
        
        if (!isset($this->performanceData[$analysisId])) {
            return null;
        }
        
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        $endPeakMemory = memory_get_peak_usage(true);
        
        $data = $this->performanceData[$analysisId];
        $duration = $endTime - $data['start_time'];
        $memoryUsed = $endMemory - $data['start_memory'];
        $peakMemoryUsed = $endPeakMemory - $data['start_peak_memory'];
        
        $analysis = [
            'analysis_id' => $analysisId,
            'operation' => $data['operation'],
            'context' => $data['context'],
            'duration' => $duration,
            'memory_used' => $memoryUsed,
            'peak_memory_used' => $peakMemoryUsed,
            'end_time' => $endTime,
            'metrics' => $metrics,
            'status' => 'completed'
        ];
        
        // 异步存储分析结果
        yield create_task($this->asyncStoreAnalysis($analysis));
        
        // 更新基准测试
        yield create_task($this->asyncUpdateBenchmark($analysis));
        
        unset($this->performanceData[$analysisId]);
        
        return $analysis;
    }

    /**
     * 异步存储分析结果
     */
    private function asyncStoreAnalysis(array $analysis): \Generator
    {
        // 模拟异步存储分析结果
        yield sleep(0.002);
        
        // 存储到缓存
        $cacheKey = "performance:analysis:{$analysis['analysis_id']}";
        yield $this->cacheManager->asyncSet($cacheKey, $analysis, 3600);
        
        // 存储到数据库
        yield $this->dbManager->asyncInsert('performance_analysis', [
            'analysis_id' => $analysis['analysis_id'],
            'operation' => $analysis['operation'],
            'duration' => $analysis['duration'],
            'memory_used' => $analysis['memory_used'],
            'peak_memory_used' => $analysis['peak_memory_used'],
            'context' => json_encode($analysis['context']),
            'metrics' => json_encode($analysis['metrics']),
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * 异步更新基准测试
     */
    private function asyncUpdateBenchmark(array $analysis): \Generator
    {
        // 模拟异步基准测试更新
        yield sleep(0.001);
        
        $operation = $analysis['operation'];
        
        if (!isset($this->benchmarks[$operation])) {
            $this->benchmarks[$operation] = [
                'count' => 0,
                'total_duration' => 0,
                'avg_duration' => 0,
                'min_duration' => PHP_FLOAT_MAX,
                'max_duration' => 0,
                'total_memory' => 0,
                'avg_memory' => 0,
                'min_memory' => PHP_FLOAT_MAX,
                'max_memory' => 0
            ];
        }
        
        $benchmark = &$this->benchmarks[$operation];
        $benchmark['count']++;
        $benchmark['total_duration'] += $analysis['duration'];
        $benchmark['avg_duration'] = $benchmark['total_duration'] / $benchmark['count'];
        $benchmark['min_duration'] = min($benchmark['min_duration'], $analysis['duration']);
        $benchmark['max_duration'] = max($benchmark['max_duration'], $analysis['duration']);
        
        $benchmark['total_memory'] += $analysis['memory_used'];
        $benchmark['avg_memory'] = $benchmark['total_memory'] / $benchmark['count'];
        $benchmark['min_memory'] = min($benchmark['min_memory'], $analysis['memory_used']);
        $benchmark['max_memory'] = max($benchmark['max_memory'], $analysis['memory_used']);
    }

    /**
     * 异步获取性能报告
     */
    public function asyncGetPerformanceReport(string $period = '1h'): \Generator
    {
        // 模拟异步性能报告生成
        yield sleep(0.01);
        
        $report = [
            'period' => $period,
            'generated_at' => date('Y-m-d H:i:s'),
            'system_info' => yield $this->asyncGetSystemInfo(),
            'performance_metrics' => yield $this->asyncGetPerformanceMetrics($period),
            'benchmarks' => $this->benchmarks,
            'recommendations' => yield $this->asyncGenerateRecommendations()
        ];
        
        return $report;
    }

    /**
     * 异步获取系统信息
     */
    private function asyncGetSystemInfo(): \Generator
    {
        // 模拟异步系统信息获取
        yield sleep(0.002);
        
        return [
            'php_version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'current_memory_usage' => memory_get_usage(true),
            'peak_memory_usage' => memory_get_peak_usage(true),
            'cpu_count' => function_exists('sys_getloadavg') ? count(sys_getloadavg()) : 1,
            'load_average' => function_exists('sys_getloadavg') ? sys_getloadavg() : [0, 0, 0],
            'timestamp' => time()
        ];
    }

    /**
     * 异步获取性能指标
     */
    private function asyncGetPerformanceMetrics(string $period): \Generator
    {
        // 模拟异步性能指标获取
        yield sleep(0.005);
        
        $timeCondition = match($period) {
            '1h' => 'created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)',
            '1d' => 'created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)',
            '1w' => 'created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)',
            default => 'created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)'
        };
        
        // 并发获取多个指标
        $tasks = [
            create_task($this->asyncGetAverageDuration($timeCondition)),
            create_task($this->asyncGetThroughput($timeCondition)),
            create_task($this->asyncGetMemoryUsage($timeCondition)),
            create_task($this->asyncGetErrorRate($timeCondition))
        ];
        
        $results = yield gather(...$tasks);
        
        return [
            'average_duration' => $results[0],
            'throughput' => $results[1],
            'memory_usage' => $results[2],
            'error_rate' => $results[3]
        ];
    }

    /**
     * 异步获取平均响应时间
     */
    private function asyncGetAverageDuration(string $timeCondition): \Generator
    {
        yield sleep(0.002);
        
        $result = yield $this->dbManager->asyncQuery(
            "SELECT AVG(duration) as avg_duration, MIN(duration) as min_duration, MAX(duration) as max_duration 
             FROM performance_analysis WHERE {$timeCondition}"
        );
        
        return $result[0] ?? ['avg_duration' => 0, 'min_duration' => 0, 'max_duration' => 0];
    }

    /**
     * 异步获取吞吐量
     */
    private function asyncGetThroughput(string $timeCondition): \Generator
    {
        yield sleep(0.002);
        
        $result = yield $this->dbManager->asyncQuery(
            "SELECT COUNT(*) as total_operations, 
                    COUNT(*) / (TIMESTAMPDIFF(SECOND, MIN(created_at), MAX(created_at)) + 1) as ops_per_second
             FROM performance_analysis WHERE {$timeCondition}"
        );
        
        return $result[0] ?? ['total_operations' => 0, 'ops_per_second' => 0];
    }

    /**
     * 异步获取内存使用情况
     */
    private function asyncGetMemoryUsage(string $timeCondition): \Generator
    {
        yield sleep(0.002);
        
        $result = yield $this->dbManager->asyncQuery(
            "SELECT AVG(memory_used) as avg_memory, 
                    MIN(memory_used) as min_memory, 
                    MAX(memory_used) as max_memory,
                    AVG(peak_memory_used) as avg_peak_memory
             FROM performance_analysis WHERE {$timeCondition}"
        );
        
        return $result[0] ?? ['avg_memory' => 0, 'min_memory' => 0, 'max_memory' => 0, 'avg_peak_memory' => 0];
    }

    /**
     * 异步获取错误率
     */
    private function asyncGetErrorRate(string $timeCondition): \Generator
    {
        yield sleep(0.002);
        
        $result = yield $this->dbManager->asyncQuery(
            "SELECT COUNT(*) as total_operations,
                    SUM(CASE WHEN duration > 1.0 THEN 1 ELSE 0 END) as slow_operations,
                    SUM(CASE WHEN memory_used > 10485760 THEN 1 ELSE 0 END) as high_memory_operations
             FROM performance_analysis WHERE {$timeCondition}"
        );
        
        $data = $result[0] ?? ['total_operations' => 0, 'slow_operations' => 0, 'high_memory_operations' => 0];
        
        return [
            'total_operations' => $data['total_operations'],
            'slow_operations' => $data['slow_operations'],
            'high_memory_operations' => $data['high_memory_operations'],
            'slow_rate' => $data['total_operations'] > 0 ? $data['slow_operations'] / $data['total_operations'] : 0,
            'high_memory_rate' => $data['total_operations'] > 0 ? $data['high_memory_operations'] / $data['total_operations'] : 0
        ];
    }

    /**
     * 异步生成性能建议
     */
    private function asyncGenerateRecommendations(): \Generator
    {
        // 模拟异步建议生成
        yield sleep(0.003);
        
        $recommendations = [];
        
        // 基于基准测试生成建议
        foreach ($this->benchmarks as $operation => $benchmark) {
            if ($benchmark['avg_duration'] > 0.1) { // 超过100ms
                $recommendations[] = [
                    'type' => 'performance',
                    'operation' => $operation,
                    'issue' => 'High average response time',
                    'recommendation' => 'Consider optimizing database queries or caching strategies',
                    'priority' => 'high'
                ];
            }
            
            if ($benchmark['avg_memory'] > 10485760) { // 超过10MB
                $recommendations[] = [
                    'type' => 'memory',
                    'operation' => $operation,
                    'issue' => 'High memory usage',
                    'recommendation' => 'Consider implementing memory pooling or reducing data processing',
                    'priority' => 'medium'
                ];
            }
            
            if ($benchmark['max_duration'] > 1.0) { // 超过1秒
                $recommendations[] = [
                    'type' => 'timeout',
                    'operation' => $operation,
                    'issue' => 'Very slow operations detected',
                    'recommendation' => 'Implement timeout mechanisms and async processing',
                    'priority' => 'critical'
                ];
            }
        }
        
        return $recommendations;
    }

    /**
     * 异步清理过期数据
     */
    public function asyncCleanupExpiredData(int $days = 7): \Generator
    {
        // 模拟异步数据清理
        yield sleep(0.01);
        
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $deletedCount = yield $this->dbManager->asyncDelete('performance_analysis', [
            'created_at' => ['<', $cutoffDate]
        ]);
        
        return [
            'deleted_records' => $deletedCount,
            'cutoff_date' => $cutoffDate,
            'cleanup_duration' => microtime(true)
        ];
    }

    /**
     * 异步获取实时性能指标
     */
    public function asyncGetRealTimeMetrics(): \Generator
    {
        // 模拟异步实时指标获取
        yield sleep(0.001);
        
        return [
            'active_analyses' => count($this->performanceData),
            'current_memory' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'cpu_usage' => function_exists('sys_getloadavg') ? sys_getloadavg()[0] : 0,
            'timestamp' => microtime(true)
        ];
    }
}
