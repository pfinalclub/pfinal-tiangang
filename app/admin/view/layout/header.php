<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? '天罡 WAF 管理控制台' ?></title>
    
    <!-- Layui CSS -->
    <link href="//unpkg.com/layui@2.12.1/dist/css/layui.css" rel="stylesheet">
    
    <!-- 自定义样式 -->
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #F5F6FA;
            color: #364A63;
            overflow-x: hidden;
        }
        
        /* 顶部导航栏 */
        .top-nav {
            height: 60px;
            background: #FFFFFF;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            display: flex;
            align-items: center;
            padding: 0 20px;
        }
        
        .top-nav .logo {
            font-size: 20px;
            font-weight: 600;
            color: #1890ff;
            display: flex;
            align-items: center;
            gap: 8px;
            width: 200px;
        }
        
        .top-nav .logo i {
            font-size: 24px;
        }
        
        .top-nav .nav-center {
            flex: 1;
            display: flex;
            align-items: center;
            gap: 30px;
            margin-left: 40px;
        }
        
        .top-nav .nav-center a {
            color: #364A63;
            text-decoration: none;
            font-size: 14px;
            padding: 5px 10px;
            border-radius: 4px;
            transition: all 0.3s;
        }
        
        .top-nav .nav-center a:hover,
        .top-nav .nav-center a.active {
            color: #1890ff;
            background: rgba(24, 144, 255, 0.1);
        }
        
        .top-nav .nav-right {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .top-nav .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            background: #f0f9ff;
            border: 1px solid #91d5ff;
            border-radius: 12px;
            font-size: 12px;
            color: #1890ff;
        }
        
        .top-nav .status-badge i {
            font-size: 8px;
            color: #52c41a;
            animation: blink 2s infinite;
        }
        
        @keyframes blink {
            0%, 50%, 100% { opacity: 1; }
            25%, 75% { opacity: 0.3; }
        }
        
        /* 侧边栏 */
        .sidebar {
            width: 200px;
            background: #FFFFFF;
            position: fixed;
            left: 0;
            top: 60px;
            bottom: 0;
            overflow-y: auto;
            box-shadow: 2px 0 8px rgba(0,0,0,0.06);
            z-index: 999;
        }
        
        .sidebar::-webkit-scrollbar {
            width: 4px;
        }
        
        .sidebar::-webkit-scrollbar-thumb {
            background: #d9d9d9;
            border-radius: 4px;
        }
        
        .nav-menu {
            list-style: none;
            padding: 15px 10px;
        }
        
        .nav-item {
            margin-bottom: 5px;
        }
        
        .nav-link {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: #364A63;
            text-decoration: none;
            font-size: 14px;
            border-radius: 6px;
            transition: all 0.3s;
            gap: 10px;
        }
        
        .nav-link:hover {
            background: #f5f5f5;
            color: #1890ff;
        }
        
        .nav-item.active .nav-link {
            background: linear-gradient(135deg, #1890ff 0%, #096dd9 100%);
            color: #FFFFFF;
            box-shadow: 0 2px 8px rgba(24, 144, 255, 0.3);
        }
        
        .nav-icon {
            font-size: 16px;
            width: 20px;
            text-align: center;
        }
        
        /* 主内容区 */
        .main-content {
            margin-left: 200px;
            margin-top: 60px;
            padding: 20px;
            min-height: calc(100vh - 60px);
        }
        
        /* 面包屑 */
        .breadcrumb {
            background: transparent;
            padding: 10px 0;
            margin-bottom: 15px;
        }
        
        /* 内容卡片 */
        .content-card {
            background: #FFFFFF;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            margin-bottom: 20px;
        }
        
        .content-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .content-card-title {
            font-size: 16px;
            font-weight: 600;
            color: #364A63;
        }
        
        /* 响应式 */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s;
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .top-nav .nav-center {
                display: none;
            }
        }
        
        /* 工具类 */
        .text-muted {
            color: #8c8c8c;
        }
        
        .text-danger {
            color: #ff4d4f;
        }
        
        .text-success {
            color: #52c41a;
        }
        
        .text-warning {
            color: #faad14;
        }
        
        .text-primary {
            color: #1890ff;
        }
        
        .mt-10 { margin-top: 10px; }
        .mt-20 { margin-top: 20px; }
        .mb-10 { margin-bottom: 10px; }
        .mb-20 { margin-bottom: 20px; }
    </style>
    
    <?php if (isset($customCss)): ?>
        <style><?= $customCss ?></style>
    <?php endif; ?>
</head>
<body>
    <!-- 顶部导航栏 -->
    <div class="top-nav">
        <div class="logo">
            <i class="layui-icon layui-icon-shield"></i>
            <span>天罡 WAF</span>
        </div>
        
        <div class="nav-center">
            <a href="/admin/dashboard" class="<?= ($currentPage ?? '') === 'dashboard' ? 'active' : '' ?>">
                <i class="layui-icon layui-icon-home"></i> 仪表板
            </a>
            <a href="/admin/config" class="<?= ($currentPage ?? '') === 'config' ? 'active' : '' ?>">
                <i class="layui-icon layui-icon-set"></i> 配置管理
            </a>
            <a href="/admin/logs" class="<?= ($currentPage ?? '') === 'logs' ? 'active' : '' ?>">
                <i class="layui-icon layui-icon-file"></i> 日志查看
            </a>
            <a href="/admin/rules" class="<?= ($currentPage ?? '') === 'rules' ? 'active' : '' ?>">
                <i class="layui-icon layui-icon-list"></i> 规则管理
            </a>
            <a href="/admin/plugins" class="<?= ($currentPage ?? '') === 'plugins' ? 'active' : '' ?>">
                <i class="layui-icon layui-icon-component"></i> 插件管理
            </a>
        </div>
        
        <div class="nav-right">
            <span class="status-badge">
                <i class="layui-icon layui-icon-flag"></i>
                WAF 运行中
            </span>
            
            <button class="layui-btn layui-btn-sm layui-btn-primary" id="refreshBtn">
                <i class="layui-icon layui-icon-refresh"></i> 刷新
            </button>
            
            <div class="layui-btn-group">
                <button class="layui-btn layui-btn-sm layui-btn-primary">
                    <i class="layui-icon layui-icon-username"></i> <?= $_SESSION['admin_user'] ?? 'Admin' ?>
                </button>
                <button class="layui-btn layui-btn-sm layui-btn-primary" onclick="logout()">
                    <i class="layui-icon layui-icon-logout"></i>
                </button>
            </div>
        </div>
    </div>
    
    <!-- 侧边栏 -->
    <div class="sidebar">
        <ul class="nav-menu">
            <li class="nav-item <?= ($currentMenu ?? '') === 'dashboard' ? 'active' : '' ?>">
                <a href="/admin/dashboard" class="nav-link">
                    <i class="layui-icon layui-icon-chart nav-icon"></i>
                    <span>数据概览</span>
                </a>
            </li>
            
            <li class="nav-item <?= ($currentMenu ?? '') === 'realtime' ? 'active' : '' ?>">
                <a href="/admin/realtime" class="nav-link">
                    <i class="layui-icon layui-icon-rate nav-icon"></i>
                    <span>实时监控</span>
                </a>
            </li>
            
            <li class="nav-item <?= ($currentMenu ?? '') === 'config' ? 'active' : '' ?>">
                <a href="/admin/config" class="nav-link">
                    <i class="layui-icon layui-icon-set nav-icon"></i>
                    <span>代理配置</span>
                </a>
            </li>
            
            <li class="nav-item <?= ($currentMenu ?? '') === 'config-nginx' ? 'active' : '' ?>">
                <a href="/admin/config/nginx-mode" class="nav-link">
                    <i class="layui-icon layui-icon-fonts-code nav-icon"></i>
                    <span>Nginx 模式</span>
                </a>
            </li>
            
            <li class="nav-item <?= ($currentMenu ?? '') === 'rules' ? 'active' : '' ?>">
                <a href="/admin/rules" class="nav-link">
                    <i class="layui-icon layui-icon-list nav-icon"></i>
                    <span>防护规则</span>
                </a>
            </li>
            
            <li class="nav-item <?= ($currentMenu ?? '') === 'logs' ? 'active' : '' ?>">
                <a href="/admin/logs" class="nav-link">
                    <i class="layui-icon layui-icon-file nav-icon"></i>
                    <span>访问日志</span>
                </a>
            </li>
            
            <li class="nav-item <?= ($currentMenu ?? '') === 'attack-logs' ? 'active' : '' ?>">
                <a href="/admin/attack-logs" class="nav-link">
                    <i class="layui-icon layui-icon-close-fill nav-icon"></i>
                    <span>攻击日志</span>
                </a>
            </li>
            
            <li class="nav-item <?= ($currentMenu ?? '') === 'plugins' ? 'active' : '' ?>">
                <a href="/admin/plugins" class="nav-link">
                    <i class="layui-icon layui-icon-component nav-icon"></i>
                    <span>插件管理</span>
                </a>
            </li>
            
            <li class="nav-item <?= ($currentMenu ?? '') === 'settings' ? 'active' : '' ?>">
                <a href="/admin/settings" class="nav-link">
                    <i class="layui-icon layui-icon-set-fill nav-icon"></i>
                    <span>系统设置</span>
                </a>
            </li>
        </ul>
    </div>
    
    <!-- 主内容区 -->
    <div class="main-content">
        <?php if (isset($showBreadcrumb) && $showBreadcrumb): ?>
        <div class="breadcrumb">
            <span class="layui-breadcrumb">
                <a href="/admin">首页</a>
                <?php if (isset($breadcrumbs) && is_array($breadcrumbs)): ?>
                    <?php foreach ($breadcrumbs as $crumb): ?>
                        <?php if (isset($crumb['url'])): ?>
                            <a href="<?= $crumb['url'] ?>"><?= $crumb['title'] ?></a>
                        <?php else: ?>
                            <a><cite><?= $crumb['title'] ?></cite></a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </span>
        </div>
        <?php endif; ?>

