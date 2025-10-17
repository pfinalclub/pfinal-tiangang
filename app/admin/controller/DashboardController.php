<?php

namespace app\admin\controller;

use support\Request;
use support\Response;

/**
 * 仪表板控制器
 * 
 * 负责处理管理界面的仪表板相关请求
 */
class DashboardController
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
     * 生成仪表板HTML页面
     */
    public function generateDashboardHtml(): string
    {
        return '<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>天罡 WAF 管理控制台</title>
    <link href="//unpkg.com/layui@2.12.1/dist/css/layui.css" rel="stylesheet">
    <style>
        .dashboard-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .dashboard-header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        .dashboard-header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        .stat-card {
            text-align: center;
            padding: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-value {
            font-size: 2.5rem;
            font-weight: bold;
            color: #2d3748;
            margin: 10px 0;
        }
        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }
        .stat-change {
            color: #5FB878;
            font-size: 0.8rem;
        }
        .chart-container {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .status-online { color: #5FB878; }
        .status-warning { color: #FFB800; }
        .status-offline { color: #FF5722; }
    </style>
</head>
<body>
    <div class="layui-container" style="margin-top: 20px;">
        <!-- 页面头部 -->
        <div class="dashboard-header">
            <h1>🛡️ 天罡 WAF 管理控制台</h1>
            <p>实时监控和管理您的 Web 应用防火墙</p>
        </div>

        <!-- 统计卡片 -->
        <div class="layui-row layui-col-space15">
            <div class="layui-col-md3">
                <div class="stat-card">
                    <div class="stat-label">总请求数</div>
                    <div class="stat-value" id="total-requests">-</div>
                    <div class="stat-change" id="requests-change">加载中...</div>
                </div>
            </div>
            <div class="layui-col-md3">
                <div class="stat-card">
                    <div class="stat-label">拦截请求</div>
                    <div class="stat-value" id="blocked-requests">-</div>
                    <div class="stat-change" id="blocked-change">加载中...</div>
                </div>
            </div>
            <div class="layui-col-md3">
                <div class="stat-card">
                    <div class="stat-label">响应时间</div>
                    <div class="stat-value" id="response-time">-</div>
                    <div class="stat-change" id="time-change">加载中...</div>
                </div>
            </div>
            <div class="layui-col-md3">
                <div class="stat-card">
                    <div class="stat-label">系统状态</div>
                    <div class="stat-value">
                        <i class="layui-icon layui-icon-ok-circle status-online" id="status-icon"></i>
                        <span id="system-status">在线</span>
                    </div>
                    <div class="stat-change" id="uptime">运行时间: 计算中...</div>
                </div>
            </div>
        </div>

        <!-- 性能监控 -->
        <div class="chart-container">
            <h3><i class="layui-icon layui-icon-chart"></i> 实时性能监控</h3>
            <div id="performance-chart" style="height: 300px;">
                <div class="layui-loading" style="text-align: center; padding: 50px;">
                    <i class="layui-icon layui-icon-loading layui-anim layui-anim-rotate layui-anim-loop"></i>
                    <p>正在加载性能数据...</p>
                </div>
            </div>
        </div>

        <!-- 安全事件统计 -->
        <div class="chart-container">
            <h3><i class="layui-icon layui-icon-shield"></i> 安全事件统计</h3>
            <div id="security-chart" style="height: 300px;">
                <div class="layui-loading" style="text-align: center; padding: 50px;">
                    <i class="layui-icon layui-icon-loading layui-anim layui-anim-rotate layui-anim-loop"></i>
                    <p>正在加载安全数据...</p>
                </div>
            </div>
        </div>
    </div>

    <script src="//unpkg.com/layui@2.12.1/dist/layui.js"></script>
    <script>
        layui.use([\'layer\', \'element\'], function(){
            var layer = layui.layer;
            var element = layui.element;
            
            // 显示欢迎消息
            layer.msg(\'欢迎使用天罡 WAF 管理控制台\', {icon: 6, time: 2000});
            
            // 加载数据
            loadDashboardData();
            
            // 每5秒更新数据
            setInterval(loadDashboardData, 5000);
        });

        async function loadDashboardData() {
            try {
                const response = await fetch("/admin/dashboard");
                const data = await response.json();
                
                if (data.code === 0) {
                    updateStats(data.data);
                }
            } catch (error) {
                console.error("加载数据失败:", error);
            }
        }

        function updateStats(data) {
            document.getElementById("total-requests").textContent = data.overview?.total_requests || 0;
            document.getElementById("blocked-requests").textContent = data.overview?.blocked_requests || 0;
            document.getElementById("response-time").textContent = (data.performance?.avg_response_time || 0) + "ms";
            
            document.getElementById("requests-change").textContent = 
                "较昨日: " + (data.overview?.requests_change || 0) + "%";
            document.getElementById("blocked-change").textContent = 
                "拦截率: " + (data.overview?.block_rate || 0) + "%";
            document.getElementById("time-change").textContent = 
                "较昨日: " + (data.performance?.time_change || 0) + "%";
        }
    </script>
</body>
</html>';
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
     * 数组转XML
     */
    private function arrayToXml(array $data): string
    {
        $xml = new \SimpleXMLElement('<root/>');
        $this->arrayToXmlRecursive($data, $xml);
        return $xml->asXML();
    }

    /**
     * 递归转换数组到XML
     */
    private function arrayToXmlRecursive(array $data, \SimpleXMLElement $xml): void
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $subnode = $xml->addChild($key);
                $this->arrayToXmlRecursive($value, $subnode);
            } else {
                $xml->addChild($key, htmlspecialchars($value));
            }
        }
    }
}
