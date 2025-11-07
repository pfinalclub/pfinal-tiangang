<?php

namespace app\admin\service;

use app\admin\Base\BaseService;

/**
 * 仪表板服务
 * 
 * 处理仪表板相关的业务逻辑
 */
class DashboardService extends BaseService
{
    /**
     * 获取仪表板数据
     */
    public function getDashboardData(): array
    {
        return [
            'overview' => [
                'total_requests' => rand(1000, 5000),
                'blocked_requests' => rand(50, 200),
                'block_rate' => rand(5, 15),
                'requests_change' => rand(-10, 20),
            ],
            'performance' => [
                'avg_response_time' => rand(50, 200),
                'max_response_time' => rand(500, 1000),
                'time_change' => rand(-5, 10),
                'throughput' => rand(100, 500),
            ],
            'security' => [
                'threats_blocked' => rand(20, 100),
                'top_threats' => [
                    ['type' => 'SQL注入', 'count' => rand(10, 50)],
                    ['type' => 'XSS攻击', 'count' => rand(5, 30)],
                    ['type' => '恶意爬虫', 'count' => rand(3, 20)],
                ],
                'security_score' => rand(85, 98),
            ],
            'system' => [
                'status' => 'online',
                'uptime' => '7天 12小时',
                'memory_usage' => rand(60, 85),
                'cpu_usage' => rand(20, 60),
            ]
        ];
    }

    /**
     * 获取性能报告
     */
    public function getPerformanceReport(string $period = '1h'): array
    {
        return [
            'period' => $period,
            'metrics' => [
                'response_times' => array_fill(0, 24, rand(50, 300)),
                'throughput' => array_fill(0, 24, rand(100, 500)),
                'error_rate' => array_fill(0, 24, rand(0, 5)),
            ],
            'summary' => [
                'avg_response_time' => rand(80, 150),
                'peak_throughput' => rand(400, 600),
                'error_rate' => rand(1, 3),
            ]
        ];
    }

    /**
     * 获取安全报告
     */
    public function getSecurityReport(string $period = '1d'): array
    {
        return [
            'period' => $period,
            'threats' => [
                'sql_injection' => rand(10, 50),
                'xss' => rand(5, 30),
                'csrf' => rand(2, 15),
                'brute_force' => rand(1, 10),
            ],
            'top_ips' => [
                ['ip' => '192.168.1.100', 'threats' => rand(5, 20)],
                ['ip' => '10.0.0.50', 'threats' => rand(3, 15)],
                ['ip' => '172.16.0.25', 'threats' => rand(2, 10)],
            ],
            'security_score' => rand(85, 98),
        ];
    }

    /**
     * 导出数据
     */
    public function exportData(string $type = 'dashboard', string $format = 'json'): string
    {
        $data = match($type) {
            'dashboard' => $this->getDashboardData(),
            'performance' => $this->getPerformanceReport(),
            'security' => $this->getSecurityReport(),
            default => ['error' => 'Invalid export type']
        };

        return match($format) {
            'json' => json_encode($data, JSON_PRETTY_PRINT),
            'csv' => $this->arrayToCsv($data),
            'xml' => $this->arrayToXml($data),
            default => json_encode($data)
        };
    }

    /**
     * 数组转CSV
     */
    private function arrayToCsv(array $data): string
    {
        $csv = '';
        foreach ($data as $row) {
            if (is_array($row)) {
                $csv .= implode(',', array_map(function($value) {
                    return is_array($value) ? json_encode($value) : $value;
                }, $row)) . "\n";
            }
        }
        return $csv;
    }

    /**
     * 数组转XML（修复：防止 XXE 注入，禁用外部实体解析）
     */
    private function arrayToXml(array $data): string
    {
        // 使用字符串拼接（最安全，不解析XML）
        return $this->arrayToXmlString($data);
    }
    
    /**
     * 使用字符串拼接生成XML（安全方法，无XXE风险）
     */
    private function arrayToXmlString(array $data, int $depth = 0): string
    {
        if ($depth === 0) {
            $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n<root>";
        } else {
            $xml = '';
        }
        
        foreach ($data as $key => $value) {
            // 验证和清理标签名
            $safeKey = preg_replace('/[^a-zA-Z0-9_\-]/', '', $key);
            if (empty($safeKey)) {
                $safeKey = 'item';
            }
            
            if (is_array($value)) {
                $xml .= "<{$safeKey}>";
                $xml .= $this->arrayToXmlString($value, $depth + 1);
                $xml .= "</{$safeKey}>";
            } else {
                // 转义XML特殊字符
                $safeValue = htmlspecialchars((string)$value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $xml .= "<{$safeKey}>{$safeValue}</{$safeKey}>";
            }
        }
        
        if ($depth === 0) {
            $xml .= '</root>';
        }
        
        return $xml;
    }
}

