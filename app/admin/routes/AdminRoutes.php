<?php

namespace app\admin\routes;

use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;
use app\admin\controller\DashboardController;
use app\admin\controller\WafController;
use app\admin\controller\RuleController;
use app\admin\controller\LogController;

/**
 * 统一的管理路由
 * 
 * 负责处理所有管理界面的路由
 */
class AdminRoutes
{
    private DashboardController $dashboardController;
    private WafController $wafController;
    private RuleController $ruleController;
    private LogController $logController;

    public function __construct()
    {
        $this->dashboardController = new DashboardController();
        $this->wafController = new WafController();
        $this->ruleController = new RuleController();
        $this->logController = new LogController();
    }

    /**
     * 处理管理请求
     */
    public function handleRequest(Request $request): Response
    {
        $path = $request->path();
        $method = $request->method();

        // 路由匹配
        return match($path) {
            // 主页面和仪表板
            '/' => $this->handleDashboard($request),
            '/dashboard' => $this->handleDashboard($request),
            '/admin' => $this->handleAdminDashboard($request),
            '/admin/' => $this->handleAdminDashboard($request),
            
            // API接口
            '/api/dashboard' => $this->handleApiDashboard($request),
            '/api/performance' => $this->handleApiPerformance($request),
            '/api/security' => $this->handleApiSecurity($request),
            '/api/export' => $this->handleApiExport($request),
            '/admin/dashboard' => $this->handleAdminApiDashboard($request),
            '/admin/performance' => $this->handleAdminApiPerformance($request),
            '/admin/security' => $this->handleAdminApiSecurity($request),
            '/admin/export' => $this->handleAdminApiExport($request),
            
            // 健康检查
            '/health' => $this->handleHealth($request),
            
            // 默认404
            default => $this->handleNotFound($request)
        };
    }

    /**
     * 处理仪表板页面
     */
    private function handleDashboard(Request $request): Response
    {
        $html = $this->dashboardController->generateDashboardHtml();
        return new Response(200, ['Content-Type' => 'text/html'], $html);
    }

    /**
     * 处理Admin仪表板页面
     */
    private function handleAdminDashboard(Request $request): Response
    {
        $html = $this->generateAdminDashboard();
        return new Response(200, ['Content-Type' => 'text/html'], $html);
    }

    /**
     * 处理API仪表板数据
     */
    private function handleApiDashboard(Request $request): Response
    {
        $data = $this->dashboardController->getDashboardData();
        return new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'success' => true,
            'data' => $data
        ]));
    }

    /**
     * 处理Admin API仪表板数据
     */
    private function handleAdminApiDashboard(Request $request): Response
    {
        $data = $this->dashboardController->getDashboardData();
        return new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'code' => 0,
            'msg' => 'success',
            'data' => $data
        ]));
    }

    /**
     * 处理性能报告API
     */
    private function handleApiPerformance(Request $request): Response
    {
        $period = $request->get('period', '1h');
        $data = $this->dashboardController->getPerformanceReport($period);
        return new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'success' => true,
            'data' => $data
        ]));
    }

    /**
     * 处理Admin性能报告API
     */
    private function handleAdminApiPerformance(Request $request): Response
    {
        $period = $request->get('period', '1h');
        $data = $this->dashboardController->getPerformanceReport($period);
        return new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'code' => 0,
            'msg' => 'success',
            'data' => $data
        ]));
    }

    /**
     * 处理安全报告API
     */
    private function handleApiSecurity(Request $request): Response
    {
        $period = $request->get('period', '1d');
        $data = $this->dashboardController->getSecurityReport($period);
        return new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'success' => true,
            'data' => $data
        ]));
    }

    /**
     * 处理Admin安全报告API
     */
    private function handleAdminApiSecurity(Request $request): Response
    {
        $period = $request->get('period', '1d');
        $data = $this->dashboardController->getSecurityReport($period);
        return new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'code' => 0,
            'msg' => 'success',
            'data' => $data
        ]));
    }

    /**
     * 处理数据导出API
     */
    private function handleApiExport(Request $request): Response
    {
        $type = $request->get('type', 'dashboard');
        $format = $request->get('format', 'json');
        $data = $this->dashboardController->exportData($type, $format);
        
        $contentType = match($format) {
            'json' => 'application/json',
            'csv' => 'text/csv',
            'xml' => 'application/xml',
            default => 'text/plain'
        };
        
        return new Response(200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'attachment; filename="waf_export.' . $format . '"'
        ], $data);
    }

    /**
     * 处理Admin数据导出API
     */
    private function handleAdminApiExport(Request $request): Response
    {
        $type = $request->get('type', 'dashboard');
        $format = $request->get('format', 'json');
        $data = $this->dashboardController->exportData($type, $format);
        
        $contentType = match($format) {
            'json' => 'application/json',
            'csv' => 'text/csv',
            'xml' => 'application/xml',
            default => 'text/plain'
        };
        
        return new Response(200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'attachment; filename="waf_export.' . $format . '"'
        ], $data);
    }

    /**
     * 处理健康检查
     */
    private function handleHealth(Request $request): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'status' => 'ok',
            'timestamp' => time(),
            'service' => 'Tiangang WAF',
            'version' => '1.0.0'
        ]));
    }

    /**
     * 处理404错误
     */
    private function handleNotFound(Request $request): Response
    {
        return new Response(404, ['Content-Type' => 'application/json'], json_encode([
            'error' => 'Not Found',
            'message' => 'The requested resource was not found',
            'timestamp' => time()
        ]));
    }

    /**
     * 生成Admin仪表板HTML
     */
    private function generateAdminDashboard(): string
    {
        return '<!DOCTYPE html>
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
    }
}

