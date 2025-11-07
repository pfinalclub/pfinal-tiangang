<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>天罡 WAF 管理控制台</title>
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
            margin: 0;
            padding: 0;
        }
        
        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }
        
        /* 侧边栏 - 根据 Figma 设计 */
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
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            flex-direction: column;
        }
        
        .logo {
            text-align: center;
            padding: 20px;
            font-weight: 400;
            font-size: 14px;
            color: #364A63;
            margin-bottom: 30px;
        }
        
        .nav-menu {
            list-style: none;
            padding: 0;
            margin: 0;
            flex: 1;
        }
        
        .nav-item {
            margin: 0;
            position: relative;
        }
        
        .nav-item.active .nav-link {
            background: #F5F6FA;
            border-radius: 100px 0 0 100px;
        }
        
        .nav-item.active .nav-icon {
            background: #FFFFFF;
            border-radius: 50%;
            padding: 4px;
        }
        
        .nav-link {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: #364A63;
            text-decoration: none;
            font-size: 14px;
            font-weight: 400;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .nav-link:hover {
            background: #EFEFF2;
            border-radius: 100px 0 0 100px;
        }
        
        .nav-icon {
            width: 20px;
            height: 20px;
            margin-right: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .nav-icon svg {
            width: 100%;
            height: 100%;
            stroke: #364A63;
            fill: none;
        }
        
        .nav-item.active .nav-icon svg {
            stroke: #364A63;
        }
        
        .nav-text {
            flex: 1;
        }
        
        /* 主内容区域 */
        .main-content {
            flex: 1;
            margin-left: 180px;
            padding: 30px;
            background: #F5F6FA;
        }
        
        /* 页面头部 - 根据 Figma 设计 */
        .dashboard-header {
            background: linear-gradient(180deg, rgba(220, 36, 48, 1) 7%, rgba(219, 35, 121, 1) 56%, rgba(123, 67, 151, 1) 100%);
            color: white;
            padding: 40px;
            border-radius: 20px;
            margin-bottom: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        .dashboard-header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            font-weight: 700;
        }
        .dashboard-header p {
            font-size: 1.1rem;
            opacity: 0.95;
        }
        
        /* 统计卡片 - 根据 Figma 设计 */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: #FFFFFF;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            border: 1px solid #EFEFF2;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        .stat-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        .stat-label {
            color: #364A63;
            font-size: 14px;
            font-weight: 500;
            opacity: 0.7;
        }
        .stat-value {
            font-size: 2.2rem;
            font-weight: 700;
            color: #364A63;
            margin: 10px 0;
        }
        .stat-change {
            font-size: 13px;
            padding: 4px 8px;
            border-radius: 6px;
            display: inline-block;
        }
        .stat-change.positive {
            background: rgba(30, 224, 172, 0.1);
            color: #1EE0AC;
        }
        .stat-change.negative {
            background: rgba(220, 36, 48, 0.1);
            color: #DC2430;
        }
        
        /* 图表容器 - 根据 Figma 设计 */
        .chart-container {
            background: #FFFFFF;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border: 1px solid #EFEFF2;
        }
        .chart-container h3 {
            color: #364A63;
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }
        .chart-container h3 i {
            margin-right: 10px;
        }
        
        .status-online { color: #1EE0AC; }
        .status-warning { color: #FFB800; }
        .status-offline { color: #DC2430; }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- 侧边栏 - 根据 Figma 设计 -->
        <nav class="sidebar">
            <div class="logo">天罡 WAF</div>
            <ul class="nav-menu">
                <li class="nav-item active">
                    <a href="/admin" class="nav-link">
                        <div class="nav-icon">
                            <svg viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/>
                            </svg>
                        </div>
                        <span class="nav-text">仪表板</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <div class="nav-icon">
                            <svg viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                <circle cx="9" cy="7" r="4"></circle>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                            </svg>
                        </div>
                        <span class="nav-text">用户管理</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <div class="nav-icon">
                            <svg viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                            </svg>
                        </div>
                        <span class="nav-text">消息中心</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/admin/config" class="nav-link">
                        <div class="nav-icon">
                            <svg viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="3"></circle>
                                <path d="M12 1v6m0 6v6m9-9h-6m-6 0H3m15.364 6.364l-4.243-4.243m-4.242 0L5.636 18.364m12.728 0l-4.243-4.243m-4.242 0L5.636 5.636"></path>
                            </svg>
                        </div>
                        <span class="nav-text">配置管理</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <div class="nav-icon">
                            <svg viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="3"></circle>
                                <path d="M12 1v6m0 6v6m9-9h-6m-6 0H3m15.364 6.364l-4.243-4.243m-4.242 0L5.636 18.364m12.728 0l-4.243-4.243m-4.242 0L5.636 5.636"></path>
                            </svg>
                        </div>
                        <span class="nav-text">设置</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/admin/auth/logout" class="nav-link">
                        <div class="nav-icon">
                            <svg viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                                <polyline points="16 17 21 12 16 7"></polyline>
                                <line x1="21" y1="12" x2="9" y2="12"></line>
                            </svg>
                        </div>
                        <span class="nav-text">退出登录</span>
                    </a>
                </li>
            </ul>
        </nav>
        
        <!-- 主内容区域 -->
        <div class="main-content">
            <!-- 页面头部 -->
            <div class="dashboard-header">
                <h1>🛡️ 天罡 WAF 管理控制台</h1>
                <p>实时监控和管理您的 Web 应用防火墙</p>
            </div>

            <!-- 统计卡片 -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-label">总请求数</div>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
                        </svg>
                    </div>
                    <div class="stat-value" id="total-requests">-</div>
                    <div class="stat-change positive" id="requests-change">加载中...</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-label">拦截请求</div>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                        </svg>
                    </div>
                    <div class="stat-value" id="blocked-requests">-</div>
                    <div class="stat-change" id="blocked-change">加载中...</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-label">响应时间</div>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <path d="M12 6v6l4 2"/>
                        </svg>
                    </div>
                    <div class="stat-value" id="response-time">-</div>
                    <div class="stat-change positive" id="time-change">加载中...</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-label">系统状态</div>
                        <i class="layui-icon layui-icon-ok-circle status-online" id="status-icon" style="font-size: 20px;"></i>
                    </div>
                    <div class="stat-value" style="font-size: 1.5rem;" id="system-status">在线</div>
                    <div class="stat-change" id="uptime">运行时间: 计算中...</div>
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
    </div>

    <script src="//unpkg.com/layui@2.12.1/dist/layui.js"></script>
    <script>
        layui.use(['layer', 'element'], function(){
            var layer = layui.layer;
            var element = layui.element;
            
            // 显示欢迎消息
            layer.msg('欢迎使用天罡 WAF 管理控制台', {icon: 6, time: 2000});
            
            // 加载数据
            loadDashboardData();
            
            // 每5秒更新数据
            setInterval(loadDashboardData, 5000);
        });

        async function loadDashboardData() {
            try {
                const response = await fetch("/admin/api/dashboard");
                const data = await response.json();
                
                if (data.code === 0) {
                    updateStats(data.data);
                }
            } catch (error) {
                console.error("加载数据失败:", error);
            }
        }

        function updateStats(data) {
            // 更新总请求数
            const totalRequests = data.overview?.total_requests || 0;
            document.getElementById("total-requests").textContent = totalRequests.toLocaleString();
            
            // 更新拦截请求
            const blockedRequests = data.overview?.blocked_requests || 0;
            document.getElementById("blocked-requests").textContent = blockedRequests.toLocaleString();
            
            // 更新响应时间
            const responseTime = data.performance?.avg_response_time || 0;
            document.getElementById("response-time").textContent = responseTime + "ms";
            
            // 更新变化指标
            const requestsChange = data.overview?.requests_change || 0;
            const requestsChangeEl = document.getElementById("requests-change");
            requestsChangeEl.textContent = "较昨日: " + (requestsChange >= 0 ? "+" : "") + requestsChange + "%";
            requestsChangeEl.className = "stat-change " + (requestsChange >= 0 ? "positive" : "negative");
            
            const blockedRate = data.overview?.block_rate || 0;
            document.getElementById("blocked-change").textContent = "拦截率: " + blockedRate + "%";
            
            const timeChange = data.performance?.time_change || 0;
            const timeChangeEl = document.getElementById("time-change");
            timeChangeEl.textContent = "较昨日: " + (timeChange >= 0 ? "+" : "") + timeChange + "%";
            timeChangeEl.className = "stat-change " + (timeChange >= 0 ? "positive" : "negative");
            
            // 更新系统状态
            if (data.system) {
                document.getElementById("system-status").textContent = data.system.status === 'online' ? '在线' : '离线';
                document.getElementById("uptime").textContent = "运行时间: " + (data.system.uptime || '计算中...');
            }
        }
    </script>
</body>
</html>

