<?php

namespace Tiangang\Waf\Web\Routes;

use Tiangang\Waf\Web\Controllers\DashboardController;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;

/**
 * Web 路由定义
 * 
 * 负责处理 Web 管理界面的路由
 */
class WebRoutes
{
    private DashboardController $dashboardController;

    public function __construct()
    {
        $this->dashboardController = new DashboardController();
    }

    /**
     * 处理 Web 请求
     */
    public function handleRequest(Request $request): Response
    {
        $path = $request->path();
        $method = $request->method();

        // 检查是否为admin路由
        if (str_starts_with($path, '/admin')) {
            return $this->handleAdminRequest($request);
        }

        // 路由匹配
        return match($path) {
            '/' => $this->handleDashboard($request),
            '/dashboard' => $this->handleDashboard($request),
            '/api/dashboard' => $this->handleApiDashboard($request),
            '/api/performance' => $this->handleApiPerformance($request),
            '/api/security' => $this->handleApiSecurity($request),
            '/api/export' => $this->handleApiExport($request),
            '/health' => $this->handleHealth($request),
            default => $this->handleNotFound($request)
        };
    }

    /**
     * 处理 Admin 请求
     */
    private function handleAdminRequest(Request $request): Response
    {
        $path = $request->path();
        
        // 简单的admin路由处理
        if ($path === '/admin' || $path === '/admin/') {
            return $this->generateAdminDashboard();
        }
        
        // API路由
        if (str_starts_with($path, '/admin/api/')) {
            return $this->handleAdminApi($request);
        }
        
        return new Response(404, [], 'Admin page not found');
    }

    /**
     * 生成 Admin 仪表板
     */
    private function generateAdminDashboard(): Response
    {
        $html = '<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>天罡 WAF - Admin 管理控制台</title>
    <link href="//unpkg.com/layui@2.12.1/dist/css/layui.css" rel="stylesheet">
    <style>
        .admin-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            margin-bottom: 20px;
        }
        .admin-card {
            margin-bottom: 20px;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #2d3748;
        }
    </style>
</head>
<body>
    <div class="layui-container" style="margin-top: 20px;">
        <div class="admin-header">
            <h1>🛡️ 天罡 WAF - Admin 管理控制台</h1>
            <p>基于 Webman-Admin 的专业管理界面</p>
        </div>

        <div class="layui-row layui-col-space15">
            <div class="layui-col-md3">
                <div class="layui-card admin-card">
                    <div class="layui-card-header">系统概览</div>
                    <div class="layui-card-body">
                        <div class="stat-number" id="total-requests">-</div>
                        <div>总请求数</div>
                    </div>
                </div>
            </div>
            <div class="layui-col-md3">
                <div class="layui-card admin-card">
                    <div class="layui-card-header">安全拦截</div>
                    <div class="layui-card-body">
                        <div class="stat-number" id="blocked-requests">-</div>
                        <div>拦截请求</div>
                    </div>
                </div>
            </div>
            <div class="layui-col-md3">
                <div class="layui-card admin-card">
                    <div class="layui-card-header">响应时间</div>
                    <div class="layui-card-body">
                        <div class="stat-number" id="response-time">-</div>
                        <div>平均响应时间</div>
                    </div>
                </div>
            </div>
            <div class="layui-col-md3">
                <div class="layui-card admin-card">
                    <div class="layui-card-header">系统状态</div>
                    <div class="layui-card-body">
                        <div class="stat-number" id="system-status">在线</div>
                        <div>运行状态</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="layui-row layui-col-space15">
            <div class="layui-col-md6">
                <div class="layui-card">
                    <div class="layui-card-header">快速操作</div>
                    <div class="layui-card-body">
                        <button class="layui-btn layui-btn-primary" onclick="loadRules()">规则管理</button>
                        <button class="layui-btn layui-btn-primary" onclick="loadLogs()">日志查看</button>
                        <button class="layui-btn layui-btn-primary" onclick="exportData()">数据导出</button>
                        <button class="layui-btn layui-btn-primary" onclick="systemStatus()">系统状态</button>
                    </div>
                </div>
            </div>
            <div class="layui-col-md6">
                <div class="layui-card">
                    <div class="layui-card-header">最近活动</div>
                    <div class="layui-card-body" id="recent-activities">
                        <div class="layui-loading">正在加载活动数据...</div>
                    </div>
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
            layer.msg(\'欢迎使用天罡 WAF Admin 管理控制台\', {icon: 6, time: 2000});
            
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
        }

        function loadRules() {
            layer.msg(\'正在加载规则管理...\', {icon: 1});
        }

        function loadLogs() {
            layer.msg(\'正在加载日志查看...\', {icon: 1});
        }

        function exportData() {
            layer.msg(\'正在导出数据...\', {icon: 1});
        }

        function systemStatus() {
            layer.msg(\'正在检查系统状态...\', {icon: 1});
        }
    </script>
</body>
</html>';

        return new Response(200, ['Content-Type' => 'text/html'], $html);
    }

    /**
     * 处理 Admin API 请求
     */
    private function handleAdminApi(Request $request): Response
    {
        $path = $request->path();
        
        // 简单的API路由处理
        if ($path === '/admin/api/dashboard') {
            $data = $this->dashboardController->getDashboardData();
            return new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'code' => 0,
                'msg' => 'success',
                'data' => $data
            ]));
        }
        
        return new Response(404, ['Content-Type' => 'application/json'], json_encode([
            'code' => 404,
            'msg' => 'API not found'
        ]));
    }

    /**
     * 处理仪表板页面
     */
    private function handleDashboard(Request $request): Response
    {
        $html = $this->generateDashboardHtml();
        
        return new Response(200, [
            'Content-Type' => 'text/html; charset=utf-8',
        ], $html);
    }

    /**
     * 处理 API 仪表板数据
     */
    private function handleApiDashboard(Request $request): Response
    {
        try {
            // 同步获取仪表板数据（混合架构）
            $data = $this->dashboardController->getDashboardData();
            
            return new Response(200, [
                'Content-Type' => 'application/json',
            ], json_encode([
                'success' => true,
                'data' => $data,
                'timestamp' => time()
            ]));
        } catch (\Exception $e) {
            return new Response(500, [
                'Content-Type' => 'application/json',
            ], json_encode([
                'success' => false,
                'error' => $e->getMessage(),
                'timestamp' => time()
            ]));
        }
    }

    /**
     * 处理性能报告 API
     */
    private function handleApiPerformance(Request $request): Response
    {
        try {
            $period = $request->get('period', '1h');
            $data = $this->dashboardController->getPerformanceReport($period);
            
            return new Response(200, [
                'Content-Type' => 'application/json',
            ], json_encode([
                'success' => true,
                'data' => $data,
                'timestamp' => time()
            ]));
        } catch (\Exception $e) {
            return new Response(500, [
                'Content-Type' => 'application/json',
            ], json_encode([
                'success' => false,
                'error' => $e->getMessage(),
                'timestamp' => time()
            ]));
        }
    }

    /**
     * 处理安全报告 API
     */
    private function handleApiSecurity(Request $request): Response
    {
        try {
            $period = $request->get('period', '1d');
            $data = $this->dashboardController->getSecurityReport($period);
            
            return new Response(200, [
                'Content-Type' => 'application/json',
            ], json_encode([
                'success' => true,
                'data' => $data,
                'timestamp' => time()
            ]));
        } catch (\Exception $e) {
            return new Response(500, [
                'Content-Type' => 'application/json',
            ], json_encode([
                'success' => false,
                'error' => $e->getMessage(),
                'timestamp' => time()
            ]));
        }
    }

    /**
     * 处理数据导出 API
     */
    private function handleApiExport(Request $request): Response
    {
        try {
            $type = $request->get('type', 'dashboard');
            $format = $request->get('format', 'json');
            $data = $this->dashboardController->exportData($type, $format);
            
            $contentType = match($format) {
                'json' => 'application/json',
                'csv' => 'text/csv',
                'xml' => 'application/xml',
                default => 'application/json'
            };
            
            return new Response(200, [
                'Content-Type' => $contentType,
                'Content-Disposition' => 'attachment; filename="waf-export.' . $format . '"'
            ], $data);
        } catch (\Exception $e) {
            return new Response(500, [
                'Content-Type' => 'application/json',
            ], json_encode([
                'success' => false,
                'error' => $e->getMessage(),
                'timestamp' => time()
            ]));
        }
    }

    /**
     * 处理健康检查
     */
    private function handleHealth(Request $request): Response
    {
        return new Response(200, [
            'Content-Type' => 'application/json',
        ], json_encode([
            'status' => 'healthy',
            'service' => 'Tiangang WAF',
            'version' => '1.0.0',
            'timestamp' => time()
        ]));
    }

    /**
     * 处理 404 错误
     */
    private function handleNotFound(Request $request): Response
    {
        return new Response(404, [
            'Content-Type' => 'application/json',
        ], json_encode([
            'error' => 'Not Found',
            'message' => 'The requested resource was not found',
            'path' => $request->path(),
            'timestamp' => time()
        ]));
    }

    /**
     * 生成仪表板 HTML
     */
    private function generateDashboardHtml(): string
    {
        return '<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>天罡 WAF 管理控制台</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
        }
        .container { 
            max-width: 1200px; 
            margin: 0 auto; 
            padding: 20px;
        }
        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        .header h1 {
            color: #2d3748;
            font-size: 2.5rem;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .header p {
            color: #718096;
            font-size: 1.1rem;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-card h3 {
            color: #4a5568;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 10px;
        }
        .stat-card .value {
            font-size: 2.5rem;
            font-weight: bold;
            color: #2d3748;
            margin-bottom: 5px;
        }
        .stat-card .change {
            font-size: 0.9rem;
            color: #48bb78;
        }
        .chart-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        .chart-title {
            font-size: 1.5rem;
            color: #2d3748;
            margin-bottom: 20px;
        }
        .loading {
            text-align: center;
            padding: 40px;
            color: #718096;
        }
        .btn {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            transition: transform 0.2s ease;
        }
        .btn:hover {
            transform: translateY(-2px);
        }
        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }
        .status-online { background: #48bb78; }
        .status-warning { background: #ed8936; }
        .status-offline { background: #f56565; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🛡️ 天罡 WAF 管理控制台</h1>
            <p>实时监控和管理您的 Web 应用防火墙</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <h3>总请求数</h3>
                <div class="value" id="total-requests">-</div>
                <div class="change" id="requests-change">加载中...</div>
            </div>
            <div class="stat-card">
                <h3>拦截请求</h3>
                <div class="value" id="blocked-requests">-</div>
                <div class="change" id="blocked-change">加载中...</div>
            </div>
            <div class="stat-card">
                <h3>响应时间</h3>
                <div class="value" id="response-time">-</div>
                <div class="change" id="time-change">加载中...</div>
            </div>
            <div class="stat-card">
                <h3>系统状态</h3>
                <div class="value">
                    <span class="status-indicator status-online"></span>
                    <span id="system-status">在线</span>
                </div>
                <div class="change" id="uptime">运行时间: 计算中...</div>
            </div>
        </div>

        <div class="chart-container">
            <h3 class="chart-title">📊 实时性能监控</h3>
            <div id="performance-chart" class="loading">
                正在加载性能数据...
            </div>
        </div>

        <div class="chart-container">
            <h3 class="chart-title">🔒 安全事件统计</h3>
            <div id="security-chart" class="loading">
                正在加载安全数据...
            </div>
        </div>
    </div>

    <script>
        // 实时数据更新
        async function loadDashboardData() {
            try {
                const response = await fetch("/api/dashboard");
                const data = await response.json();
                
                if (data.success) {
                    updateStats(data.data);
                }
            } catch (error) {
                console.error("加载数据失败:", error);
            }
        }

        function updateStats(data) {
            // 更新统计数据
            document.getElementById("total-requests").textContent = data.overview?.total_requests || 0;
            document.getElementById("blocked-requests").textContent = data.overview?.blocked_requests || 0;
            document.getElementById("response-time").textContent = (data.performance?.avg_response_time || 0) + "ms";
            
            // 更新变化趋势
            document.getElementById("requests-change").textContent = 
                "较昨日: " + (data.overview?.requests_change || 0) + "%";
            document.getElementById("blocked-change").textContent = 
                "拦截率: " + (data.overview?.block_rate || 0) + "%";
            document.getElementById("time-change").textContent = 
                "较昨日: " + (data.performance?.time_change || 0) + "%";
        }

        // 每5秒更新一次数据
        setInterval(loadDashboardData, 5000);
        
        // 页面加载时立即获取数据
        loadDashboardData();
    </script>
</body>
</html>';
    }
}
