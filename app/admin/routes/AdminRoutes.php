<?php

namespace app\admin\routes;

use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;
use app\admin\controller\DashboardController;
use app\admin\controller\WafController;
use app\admin\controller\RuleController;
use app\admin\controller\LogController;
use app\admin\controller\AuthController;

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
    private AuthController $authController;

    public function __construct()
    {
        $this->dashboardController = new DashboardController();
        $this->wafController = new WafController();
        $this->ruleController = new RuleController();
        $this->logController = new LogController();
        $this->authController = new AuthController();
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
            // 认证相关路由
            '/admin/login' => $this->authController->login($request),
            '/admin/auth/login' => $this->authController->doLogin($request),
            '/admin/auth/logout' => $this->authController->logout($request),
            
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
     * 生成Admin仪表板HTML - 基于Figma设计稿
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
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #F5F6FA;
            color: #364A63;
        }
        
        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }
        
        /* 侧边栏 */
        .sidebar {
            width: 180px;
            background: #FFFFFF;
            border-radius: 20px 0 0 20px;
            padding: 20px 0;
            position: fixed;
            height: 100vh;
            left: 0;
            top: 0;
            z-index: 1000;
        }
        
        .logo {
            text-align: center;
            padding: 20px;
            font-weight: 700;
            font-size: 15px;
            color: #364A63;
            border-bottom: 1px solid #F5F6FA;
            margin-bottom: 20px;
        }
        
        .nav-menu {
            list-style: none;
        }
        
        .nav-item {
            margin: 8px 0;
        }
        
        .nav-item.active {
            background: #F5F6FA;
            border-radius: 100px 0 0 100px;
        }
        
        .nav-link {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: #364A63;
            text-decoration: none;
            font-size: 10px;
            transition: all 0.3s ease;
        }
        
        .nav-link:hover {
            background: #EFEFF2;
            border-radius: 100px 0 0 100px;
        }
        
        .nav-icon {
            width: 14px;
            height: 14px;
            margin-right: 12px;
            opacity: 0.7;
        }
        
        /* 主内容区域 */
        .main-content {
            flex: 1;
            margin-left: 180px;
            padding: 20px;
        }
        
        /* 顶部导航栏 */
        .top-nav {
            background: #FFFFFF;
            border-radius: 0 20px 0 10px;
            padding: 15px 30px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .welcome-text {
            font-size: 14px;
            font-weight: 400;
            color: #364A63;
        }
        
        .top-nav-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .search-box {
            position: relative;
        }
        
        .search-input {
            padding: 8px 35px 8px 12px;
            border: 1px solid #EFEFF2;
            border-radius: 20px;
            width: 200px;
            font-size: 12px;
        }
        
        .search-icon {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            width: 16px;
            height: 16px;
            opacity: 0.5;
        }
        
        .notification-icon, .theme-toggle {
            width: 18px;
            height: 18px;
            cursor: pointer;
            opacity: 0.7;
        }
        
        .user-avatar {
            width: 25px;
            height: 25px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 12px;
            font-weight: bold;
        }
        
        /* 统计卡片 */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: #FFFFFF;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
        }
        
        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .stat-title {
            font-size: 12px;
            color: #364A63;
            font-weight: 400;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: 600;
            color: #364A63;
            margin-bottom: 5px;
        }
        
        .stat-change {
            font-size: 10px;
            color: #1EE0AC;
        }
        
        .stat-change.negative {
            color: #DC2430;
        }
        
        /* 图表区域 */
        .charts-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .chart-card {
            background: #FFFFFF;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .chart-title {
            font-size: 14px;
            font-weight: 400;
            color: #364A63;
        }
        
        .chart-content {
            height: 300px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #F8F9FA;
            border-radius: 8px;
            color: #6C757D;
        }
        
        /* 交易记录 */
        .transactions-card {
            background: #FFFFFF;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .transaction-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #F5F6FA;
        }
        
        .transaction-item:last-child {
            border-bottom: none;
        }
        
        .transaction-info {
            flex: 1;
        }
        
        .transaction-name {
            font-size: 10px;
            color: #364A63;
            margin-bottom: 5px;
        }
        
        .transaction-date {
            font-size: 8px;
            color: #DBDFEA;
        }
        
        .transaction-amount {
            font-size: 10px;
            font-weight: 700;
        }
        
        .transaction-amount.positive {
            color: #1EE0AC;
        }
        
        .transaction-amount.negative {
            color: #DC2430;
        }
        
        /* 响应式设计 */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .charts-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- 侧边栏 -->
        <nav class="sidebar">
            <div class="logo">天罡 WAF</div>
            <ul class="nav-menu">
                <li class="nav-item active">
                    <a href="#" class="nav-link">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/>
                        </svg>
                        仪表板
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M16 7c0-2.21-1.79-4-4-4S8 4.79 8 7s1.79 4 4 4 4-1.79 4-4zm-4 2c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm-5 8c0-2.21-1.79-4-4-4s-4 1.79-4 4 1.79 4 4 4 4-1.79 4-4zm-4 2c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2z"/>
                        </svg>
                        用户管理
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M20 2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h4l4 4 4-4h4c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/>
                        </svg>
                        消息中心
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M20 4H4c-1.11 0-1.99.89-1.99 2L2 18c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V6c0-1.11-.89-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
                        </svg>
                        交易记录
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                        </svg>
                        我的钱包
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                        </svg>
                        支付管理
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M3 13h2v-2H3v2zm0 4h2v-2H3v2zm0-8h2V7H3v2zm4 4h14v-2H7v2zm0 4h14v-2H7v2zM7 7v2h14V7H7z"/>
                        </svg>
                        投资分析
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/>
                        </svg>
                        报告中心
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12,15.5A3.5,3.5 0 0,1 8.5,12A3.5,3.5 0 0,1 12,8.5A3.5,3.5 0 0,1 15.5,12A3.5,3.5 0 0,1 12,15.5M19.43,12.97C19.47,12.65 19.5,12.33 19.5,12C19.5,11.67 19.47,11.34 19.43,11L21.54,9.37C21.73,9.22 21.78,8.95 21.66,8.73L19.66,5.27C19.54,5.05 19.27,4.96 19.05,5.05L16.56,6.05C16.04,5.66 15.5,5.32 14.87,5.07L14.5,2.42C14.46,2.18 14.25,2 14,2H10C9.75,2 9.54,2.18 9.5,2.42L9.13,5.07C8.5,5.32 7.96,5.66 7.44,6.05L4.95,5.05C4.73,4.96 4.46,5.05 4.34,5.27L2.34,8.73C2.22,8.95 2.27,9.22 2.46,9.37L4.57,11C4.53,11.34 4.5,11.67 4.5,12C4.5,12.33 4.53,12.65 4.57,12.97L2.46,14.63C2.27,14.78 2.22,15.05 2.34,15.27L4.34,18.73C4.46,18.95 4.73,19.03 4.95,18.95L7.44,17.94C7.96,18.34 8.5,18.68 9.13,18.93L9.5,21.58C9.54,21.82 9.75,22 10,22H14C14.25,22 14.46,21.82 14.5,21.58L14.87,18.93C15.5,18.68 16.04,18.34 16.56,17.94L19.05,18.95C19.27,19.03 19.54,18.95 19.66,18.73L21.66,15.27C21.78,15.05 21.73,14.78 21.54,14.63L19.43,12.97Z"/>
                        </svg>
                        设置
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M17,7H22V17H17V19A1,1 0 0,0 18,20H20V22H16.5C15.95,22 15,21.55 15,21A1,1 0 0,0 14,20H10A1,1 0 0,0 9,21C9,21.55 8.05,22 7.5,22H4V20H6A1,1 0 0,0 7,19V5A1,1 0 0,0 6,4H4V2H7.5C8.05,2 9,2.45 9,3A1,1 0 0,0 10,4H14A1,1 0 0,0 15,3C15,2.45 15.95,2 16.5,2H20V4H18A1,1 0 0,0 17,5V7Z"/>
                        </svg>
                        支持
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M14.08,4.61L15.92,5.4L14.5,8.69L12.5,7.8L14.08,4.61M20.84,8.53L19.32,9.96L21.36,12.5L19.84,13.93L20.84,8.53M15.61,11.17L16.39,13L19.31,11.5L18.53,9.67L15.61,11.17M4.16,8.53L5.16,13.93L3.64,12.5L5.68,9.96L4.16,8.53M19.84,15.47L20.84,20.87L19.32,19.44L21.36,16.9L19.84,15.47M14.08,19.39L12.5,16.2L14.5,15.31L15.92,18.6L14.08,19.39M8.39,11.17L5.47,9.67L4.69,11.5L7.61,13L8.39,11.17M12,2A10,10 0 0,1 22,12A10,10 0 0,1 12,22A10,10 0 0,1 2,12A10,10 0 0,1 12,2M12,4A8,8 0 0,0 4,12A8,8 0 0,0 12,20A8,8 0 0,0 20,12A8,8 0 0,0 12,4Z"/>
                        </svg>
                        退出登录
                    </a>
                </li>
            </ul>
        </nav>

        <!-- 主内容区域 -->
        <main class="main-content">
            <!-- 顶部导航栏 -->
            <header class="top-nav">
                <div class="welcome-text">你好，管理员</div>
                <div class="top-nav-right">
                    <div class="search-box">
                        <input type="text" class="search-input" placeholder="搜索...">
                        <svg class="search-icon" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
                        </svg>
                    </div>
                    <svg class="notification-icon" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.89 2 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/>
                    </svg>
                    <svg class="theme-toggle" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 3c-4.97 0-9 4.03-9 9s4.03 9 9 9 9-4.03 9-9c0-.46-.04-.92-.1-1.36-.98 1.37-2.58 2.26-4.4 2.26-2.98 0-5.4-2.42-5.4-5.4 0-1.81.89-3.42 2.26-4.4-.44-.06-.9-.1-1.36-.1z"/>
                    </svg>
                           <div class="user-avatar" onclick="logout()" style="cursor: pointer;" title="点击登出">管</div>
                </div>
            </header>

            <!-- 统计卡片 -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-title">总请求数</div>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                        </svg>
                    </div>
                    <div class="stat-value" id="total-requests">-</div>
                    <div class="stat-change" id="requests-change">加载中...</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-title">拦截请求</div>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z"/>
                        </svg>
                    </div>
                    <div class="stat-value" id="blocked-requests">-</div>
                    <div class="stat-change" id="blocked-change">拦截率: 计算中...</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-title">响应时间</div>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                        </svg>
                    </div>
                    <div class="stat-value" id="response-time">-</div>
                    <div class="stat-change" id="time-change">较昨日: 计算中...</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-title">系统状态</div>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                        </svg>
                    </div>
                    <div class="stat-value" id="system-status">在线</div>
                    <div class="stat-change" id="uptime">运行时间: 计算中...</div>
                </div>
            </div>

            <!-- 图表区域 -->
            <div class="charts-grid">
                <div class="chart-card">
                    <div class="chart-header">
                        <div class="chart-title">安全事件趋势</div>
                        <div style="display: flex; gap: 10px; font-size: 10px;">
                            <span style="color: #DC2430;">● 拦截</span>
                            <span style="color: #1EE0AC;">● 放行</span>
                        </div>
                    </div>
                    <div class="chart-content">
                        <div style="text-align: center;">
                            <div style="font-size: 14px; margin-bottom: 10px;">📊 安全事件趋势图</div>
                            <div style="font-size: 12px; color: #6C757D;">实时监控安全事件变化趋势</div>
                        </div>
                    </div>
                </div>
                
                <div class="chart-card">
                    <div class="chart-header">
                        <div class="chart-title">威胁类型分布</div>
                    </div>
                    <div class="chart-content">
                        <div style="text-align: center;">
                            <div style="font-size: 14px; margin-bottom: 10px;">🥧 威胁分析</div>
                            <div style="font-size: 12px; color: #6C757D;">SQL注入、XSS、CSRF等威胁占比</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 最近活动 -->
            <div class="transactions-card">
                <div class="chart-header">
                    <div class="chart-title">最近安全事件</div>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 8c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm0 2c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"/>
                    </svg>
                </div>
                <div id="recent-activities">
                    <div class="transaction-item">
                        <div class="transaction-info">
                            <div class="transaction-name">SQL注入攻击检测</div>
                            <div class="transaction-date">2024-01-15 14:30:25</div>
                        </div>
                        <div class="transaction-amount negative">已拦截</div>
                    </div>
                    <div class="transaction-item">
                        <div class="transaction-info">
                            <div class="transaction-name">XSS跨站脚本攻击</div>
                            <div class="transaction-date">2024-01-15 14:28:12</div>
                        </div>
                        <div class="transaction-amount negative">已拦截</div>
                    </div>
                    <div class="transaction-item">
                        <div class="transaction-info">
                            <div class="transaction-name">正常用户访问</div>
                            <div class="transaction-date">2024-01-15 14:25:45</div>
                        </div>
                        <div class="transaction-amount positive">已放行</div>
                    </div>
                    <div class="transaction-item">
                        <div class="transaction-info">
                            <div class="transaction-name">恶意爬虫检测</div>
                            <div class="transaction-date">2024-01-15 14:22:18</div>
                        </div>
                        <div class="transaction-amount negative">已拦截</div>
                    </div>
                </div>
            </div>
        </main>
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
               
               // 登出功能
               function logout() {
                   if (confirm("确定要登出吗？")) {
                       window.location.href = "/admin/auth/logout";
                   }
               }
    </script>
</body>
</html>';
    }
}

