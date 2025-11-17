<?php 
$pageTitle = '配置管理 - 天罡 WAF 管理控制台';
$currentPage = 'config';
$currentMenu = 'config';
$showBreadcrumb = true;
$breadcrumbs = [
    ['title' => '配置管理']
];

include __DIR__ . '/../layout/header.php';
?>

<style>
    /* 页面特定样式 */
    .config-section {
        background: white;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    }
    
    .config-section h3 {
        margin-bottom: 20px;
        color: #364A63;
        border-bottom: 2px solid #667eea;
        padding-bottom: 10px;
        font-size: 16px;
        font-weight: 600;
    }
    
    .mapping-item {
        border: 1px solid #e8e8e8;
        border-radius: 6px;
        padding: 16px;
        margin-bottom: 12px;
        background: #fafafa;
        transition: all 0.3s;
    }
    
    .mapping-item:hover {
        background: #f5f5f5;
        border-color: #667eea;
        box-shadow: 0 2px 8px rgba(102, 126, 234, 0.1);
    }
    
    .status-badge {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 500;
    }
    
    .status-enabled {
        background: #f6ffed;
        color: #52c41a;
        border: 1px solid #b7eb8f;
    }
    
    .status-disabled {
        background: #fff2f0;
        color: #ff4d4f;
        border: 1px solid #ffccc7;
    }
</style>

<div class="content-card">
    <div class="content-card-header">
        <h2 class="content-card-title">
            <i class="layui-icon layui-icon-set"></i> 配置管理
        </h2>
    </div>
        
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

<?php include __DIR__ . '/../layout/footer.php'; ?>

