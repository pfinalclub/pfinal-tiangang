<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>配置管理 - 天罡 WAF</title>
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
            padding: 20px;
        }
        
        .config-section {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .config-section h3 {
            margin-bottom: 20px;
            color: #333;
            border-bottom: 2px solid #5FB878;
            padding-bottom: 10px;
        }
        .mapping-item {
            border: 1px solid #e6e6e6;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 10px;
            background: #fafafa;
        }
        .mapping-item:hover {
            background: #f0f0f0;
        }
        .status-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 12px;
        }
        .status-enabled {
            background: #5FB878;
            color: white;
        }
        .status-disabled {
            background: #FF5722;
            color: white;
        }
        
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
                <li class="nav-item">
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
                <li class="nav-item active">
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
            <h2 style="margin-bottom: 20px;">🛡️ 配置管理</h2>
        
        <!-- 后端服务配置 -->
        <div class="config-section">
            <h3>后端服务</h3>
            <div id="backends-list">
                <div class="layui-loading" style="text-align: center; padding: 20px;">
                    <i class="layui-icon layui-icon-loading layui-anim layui-anim-rotate layui-anim-loop"></i>
                    <p>加载中...</p>
                </div>
            </div>
        </div>
        
        <!-- 域名映射配置 -->
        <div class="config-section">
            <h3>域名映射（主要路由方式）</h3>
            <button class="layui-btn layui-btn-sm" onclick="showAddDomainModal()">
                <i class="layui-icon layui-icon-add-1"></i> 添加域名映射
            </button>
            <div id="domain-mappings-list" style="margin-top: 15px;">
                <div class="layui-loading" style="text-align: center; padding: 20px;">
                    <i class="layui-icon layui-icon-loading layui-anim layui-anim-rotate layui-anim-loop"></i>
                    <p>加载中...</p>
                </div>
            </div>
        </div>
        
        <!-- 路径映射配置 -->
        <div class="config-section">
            <h3>路径映射（补充路由方式）</h3>
            <button class="layui-btn layui-btn-sm" onclick="showAddMappingModal()">
                <i class="layui-icon layui-icon-add-1"></i> 添加路径映射
            </button>
            <div id="mappings-list" style="margin-top: 15px;">
                <div class="layui-loading" style="text-align: center; padding: 20px;">
                    <i class="layui-icon layui-icon-loading layui-anim layui-anim-rotate layui-anim-loop"></i>
                    <p>加载中...</p>
                </div>
            </div>
        </div>
        
        <!-- WAF 规则配置 -->
        <div class="config-section">
            <h3>WAF 保护规则</h3>
            <div id="waf-rules">
                <div class="layui-loading" style="text-align: center; padding: 20px;">
                    <i class="layui-icon layui-icon-loading layui-anim layui-anim-rotate layui-anim-loop"></i>
                    <p>加载中...</p>
                </div>
            </div>
        </div>
        </div>
    </div>
    
    <script src="//unpkg.com/layui@2.12.1/dist/layui.js"></script>
    <script>
        // CSRF Token
        const csrfToken = '<?= htmlspecialchars($csrfToken ?? '') ?>';
        layui.use(['layer', 'form'], function(){
            var layer = layui.layer;
            var form = layui.form;
            
            // 加载数据
            loadBackends();
            loadDomainMappings();
            loadPathMappings();
            loadWafRules();
        });
        
        async function loadBackends() {
            try {
                const response = await fetch("/admin/api/config/backends");
                const result = await response.json();
                
                if (result.code === 0) {
                    renderBackends(result.data.backends);
                }
            } catch (error) {
                console.error("加载后端服务失败:", error);
            }
        }
        
        function renderBackends(backends) {
            const container = document.getElementById("backends-list");
            if (!backends || backends.length === 0) {
                container.innerHTML = "<p>暂无后端服务配置</p>";
                return;
            }
            
            let html = "<table class=\"layui-table\">";
            html += "<thead><tr><th>名称</th><th>URL</th><th>权重</th><th>健康状态</th><th>操作</th></tr></thead>";
            html += "<tbody>";
            
            backends.forEach(backend => {
                const health = backend.health || {};
                const healthStatus = health.healthy ? "健康" : "异常";
                const healthClass = health.healthy ? "status-enabled" : "status-disabled";
                
                html += `<tr>
                    <td>${backend.name || '-'}</td>
                    <td>${backend.url || '-'}</td>
                    <td>${backend.weight || 1}</td>
                    <td><span class="status-badge ${healthClass}">${healthStatus}</span></td>
                    <td><button class="layui-btn layui-btn-xs">编辑</button></td>
                </tr>`;
            });
            
            html += "</tbody></table>";
            container.innerHTML = html;
        }
        
        async function loadDomainMappings() {
            try {
                const response = await fetch("/admin/api/config/domain-mappings");
                const result = await response.json();
                
                if (result.code === 0) {
                    renderDomainMappings(result.data.mappings);
                }
            } catch (error) {
                console.error("加载域名映射失败:", error);
            }
        }
        
        function renderDomainMappings(mappings) {
            const container = document.getElementById("domain-mappings-list");
            if (!mappings || mappings.length === 0) {
                container.innerHTML = "<p>暂无域名映射配置</p>";
                return;
            }
            
            let html = "<table class=\"layui-table\">";
            html += "<thead><tr><th>域名</th><th>后端服务</th><th>WAF规则</th><th>状态</th><th>操作</th></tr></thead>";
            html += "<tbody>";
            
            mappings.forEach(mapping => {
                const enabled = mapping.enabled !== false;
                const statusClass = enabled ? "status-enabled" : "status-disabled";
                const statusText = enabled ? "启用" : "禁用";
                const wafRules = (mapping.waf_rules || []).join(", ") || "无";
                
                html += `<tr>
                    <td>${mapping.domain || '-'}</td>
                    <td>${mapping.backend || '-'}</td>
                    <td>${wafRules}</td>
                    <td><span class="status-badge ${statusClass}">${statusText}</span></td>
                    <td>
                        <button class="layui-btn layui-btn-xs" onclick="editDomainMapping('${mapping.domain}')">编辑</button>
                        <button class="layui-btn layui-btn-xs layui-btn-danger" onclick="deleteDomainMapping('${mapping.domain}')">删除</button>
                    </td>
                </tr>`;
            });
            
            html += "</tbody></table>";
            container.innerHTML = html;
        }
        
        function showAddDomainModal() {
            layer.open({
                type: 2,
                title: "添加域名映射",
                area: ["600px", "500px"],
                content: "/admin/config/domain-form",
                end: function() {
                    loadDomainMappings();
                }
            });
        }
        
        function editDomainMapping(domain) {
            layer.open({
                type: 2,
                title: "编辑域名映射",
                area: ["600px", "500px"],
                content: "/admin/config/domain-form?domain=" + encodeURIComponent(domain),
                end: function() {
                    loadDomainMappings();
                }
            });
        }
        
        async function deleteDomainMapping(domain) {
            if (!confirm("确定要删除域名映射 " + domain + " 吗？")) {
                return;
            }
            
            try {
                const formData = new FormData();
                formData.append("domain", domain);
                formData.append("_token", csrfToken);
                
                const response = await fetch("/admin/api/config/domain-mapping/delete", {
                    method: "POST",
                    body: formData
                });
                
                const result = await response.json();
                if (result.code === 0) {
                    layer.msg("删除成功", {icon: 1});
                    loadDomainMappings();
                } else {
                    layer.msg(result.msg || "删除失败", {icon: 2});
                }
            } catch (error) {
                layer.msg("删除失败: " + error.message, {icon: 2});
            }
        }
        
        async function loadPathMappings() {
            try {
                const response = await fetch("/admin/api/config/path-mappings");
                const result = await response.json();
                
                if (result.code === 0) {
                    renderPathMappings(result.data.mappings);
                }
            } catch (error) {
                console.error("加载路径映射失败:", error);
            }
        }
        
        function renderPathMappings(mappings) {
            const container = document.getElementById("mappings-list");
            if (!mappings || mappings.length === 0) {
                container.innerHTML = "<p>暂无路径映射，点击上方按钮添加</p>";
                return;
            }
            
            let html = "";
            mappings.forEach(mapping => {
                const enabled = mapping.enabled !== false;
                const statusClass = enabled ? "status-enabled" : "status-disabled";
                const statusText = enabled ? "启用" : "禁用";
                const stripPrefix = mapping.strip_prefix ? "是" : "否";
                const wafRules = (mapping.waf_rules || []).join(", ") || "默认规则";
                
                html += `<div class="mapping-item">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <strong>${mapping.path || '-'}</strong>
                            <span class="status-badge ${statusClass}" style="margin-left: 10px;">${statusText}</span>
                        </div>
                        <div>
                            <button class="layui-btn layui-btn-xs" onclick="editMapping('${mapping.path}')">编辑</button>
                            <button class="layui-btn layui-btn-danger layui-btn-xs" onclick="deleteMapping('${mapping.path}')">删除</button>
                        </div>
                    </div>
                    <div style="margin-top: 10px; color: #666; font-size: 14px;">
                        <span>后端: <strong>${mapping.backend || '-'}</strong></span>
                        <span style="margin-left: 20px;">移除前缀: <strong>${stripPrefix}</strong></span>
                        <span style="margin-left: 20px;">WAF规则: <strong>${wafRules}</strong></span>
                    </div>
                </div>`;
            });
            
            container.innerHTML = html;
        }
        
        async function loadWafRules() {
            try {
                const response = await fetch("/admin/api/config/waf-rules");
                const result = await response.json();
                
                if (result.code === 0) {
                    renderWafRules(result.data.rules);
                }
            } catch (error) {
                console.error("加载WAF规则失败:", error);
            }
        }
        
        function renderWafRules(rules) {
            const container = document.getElementById("waf-rules");
            const enabled = rules.enabled || [];
            const priority = rules.priority || {};
            
            let html = "<table class=\"layui-table\">";
            html += "<thead><tr><th>规则名称</th><th>状态</th><th>优先级</th><th>操作</th></tr></thead>";
            html += "<tbody>";
            
            const allRules = ["sql_injection", "xss", "rate_limit", "ip_blacklist"];
            allRules.forEach(rule => {
                const isEnabled = enabled.includes(rule);
                const rulePriority = priority[rule] || 0;
                const statusClass = isEnabled ? "status-enabled" : "status-disabled";
                const statusText = isEnabled ? "启用" : "禁用";
                
                html += `<tr>
                    <td>${rule}</td>
                    <td><span class="status-badge ${statusClass}">${statusText}</span></td>
                    <td>${rulePriority}</td>
                    <td><button class="layui-btn layui-btn-xs">配置</button></td>
                </tr>`;
            });
            
            html += "</tbody></table>";
            container.innerHTML = html;
        }
        
        function showAddMappingModal() {
            layer.open({
                type: 2,
                title: "添加路径映射",
                area: ["600px", "500px"],
                content: "/admin/config/mapping-form",
                end: function() {
                    loadPathMappings();
                }
            });
        }
        
        function editMapping(path) {
            layer.open({
                type: 2,
                title: "编辑路径映射",
                area: ["600px", "500px"],
                content: "/admin/config/mapping-form?path=" + encodeURIComponent(path),
                end: function() {
                    loadPathMappings();
                }
            });
        }
        
        async function deleteMapping(path) {
            layer.confirm("确定要删除这个路径映射吗？", {icon: 3, title: "确认删除"}, async function(index) {
                try {
                    const formData = new FormData();
                    formData.append("_token", csrfToken);
                    
                    const response = await fetch("/admin/api/config/path-mapping/delete?path=" + encodeURIComponent(path), {
                        method: "POST",
                        body: formData
                    });
                    const result = await response.json();
                    
                    if (result.code === 0) {
                        layer.msg("删除成功", {icon: 1});
                        loadPathMappings();
                    } else {
                        layer.msg(result.msg || "删除失败", {icon: 2});
                    }
                } catch (error) {
                    layer.msg("删除失败: " + error.message, {icon: 2});
                }
                
                layer.close(index);
            });
        }
    </script>
</body>
</html>

