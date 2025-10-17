<?php

namespace Tiangang\Waf\Performance;

use PfinalClub\Asyncio\{create_task, gather, wait_for, sleep};
use Tiangang\Waf\Config\ConfigManager;
use Tiangang\Waf\Cache\AsyncCacheManager;
use Tiangang\Waf\Database\AsyncDatabaseManager;

/**
 * 性能追踪器
 * 
 * 负责追踪 WAF 系统各个组件的性能，包括请求处理、检测、代理等
 */
class PerformanceTracker
{
    private ConfigManager $configManager;
    private AsyncCacheManager $cacheManager;
    private AsyncDatabaseManager $dbManager;
    private ?array $config;
    private array $activeTraces = [];
    private array $traceData = [];

    public function __construct()
    {
        $this->configManager = new ConfigManager();
        $this->cacheManager = new AsyncCacheManager();
        $this->dbManager = new AsyncDatabaseManager();
        $this->config = $this->configManager->get('performance') ?? [];
    }

    /**
     * 异步开始追踪
     */
    public function asyncStartTrace(string $traceId, string $component, array $context = []): \Generator
    {
        // 模拟异步追踪开始
        yield sleep(0.001);
        
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        
        $this->activeTraces[$traceId] = [
            'component' => $component,
            'context' => $context,
            'start_time' => $startTime,
            'start_memory' => $startMemory,
            'start_peak_memory' => memory_get_peak_usage(true),
            'status' => 'active'
        ];
        
        return $traceId;
    }

    /**
     * 异步结束追踪
     */
    public function asyncEndTrace(string $traceId, array $metrics = []): \Generator
    {
        // 模拟异步追踪结束
        yield sleep(0.001);
        
        if (!isset($this->activeTraces[$traceId])) {
            return null;
        }
        
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        $endPeakMemory = memory_get_peak_usage(true);
        
        $trace = $this->activeTraces[$traceId];
        $duration = $endTime - $trace['start_time'];
        $memoryUsed = $endMemory - $trace['start_memory'];
        $peakMemoryUsed = $endPeakMemory - $trace['start_peak_memory'];
        
        $traceData = [
            'trace_id' => $traceId,
            'component' => $trace['component'],
            'context' => $trace['context'],
            'duration' => $duration,
            'memory_used' => $memoryUsed,
            'peak_memory_used' => $peakMemoryUsed,
            'start_time' => $trace['start_time'],
            'end_time' => $endTime,
            'metrics' => $metrics,
            'status' => 'completed'
        ];
        
        // 异步存储追踪数据
        yield create_task($this->asyncStoreTrace($traceData));
        
        // 异步分析追踪数据
        yield create_task($this->asyncAnalyzeTrace($traceData));
        
        unset($this->activeTraces[$traceId]);
        
        return $traceData;
    }

    /**
     * 异步存储追踪数据
     */
    private function asyncStoreTrace(array $traceData): \Generator
    {
        // 模拟异步追踪数据存储
        yield sleep(0.002);
        
        // 存储到缓存
        $cacheKey = "performance:trace:{$traceData['trace_id']}";
        yield $this->cacheManager->asyncSet($cacheKey, $traceData, 1800); // 30分钟
        
        // 存储到数据库
        yield $this->dbManager->asyncInsert('performance_traces', [
            'trace_id' => $traceData['trace_id'],
            'component' => $traceData['component'],
            'duration' => $traceData['duration'],
            'memory_used' => $traceData['memory_used'],
            'peak_memory_used' => $traceData['peak_memory_used'],
            'context' => json_encode($traceData['context']),
            'metrics' => json_encode($traceData['metrics']),
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * 异步分析追踪数据
     */
    private function asyncAnalyzeTrace(array $traceData): \Generator
    {
        // 模拟异步追踪数据分析
        yield sleep(0.001);
        
        $component = $traceData['component'];
        $duration = $traceData['duration'];
        $memoryUsed = $traceData['memory_used'];
        
        // 检测性能问题
        $issues = [];
        
        if ($duration > 0.5) { // 超过500ms
            $issues[] = [
                'type' => 'slow_operation',
                'severity' => 'high',
                'message' => "Component {$component} took {$duration}s to complete",
                'threshold' => 0.5,
                'actual' => $duration
            ];
        }
        
        if ($memoryUsed > 10485760) { // 超过10MB
            $issues[] = [
                'type' => 'high_memory',
                'severity' => 'medium',
                'message' => "Component {$component} used " . round($memoryUsed / 1024 / 1024, 2) . "MB",
                'threshold' => 10485760,
                'actual' => $memoryUsed
            ];
        }
        
        if (!empty($issues)) {
            // 异步记录性能问题
            yield create_task($this->asyncRecordPerformanceIssue($traceData, $issues));
        }
    }

    /**
     * 异步记录性能问题
     */
    private function asyncRecordPerformanceIssue(array $traceData, array $issues): \Generator
    {
        // 模拟异步性能问题记录
        yield sleep(0.001);
        
        foreach ($issues as $issue) {
            yield $this->dbManager->asyncInsert('performance_issues', [
                'trace_id' => $traceData['trace_id'],
                'component' => $traceData['component'],
                'issue_type' => $issue['type'],
                'severity' => $issue['severity'],
                'message' => $issue['message'],
                'threshold' => $issue['threshold'],
                'actual_value' => $issue['actual'],
                'context' => json_encode($traceData['context']),
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
    }

    /**
     * 异步获取组件性能报告
     */
    public function asyncGetComponentReport(string $component, string $period = '1h'): \Generator
    {
        // 模拟异步组件性能报告生成
        yield sleep(0.005);
        
        $timeCondition = match($period) {
            '1h' => 'created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)',
            '1d' => 'created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)',
            '1w' => 'created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)',
            default => 'created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)'
        };
        
        // 并发获取多个指标
        $tasks = [
            create_task($this->asyncGetComponentMetrics($component, $timeCondition)),
            create_task($this->asyncGetComponentIssues($component, $timeCondition)),
            create_task($this->asyncGetComponentTrends($component, $timeCondition))
        ];
        
        $results = yield gather(...$tasks);
        
        return [
            'component' => $component,
            'period' => $period,
            'generated_at' => date('Y-m-d H:i:s'),
            'metrics' => $results[0],
            'issues' => $results[1],
            'trends' => $results[2]
        ];
    }

    /**
     * 异步获取组件指标
     */
    private function asyncGetComponentMetrics(string $component, string $timeCondition): \Generator
    {
        yield sleep(0.002);
        
        $result = yield $this->dbManager->asyncQuery(
            "SELECT 
                COUNT(*) as total_traces,
                AVG(duration) as avg_duration,
                MIN(duration) as min_duration,
                MAX(duration) as max_duration,
                AVG(memory_used) as avg_memory,
                MIN(memory_used) as min_memory,
                MAX(memory_used) as max_memory,
                AVG(peak_memory_used) as avg_peak_memory
             FROM performance_traces 
             WHERE component = ? AND {$timeCondition}",
            [$component]
        );
        
        return $result[0] ?? [];
    }

    /**
     * 异步获取组件问题
     */
    private function asyncGetComponentIssues(string $component, string $timeCondition): \Generator
    {
        yield sleep(0.002);
        
        $result = yield $this->dbManager->asyncQuery(
            "SELECT 
                issue_type,
                severity,
                COUNT(*) as count,
                AVG(actual_value) as avg_value
             FROM performance_issues 
             WHERE component = ? AND {$timeCondition}
             GROUP BY issue_type, severity",
            [$component]
        );
        
        return $result ?? [];
    }

    /**
     * 异步获取组件趋势
     */
    private function asyncGetComponentTrends(string $component, string $timeCondition): \Generator
    {
        yield sleep(0.003);
        
        $result = yield $this->dbManager->asyncQuery(
            "SELECT 
                DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00') as hour,
                COUNT(*) as trace_count,
                AVG(duration) as avg_duration,
                AVG(memory_used) as avg_memory
             FROM performance_traces 
             WHERE component = ? AND {$timeCondition}
             GROUP BY hour
             ORDER BY hour",
            [$component]
        );
        
        return $result ?? [];
    }

    /**
     * 异步获取实时追踪状态
     */
    public function asyncGetRealTimeStatus(): \Generator
    {
        // 模拟异步实时状态获取
        yield sleep(0.001);
        
        $activeTraces = [];
        foreach ($this->activeTraces as $traceId => $trace) {
            $currentTime = microtime(true);
            $elapsed = $currentTime - $trace['start_time'];
            
            $activeTraces[] = [
                'trace_id' => $traceId,
                'component' => $trace['component'],
                'elapsed_time' => $elapsed,
                'context' => $trace['context']
            ];
        }
        
        return [
            'active_traces' => count($activeTraces),
            'traces' => $activeTraces,
            'current_memory' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'timestamp' => microtime(true)
        ];
    }

    /**
     * 异步清理过期追踪数据
     */
    public function asyncCleanupExpiredTraces(int $days = 3): \Generator
    {
        // 模拟异步追踪数据清理
        yield sleep(0.01);
        
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $deletedTraces = yield $this->dbManager->asyncDelete('performance_traces', [
            'created_at' => ['<', $cutoffDate]
        ]);
        
        $deletedIssues = yield $this->dbManager->asyncDelete('performance_issues', [
            'created_at' => ['<', $cutoffDate]
        ]);
        
        return [
            'deleted_traces' => $deletedTraces,
            'deleted_issues' => $deletedIssues,
            'cutoff_date' => $cutoffDate
        ];
    }

    /**
     * 异步获取性能热点
     */
    public function asyncGetPerformanceHotspots(string $period = '1h'): \Generator
    {
        // 模拟异步性能热点分析
        yield sleep(0.005);
        
        $timeCondition = match($period) {
            '1h' => 'created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)',
            '1d' => 'created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)',
            '1w' => 'created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)',
            default => 'created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)'
        };
        
        $result = yield $this->dbManager->asyncQuery(
            "SELECT 
                component,
                COUNT(*) as total_traces,
                AVG(duration) as avg_duration,
                MAX(duration) as max_duration,
                AVG(memory_used) as avg_memory,
                MAX(memory_used) as max_memory
             FROM performance_traces 
             WHERE {$timeCondition}
             GROUP BY component
             ORDER BY avg_duration DESC
             LIMIT 10"
        );
        
        return $result ?? [];
    }
}
