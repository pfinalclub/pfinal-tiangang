<?php

namespace app\waf\performance;

use PfinalClub\Asyncio\{create_task, gather, wait_for, sleep};
use app\waf\config\ConfigManager;
use app\waf\cache\AsyncCacheManager;
use app\waf\database\AsyncDatabaseManager;

/**
 * 性能监控仪表板
 * 
 * 负责提供性能监控的仪表板数据，包括实时指标、历史趋势、告警等
 */
class PerformanceDashboard
{
    private ConfigManager $configManager;
    private ?AsyncCacheManager $cacheManager;
    private ?AsyncDatabaseManager $dbManager;
    private PerformanceAnalyzer $analyzer;
    private PerformanceTracker $tracker;
    private ?array $config;

    public function __construct()
    {
        $this->configManager = new ConfigManager();
        
        // 延迟初始化数据库和缓存组件，避免连接问题
        try {
            $this->cacheManager = new AsyncCacheManager();
            $this->dbManager = new AsyncDatabaseManager();
        } catch (\Exception $e) {
            $this->cacheManager = null;
            $this->dbManager = null;
        }
        
        $this->analyzer = new PerformanceAnalyzer();
        $this->tracker = new PerformanceTracker();
        $this->config = $this->configManager->get('performance') ?? [
            'enabled' => true,
            'metrics_interval' => 60,
            'retention_days' => 30
        ];
    }

    /**
     * 异步获取仪表板数据
     */
    public function asyncGetDashboardData(string $period = '1h'): \Generator
    {
        // 模拟异步仪表板数据获取
        yield sleep(0.01);
        
        // 并发获取多个数据源
        $tasks = [
            create_task($this->asyncGetRealTimeMetrics()),
            create_task($this->asyncGetPerformanceOverview($period)),
            create_task($this->asyncGetComponentBreakdown($period)),
            create_task($this->asyncGetPerformanceAlerts($period)),
            create_task($this->asyncGetTrendAnalysis($period))
        ];
        
        $results = yield gather(...$tasks);
        
        return [
            'period' => $period,
            'generated_at' => date('Y-m-d H:i:s'),
            'real_time' => $results[0],
            'overview' => $results[1],
            'components' => $results[2],
            'alerts' => $results[3],
            'trends' => $results[4]
        ];
    }

    /**
     * 异步获取实时指标
     */
    private function asyncGetRealTimeMetrics(): \Generator
    {
        // 模拟异步实时指标获取
        yield sleep(0.002);
        
        return [
            'active_traces' => yield $this->tracker->asyncGetRealTimeStatus(),
            'system_metrics' => yield $this->analyzer->asyncGetRealTimeMetrics(),
            'cache_stats' => yield $this->cacheManager->asyncGetStats(),
            'timestamp' => microtime(true)
        ];
    }

    /**
     * 异步获取性能概览
     */
    private function asyncGetPerformanceOverview(string $period): \Generator
    {
        // 模拟异步性能概览获取
        yield sleep(0.005);
        
        $timeCondition = match($period) {
            '1h' => 'created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)',
            '1d' => 'created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)',
            '1w' => 'created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)',
            default => 'created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)'
        };
        
        // 并发获取多个概览指标
        $tasks = [
            create_task($this->asyncGetTotalOperations($timeCondition)),
            create_task($this->asyncGetAverageResponseTime($timeCondition)),
            create_task($this->asyncGetThroughput($timeCondition)),
            create_task($this->asyncGetErrorRate($timeCondition)),
            create_task($this->asyncGetMemoryUsage($timeCondition))
        ];
        
        $results = yield gather(...$tasks);
        
        return [
            'total_operations' => $results[0],
            'avg_response_time' => $results[1],
            'throughput' => $results[2],
            'error_rate' => $results[3],
            'memory_usage' => $results[4]
        ];
    }

    /**
     * 异步获取总操作数
     */
    private function asyncGetTotalOperations(string $timeCondition): \Generator
    {
        yield sleep(0.002);
        
        $result = yield $this->dbManager->asyncQuery(
            "SELECT COUNT(*) as total_operations FROM performance_traces WHERE {$timeCondition}"
        );
        
        return $result[0]['total_operations'] ?? 0;
    }

    /**
     * 异步获取平均响应时间
     */
    private function asyncGetAverageResponseTime(string $timeCondition): \Generator
    {
        yield sleep(0.002);
        
        $result = yield $this->dbManager->asyncQuery(
            "SELECT AVG(duration) as avg_duration FROM performance_traces WHERE {$timeCondition}"
        );
        
        return $result[0]['avg_duration'] ?? 0;
    }

    /**
     * 异步获取吞吐量
     */
    private function asyncGetThroughput(string $timeCondition): \Generator
    {
        yield sleep(0.002);
        
        $result = yield $this->dbManager->asyncQuery(
            "SELECT 
                COUNT(*) as total_operations,
                TIMESTAMPDIFF(SECOND, MIN(created_at), MAX(created_at)) as time_span
             FROM performance_traces WHERE {$timeCondition}"
        );
        
        $data = $result[0] ?? ['total_operations' => 0, 'time_span' => 1];
        $opsPerSecond = $data['time_span'] > 0 ? $data['total_operations'] / $data['time_span'] : 0;
        
        return [
            'total_operations' => $data['total_operations'],
            'ops_per_second' => $opsPerSecond,
            'time_span' => $data['time_span']
        ];
    }

    /**
     * 异步获取错误率
     */
    private function asyncGetErrorRate(string $timeCondition): \Generator
    {
        yield sleep(0.002);
        
        $result = yield $this->dbManager->asyncQuery(
            "SELECT 
                COUNT(*) as total_operations,
                SUM(CASE WHEN duration > 1.0 THEN 1 ELSE 0 END) as slow_operations,
                SUM(CASE WHEN memory_used > 10485760 THEN 1 ELSE 0 END) as high_memory_operations
             FROM performance_traces WHERE {$timeCondition}"
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
     * 异步获取内存使用情况
     */
    private function asyncGetMemoryUsage(string $timeCondition): \Generator
    {
        yield sleep(0.002);
        
        $result = yield $this->dbManager->asyncQuery(
            "SELECT 
                AVG(memory_used) as avg_memory,
                MIN(memory_used) as min_memory,
                MAX(memory_used) as max_memory,
                AVG(peak_memory_used) as avg_peak_memory
             FROM performance_traces WHERE {$timeCondition}"
        );
        
        return $result[0] ?? [
            'avg_memory' => 0,
            'min_memory' => 0,
            'max_memory' => 0,
            'avg_peak_memory' => 0
        ];
    }

    /**
     * 异步获取组件分解
     */
    private function asyncGetComponentBreakdown(string $period): \Generator
    {
        // 模拟异步组件分解获取
        yield sleep(0.003);
        
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
                MIN(duration) as min_duration,
                MAX(duration) as max_duration,
                AVG(memory_used) as avg_memory,
                MIN(memory_used) as min_memory,
                MAX(memory_used) as max_memory
             FROM performance_traces 
             WHERE {$timeCondition}
             GROUP BY component
             ORDER BY avg_duration DESC"
        );
        
        return $result ?? [];
    }

    /**
     * 异步获取性能告警
     */
    private function asyncGetPerformanceAlerts(string $period): \Generator
    {
        // 模拟异步性能告警获取
        yield sleep(0.002);
        
        $timeCondition = match($period) {
            '1h' => 'created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)',
            '1d' => 'created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)',
            '1w' => 'created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)',
            default => 'created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)'
        };
        
        $result = yield $this->dbManager->asyncQuery(
            "SELECT 
                component,
                issue_type,
                severity,
                COUNT(*) as count,
                MAX(created_at) as last_occurrence
             FROM performance_issues 
             WHERE {$timeCondition}
             GROUP BY component, issue_type, severity
             ORDER BY severity DESC, count DESC"
        );
        
        return $result ?? [];
    }

    /**
     * 异步获取趋势分析
     */
    private function asyncGetTrendAnalysis(string $period): \Generator
    {
        // 模拟异步趋势分析获取
        yield sleep(0.005);
        
        $timeCondition = match($period) {
            '1h' => 'created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)',
            '1d' => 'created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)',
            '1w' => 'created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)',
            default => 'created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)'
        };
        
        $interval = match($period) {
            '1h' => 'MINUTE',
            '1d' => 'HOUR',
            '1w' => 'DAY',
            default => 'MINUTE'
        };
        
        $result = yield $this->dbManager->asyncQuery(
            "SELECT 
                DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:00') as time_bucket,
                COUNT(*) as operation_count,
                AVG(duration) as avg_duration,
                AVG(memory_used) as avg_memory
             FROM performance_traces 
             WHERE {$timeCondition}
             GROUP BY time_bucket
             ORDER BY time_bucket"
        );
        
        return $result ?? [];
    }

    /**
     * 异步获取性能报告
     */
    public function asyncGetPerformanceReport(string $period = '1h'): \Generator
    {
        // 模拟异步性能报告生成
        yield sleep(0.02);
        
        $dashboardData = yield $this->asyncGetDashboardData($period);
        $performanceReport = yield $this->analyzer->asyncGetPerformanceReport($period);
        
        return [
            'period' => $period,
            'generated_at' => date('Y-m-d H:i:s'),
            'dashboard' => $dashboardData,
            'analysis' => $performanceReport,
            'summary' => yield $this->asyncGenerateSummary($dashboardData, $performanceReport)
        ];
    }

    /**
     * 异步生成性能摘要
     */
    private function asyncGenerateSummary(array $dashboardData, array $performanceReport): \Generator
    {
        // 模拟异步性能摘要生成
        yield sleep(0.003);
        
        $overview = $dashboardData['overview'];
        $components = $dashboardData['components'];
        $alerts = $dashboardData['alerts'];
        
        $summary = [
            'status' => 'healthy',
            'total_operations' => $overview['total_operations'],
            'avg_response_time' => round($overview['avg_response_time'], 3),
            'throughput' => round($overview['throughput']['ops_per_second'], 2),
            'error_rate' => round($overview['error_rate']['slow_rate'] * 100, 2),
            'top_components' => array_slice($components, 0, 5),
            'alert_count' => count($alerts),
            'recommendations' => $performanceReport['recommendations'] ?? []
        ];
        
        // 判断系统状态
        if ($overview['avg_response_time'] > 0.5) {
            $summary['status'] = 'warning';
        }
        
        if ($overview['error_rate']['slow_rate'] > 0.1) {
            $summary['status'] = 'critical';
        }
        
        return $summary;
    }

    /**
     * 异步导出性能数据
     */
    public function asyncExportPerformanceData(string $period = '1h', string $format = 'json'): \Generator
    {
        // 模拟异步性能数据导出
        yield sleep(0.01);
        
        $dashboardData = yield $this->asyncGetDashboardData($period);
        
        switch ($format) {
            case 'json':
                return json_encode($dashboardData, JSON_PRETTY_PRINT);
            case 'csv':
                return yield $this->asyncExportToCsv($dashboardData);
            case 'xml':
                return yield $this->asyncExportToXml($dashboardData);
            default:
                return json_encode($dashboardData, JSON_PRETTY_PRINT);
        }
    }

    /**
     * 异步导出为 CSV
     */
    private function asyncExportToCsv(array $data): \Generator
    {
        yield sleep(0.005);
        
        $csv = "Period,Generated At,Total Operations,Avg Response Time,Throughput,Error Rate\n";
        $csv .= "{$data['period']},{$data['generated_at']},{$data['overview']['total_operations']},{$data['overview']['avg_response_time']},{$data['overview']['throughput']['ops_per_second']},{$data['overview']['error_rate']['slow_rate']}\n";
        
        return $csv;
    }

    /**
     * 异步导出为 XML
     */
    private function asyncExportToXml(array $data): \Generator
    {
        yield sleep(0.005);
        
        $xml = new \SimpleXMLElement('<performance_data/>');
        $xml->addChild('period', $data['period']);
        $xml->addChild('generated_at', $data['generated_at']);
        $xml->addChild('total_operations', $data['overview']['total_operations']);
        $xml->addChild('avg_response_time', $data['overview']['avg_response_time']);
        $xml->addChild('throughput', $data['overview']['throughput']['ops_per_second']);
        $xml->addChild('error_rate', $data['overview']['error_rate']['slow_rate']);
        
        return $xml->asXML();
    }
}
