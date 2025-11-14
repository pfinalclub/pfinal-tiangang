<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>插件管理 - Tiangang WAF</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f5f6fa;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .header {
            background: white;
            padding: 20px 30px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .header h1 {
            font-size: 24px;
            color: #333;
            margin-bottom: 10px;
        }
        .header p {
            color: #666;
            font-size: 14px;
        }
        .actions {
            margin-bottom: 20px;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #5FB878;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            margin-right: 10px;
        }
        .btn:hover {
            background: #4BAA66;
        }
        .btn-secondary {
            background: #1E9FFF;
        }
        .btn-secondary:hover {
            background: #0B8BFF;
        }
        .plugins-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }
        .plugin-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        .plugin-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .plugin-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }
        .plugin-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        .plugin-version {
            font-size: 12px;
            color: #999;
        }
        .plugin-status {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }
        .status-active {
            background: #E8F8F5;
            color: #5FB878;
        }
        .status-licensed {
            background: #FFF4E5;
            color: #FFB800;
        }
        .status-unlicensed {
            background: #FFF0F0;
            color: #FF5722;
        }
        .plugin-description {
            color: #666;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 15px;
        }
        .plugin-meta {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
            padding: 10px 0;
            border-top: 1px solid #f0f0f0;
            border-bottom: 1px solid #f0f0f0;
        }
        .meta-item {
            font-size: 13px;
            color: #666;
        }
        .meta-label {
            font-weight: 600;
            color: #333;
        }
        .plugin-actions {
            display: flex;
            gap: 10px;
        }
        .plugin-btn {
            flex: 1;
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            transition: all 0.2s;
        }
        .btn-activate {
            background: #5FB878;
            color: white;
        }
        .btn-activate:hover {
            background: #4BAA66;
        }
        .btn-configure {
            background: #1E9FFF;
            color: white;
        }
        .btn-configure:hover {
            background: #0B8BFF;
        }
        .btn-uninstall {
            background: #FF5722;
            color: white;
        }
        .btn-uninstall:hover {
            background: #E64A19;
        }
        .empty-state {
            background: white;
            padding: 60px 20px;
            text-align: center;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 20px;
        }
        .empty-state-title {
            font-size: 20px;
            color: #333;
            margin-bottom: 10px;
        }
        .empty-state-text {
            color: #666;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>插件管理</h1>
            <p>管理 WAF 插件，启用或禁用防护功能，激活许可证</p>
        </div>

        <div class="actions">
            <button class="btn" onclick="uploadPlugin()">上传插件</button>
            <a href="/admin/plugins/market" class="btn btn-secondary">插件市场</a>
            <a href="/admin/plugins/license" class="btn btn-secondary">许可证管理</a>
        </div>

        <div class="plugins-grid" id="pluginsGrid">
            <!-- 插件卡片将通过JavaScript动态加载 -->
        </div>

        <div class="empty-state" id="emptyState" style="display: none;">
            <div class="empty-state-icon">📦</div>
            <div class="empty-state-title">暂无插件</div>
            <div class="empty-state-text">点击上传插件或访问插件市场安装插件</div>
            <button class="btn" onclick="uploadPlugin()">上传插件</button>
        </div>
    </div>

    <script>
        // 加载插件列表
        async function loadPlugins() {
            try {
                const response = await fetch('/admin/plugins');
                const data = await response.json();
                
                if (data.code === 0 && data.data.plugins.length > 0) {
                    renderPlugins(data.data.plugins);
                } else {
                    document.getElementById('emptyState').style.display = 'block';
                }
            } catch (error) {
                console.error('加载插件失败:', error);
                alert('加载插件失败，请刷新页面重试');
            }
        }

        // 渲染插件列表
        function renderPlugins(plugins) {
            const grid = document.getElementById('pluginsGrid');
            grid.innerHTML = plugins.map(plugin => `
                <div class="plugin-card">
                    <div class="plugin-header">
                        <div>
                            <div class="plugin-title">${plugin.name}</div>
                            <div class="plugin-version">v${plugin.version}</div>
                        </div>
                        <span class="plugin-status ${getStatusClass(plugin)}">
                            ${getStatusText(plugin)}
                        </span>
                    </div>
                    <div class="plugin-description">${plugin.description}</div>
                    <div class="plugin-meta">
                        <div class="meta-item">
                            <span class="meta-label">优先级:</span> ${plugin.priority}
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">快速检测:</span> ${plugin.supports_quick_detection ? '✓' : '✗'}
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">需要许可证:</span> ${plugin.requires_license ? '是' : '否'}
                        </div>
                    </div>
                    <div class="plugin-actions">
                        ${getActionButtons(plugin)}
                    </div>
                </div>
            `).join('');
        }

        // 获取状态样式类
        function getStatusClass(plugin) {
            if (plugin.enabled && plugin.license_valid) return 'status-active';
            if (plugin.requires_license && !plugin.license_valid) return 'status-unlicensed';
            return 'status-licensed';
        }

        // 获取状态文本
        function getStatusText(plugin) {
            if (plugin.enabled && plugin.license_valid) return '已激活';
            if (plugin.requires_license && !plugin.license_valid) return '未授权';
            if (plugin.enabled) return '已启用';
            return '未启用';
        }

        // 获取操作按钮
        function getActionButtons(plugin) {
            let buttons = '';
            
            if (plugin.requires_license && !plugin.license_valid) {
                buttons += `<button class="plugin-btn btn-activate" onclick="activatePlugin('${plugin.name}')">激活许可证</button>`;
            }
            
            buttons += `<button class="plugin-btn btn-configure" onclick="configPlugin('${plugin.name}')">配置</button>`;
            buttons += `<button class="plugin-btn btn-uninstall" onclick="uninstallPlugin('${plugin.name}')">卸载</button>`;
            
            return buttons;
        }

        // 上传插件
        function uploadPlugin() {
            alert('上传插件功能开发中...\n\n请准备 ZIP 格式的插件包');
            // TODO: 实现文件上传对话框
        }

        // 激活插件许可证
        async function activatePlugin(pluginName) {
            const licenseKey = prompt(`请输入 ${pluginName} 的许可证密钥:`);
            if (!licenseKey) return;
            
            try {
                const response = await fetch('/admin/plugins/activate', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `plugin_name=${pluginName}&license_key=${licenseKey}`
                });
                const data = await response.json();
                
                if (data.code === 0) {
                    alert('许可证激活成功！');
                    loadPlugins();
                } else {
                    alert('激活失败: ' + data.msg);
                }
            } catch (error) {
                console.error('激活失败:', error);
                alert('激活失败，请重试');
            }
        }

        // 配置插件
        function configPlugin(pluginName) {
            window.location.href = `/admin/plugins/config?plugin_name=${pluginName}`;
        }

        // 卸载插件
        async function uninstallPlugin(pluginName) {
            if (!confirm(`确定要卸载插件 ${pluginName}？此操作不可恢复。`)) return;
            
            try {
                const response = await fetch('/admin/plugins/uninstall', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `plugin_name=${pluginName}`
                });
                const data = await response.json();
                
                if (data.code === 0) {
                    alert('插件卸载成功！');
                    loadPlugins();
                } else {
                    alert('卸载失败: ' + data.msg);
                }
            } catch (error) {
                console.error('卸载失败:', error);
                alert('卸载失败，请重试');
            }
        }

        // 页面加载时获取插件列表
        document.addEventListener('DOMContentLoaded', loadPlugins);
    </script>
</body>
</html>

