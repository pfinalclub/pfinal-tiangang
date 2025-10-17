<?php

namespace Tiangang\Waf\Web\Controllers;

use PfinalClub\Asyncio\{create_task, gather, wait_for, sleep};
use Tiangang\Waf\Performance\PerformanceDashboard;
use Tiangang\Waf\Monitoring\MetricsCollector;
use Tiangang\Waf\Cache\AsyncCacheManager;
use Tiangang\Waf\Database\AsyncDatabaseManager;

/**
 * Web 控制台仪表板控制器
 * 
 * 负责处理 Web 控制台的仪表板相关请求
 */
class DashboardController
{
    private PerformanceDashboard $performanceDashboard;
    private MetricsCollector $metricsCollector;
    private AsyncCacheManager $cacheManager;
    private AsyncDatabaseManager $dbManager;

    public function __construct()
    {
        $this->performanceDashboard = new PerformanceDashboard();
        $this->metricsCollector = new MetricsCollector();
        $this->cacheManager = new AsyncCacheManager();
        $this->dbManager = new AsyncDatabaseManager();
    }

    /**
     * 异步获取仪表板数据
     */
    public function asyncGetDashboardData(): \Generator
    {
        // 模拟异步获取仪表板数据
        yield sleep(0.01);
        
        // 并发获取多个数据源
        $tasks = [
            create_task($this->asyncGetSystemOverview()),
            create_task($this->asyncGetPerformanceMetrics()),
            create_task($this->asyncGetSecurityStats()),
            create_task($this->asyncGetRecentActivity()),
            create_task($this->asyncGetAlerts())
        ];
        
        $results = yield gather(...$tasks);
        
        return [
            'success' => true,
            'data' => [
                'system_overview' => $results[0],
                'performance_metrics' => $results[1],
                'security_stats' => $results[2],
                'recent_activity' => $results[3],
                'alerts' => $results[4]
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * 异步获取系统概览
     */
    private function asyncGetSystemOverview(): \Generator
    {
        yield sleep(0.002);
        
        return [
            'status' => 'healthy',
            'uptime' => '7 days, 12 hours',
            'version' => '1.0.0',
            'last_updated' => date('Y-m-d H:i:s'),
            'components' => [
                'waf_middleware' => ['status' => 'running', 'uptime' => '7d 12h'],
                'proxy_handler' => ['status' => 'running', 'uptime' => '7d 12h'],
                'monitoring' => ['status' => 'running', 'uptime' => '7d 12h'],
                'logging' => ['status' => 'running', 'uptime' => '7d 12h']
            ]
        ];
    }

    /**
     * 异步获取性能指标
     */
    private function asyncGetPerformanceMetrics(): \Generator
    {
        yield sleep(0.003);
        
        // 异步获取性能仪表板数据
        $performanceData = yield $this->performanceDashboard->asyncGetDashboardData('1h');
        
        return [
            'response_time' => [
                'current' => 45.2,
                'average' => 42.8,
                'trend' => 'stable'
            ],
            'throughput' => [
                'current' => 1250,
                'average' => 1180,
                'trend' => 'increasing'
            ],
            'error_rate' => [
                'current' => 0.02,
                'average' => 0.015,
                'trend' => 'stable'
            ],
            'memory_usage' => [
                'current' => 256.5,
                'average' => 245.2,
                'trend' => 'stable'
            ]
        ];
    }

    /**
     * 异步获取安全统计
     */
    private function asyncGetSecurityStats(): \Generator
    {
        yield sleep(0.002);
        
        $result = yield $this->dbManager->asyncQuery(
            "SELECT 
                COUNT(*) as total_requests,
                SUM(CASE WHEN blocked = 1 THEN 1 ELSE 0 END) as blocked_requests,
                SUM(CASE WHEN rule = 'sql_injection' THEN 1 ELSE 0 END) as sql_injection_attempts,
                SUM(CASE WHEN rule = 'xss' THEN 1 ELSE 0 END) as xss_attempts,
                SUM(CASE WHEN rule = 'rate_limit' THEN 1 ELSE 0 END) as rate_limit_hits
             FROM waf_logs 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );
        
        $data = $result[0] ?? [
            'total_requests' => 0,
            'blocked_requests' => 0,
            'sql_injection_attempts' => 0,
            'xss_attempts' => 0,
            'rate_limit_hits' => 0
        ];
        
        return [
            'total_requests' => $data['total_requests'],
            'blocked_requests' => $data['blocked_requests'],
            'block_rate' => $data['total_requests'] > 0 ? 
                round($data['blocked_requests'] / $data['total_requests'] * 100, 2) : 0,
            'threats' => [
                'sql_injection' => $data['sql_injection_attempts'],
                'xss' => $data['xss_attempts'],
                'rate_limit' => $data['rate_limit_hits']
            ]
        ];
    }

    /**
     * 异步获取最近活动
     */
    private function asyncGetRecentActivity(): \Generator
    {
        yield sleep(0.002);
        
        $result = yield $this->dbManager->asyncQuery(
            "SELECT 
                ip,
                uri,
                method,
                blocked,
                rule,
                created_at
             FROM waf_logs 
             ORDER BY created_at DESC 
             LIMIT 10"
        );
        
        $activities = [];
        foreach ($result as $row) {
            $activities[] = [
                'ip' => $row['ip'],
                'uri' => $row['uri'],
                'method' => $row['method'],
                'blocked' => (bool)$row['blocked'],
                'rule' => $row['rule'],
                'timestamp' => $row['created_at'],
                'type' => $row['blocked'] ? 'blocked' : 'allowed'
            ];
        }
        
        return $activities;
    }

    /**
     * 异步获取告警
     */
    private function asyncGetAlerts(): \Generator
    {
        yield sleep(0.001);
        
        return [
            [
                'id' => 'alert_001',
                'type' => 'performance',
                'severity' => 'warning',
                'message' => 'High response time detected',
                'timestamp' => date('Y-m-d H:i:s', strtotime('-5 minutes')),
                'status' => 'active'
            ],
            [
                'id' => 'alert_002',
                'type' => 'security',
                'severity' => 'critical',
                'message' => 'Multiple SQL injection attempts detected',
                'timestamp' => date('Y-m-d H:i:s', strtotime('-10 minutes')),
                'status' => 'active'
            ],
            [
                'id' => 'alert_003',
                'type' => 'system',
                'severity' => 'info',
                'message' => 'System update available',
                'timestamp' => date('Y-m-d H:i:s', strtotime('-1 hour')),
                'status' => 'dismissed'
            ]
        ];
    }

    /**
     * 异步获取性能报告
     */
    public function asyncGetPerformanceReport(string $period = '1h'): \Generator
    {
        // 模拟异步获取性能报告
        yield sleep(0.02);
        
        $report = yield $this->performanceDashboard->asyncGetPerformanceReport($period);
        
        return [
            'success' => true,
            'data' => $report,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * 异步获取安全报告
     */
    public function asyncGetSecurityReport(string $period = '1d'): \Generator
    {
        // 模拟异步获取安全报告
        yield sleep(0.015);
        
        $timeCondition = match($period) {
            '1h' => 'created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)',
            '1d' => 'created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)',
            '1w' => 'created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)',
            default => 'created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)'
        };
        
        // 并发获取多个安全指标
        $tasks = [
            create_task($this->asyncGetSecurityOverview($timeCondition)),
            create_task($this->asyncGetThreatAnalysis($timeCondition)),
            create_task($this->asyncGetTopThreats($timeCondition)),
            create_task($this->asyncGetGeographicData($timeCondition))
        ];
        
        $results = yield gather(...$tasks);
        
        return [
            'success' => true,
            'data' => [
                'period' => $period,
                'overview' => $results[0],
                'threat_analysis' => $results[1],
                'top_threats' => $results[2],
                'geographic_data' => $results[3],
                'generated_at' => date('Y-m-d H:i:s')
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * 异步获取安全概览
     */
    private function asyncGetSecurityOverview(string $timeCondition): \Generator
    {
        yield sleep(0.003);
        
        $result = yield $this->dbManager->asyncQuery(
            "SELECT 
                COUNT(*) as total_requests,
                SUM(CASE WHEN blocked = 1 THEN 1 ELSE 0 END) as blocked_requests,
                COUNT(DISTINCT ip) as unique_ips,
                AVG(duration) as avg_response_time
             FROM waf_logs 
             WHERE {$timeCondition}"
        );
        
        $data = $result[0] ?? [
            'total_requests' => 0,
            'blocked_requests' => 0,
            'unique_ips' => 0,
            'avg_response_time' => 0
        ];
        
        return [
            'total_requests' => $data['total_requests'],
            'blocked_requests' => $data['blocked_requests'],
            'block_rate' => $data['total_requests'] > 0 ? 
                round($data['blocked_requests'] / $data['total_requests'] * 100, 2) : 0,
            'unique_ips' => $data['unique_ips'],
            'avg_response_time' => round($data['avg_response_time'], 2)
        ];
    }

    /**
     * 异步获取威胁分析
     */
    private function asyncGetThreatAnalysis(string $timeCondition): \Generator
    {
        yield sleep(0.002);
        
        $result = yield $this->dbManager->asyncQuery(
            "SELECT 
                rule,
                COUNT(*) as count,
                COUNT(DISTINCT ip) as unique_ips
             FROM waf_logs 
             WHERE blocked = 1 AND {$timeCondition}
             GROUP BY rule
             ORDER BY count DESC"
        );
        
        return $result ?? [];
    }

    /**
     * 异步获取顶级威胁
     */
    private function asyncGetTopThreats(string $timeCondition): \Generator
    {
        yield sleep(0.002);
        
        $result = yield $this->dbManager->asyncQuery(
            "SELECT 
                ip,
                COUNT(*) as request_count,
                COUNT(CASE WHEN blocked = 1 THEN 1 END) as blocked_count,
                MAX(created_at) as last_seen
             FROM waf_logs 
             WHERE {$timeCondition}
             GROUP BY ip
             ORDER BY request_count DESC
             LIMIT 10"
        );
        
        return $result ?? [];
    }

    /**
     * 异步获取地理数据
     */
    private function asyncGetGeographicData(string $timeCondition): \Generator
    {
        yield sleep(0.002);
        
        $result = yield $this->dbManager->asyncQuery(
            "SELECT 
                SUBSTRING_INDEX(ip, '.', 1) as country_code,
                COUNT(*) as request_count,
                COUNT(CASE WHEN blocked = 1 THEN 1 END) as blocked_count
             FROM waf_logs 
             WHERE {$timeCondition}
             GROUP BY country_code
             ORDER BY request_count DESC"
        );
        
        return $result ?? [];
    }

    /**
     * 异步导出数据
     */
    public function asyncExportData(string $type, string $format = 'json'): \Generator
    {
        // 模拟异步数据导出
        yield sleep(0.01);
        
        $data = match($type) {
            'dashboard' => yield $this->asyncGetDashboardData(),
            'performance' => yield $this->asyncGetPerformanceReport('1h'),
            'security' => yield $this->asyncGetSecurityReport('1d'),
            default => ['error' => 'Invalid export type']
        };
        
        if (isset($data['error'])) {
            return [
                'success' => false,
                'error' => $data['error'],
                'code' => 400
            ];
        }
        
        $exportData = match($format) {
            'json' => json_encode($data, JSON_PRETTY_PRINT),
            'csv' => yield $this->asyncConvertToCsv($data),
            'xml' => yield $this->asyncConvertToXml($data),
            default => json_encode($data, JSON_PRETTY_PRINT)
        };
        
        return [
            'success' => true,
            'data' => [
                'type' => $type,
                'format' => $format,
                'content' => $exportData,
                'size' => strlen($exportData)
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * 异步转换为 CSV
     */
    private function asyncConvertToCsv(array $data): \Generator
    {
        yield sleep(0.002);
        
        $csv = "Type,Value,Timestamp\n";
        $csv .= "Dashboard Data," . json_encode($data) . "," . date('Y-m-d H:i:s') . "\n";
        
        return $csv;
    }

    /**
     * 异步转换为 XML
     */
    private function asyncConvertToXml(array $data): \Generator
    {
        yield sleep(0.002);
        
        $xml = new \SimpleXMLElement('<dashboard_data/>');
        $xml->addChild('timestamp', date('Y-m-d H:i:s'));
        $xml->addChild('data', json_encode($data));
        
        return $xml->asXML();
    }
}
