<?php 
$pageTitle = 'Nginx 模式配置 - 天罡 WAF 管理控制台';
$currentPage = 'config';
$currentMenu = 'config-nginx';
$showBreadcrumb = true;
$breadcrumbs = [
    ['title' => '配置管理', 'url' => '/admin/config'],
    ['title' => 'Nginx 模式']
];

include __DIR__ . '/../layout/header.php';
?>

<style>
    .nginx-mode-card {
        background: white;
        border-radius: 8px;
        padding: 24px;
        margin-bottom: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    }
    
    .mode-selector {
        display: flex;
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .mode-option {
        flex: 1;
        border: 2px solid #e8e8e8;
        border-radius: 12px;
        padding: 24px;
        cursor: pointer;
        transition: all 0.3s;
        position: relative;
    }
    
    .mode-option:hover {
        border-color: #667eea;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.1);
    }
    
    .mode-option.active {
        border-color: #667eea;
        background: linear-gradient(135deg, #667eea10, #764ba210);
    }
    
    .mode-option.active::before {
        content: '✓';
        position: absolute;
        top: 10px;
        right: 10px;
        width: 30px;
        height: 30px;
        background: #667eea;
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        font-weight: bold;
    }
    
    .mode-icon {
        font-size: 48px;
        margin-bottom: 16px;
        text-align: center;
    }
    
    .mode-title {
        font-size: 18px;
        font-weight: 600;
        color: #364A63;
        margin-bottom: 12px;
        text-align: center;
    }
    
    .mode-desc {
        font-size: 14px;
        color: #666;
        text-align: center;
        line-height: 1.6;
    }
    
    .mode-features {
        margin-top: 16px;
        padding-top: 16px;
        border-top: 1px dashed #e8e8e8;
    }
    
    .mode-features li {
        font-size: 13px;
        color: #666;
        margin-bottom: 8px;
        padding-left: 20px;
        position: relative;
    }
    
    .mode-features li::before {
        content: '✓';
        position: absolute;
        left: 0;
        color: #52c41a;
        font-weight: bold;
    }
    
    .config-panel {
        display: none;
        animation: fadeIn 0.3s;
    }
    
    .config-panel.active {
        display: block;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .status-indicator {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 16px;
        border-radius: 20px;
        font-size: 14px;
        font-weight: 500;
    }
    
    .status-indicator.active {
        background: #f6ffed;
        color: #52c41a;
        border: 1px solid #b7eb8f;
    }
    
    .status-indicator.inactive {
        background: #fff7e6;
        color: #fa8c16;
        border: 1px solid #ffd591;
    }
    
    .status-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        animation: pulse 2s infinite;
    }
    
    .status-indicator.active .status-dot {
        background: #52c41a;
    }
    
    .status-indicator.inactive .status-dot {
        background: #fa8c16;
    }
    
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }
    
    .deployment-guide {
        background: linear-gradient(135deg, #667eea10, #764ba210);
        border-left: 4px solid #667eea;
        padding: 20px;
        border-radius: 8px;
        margin-top: 20px;
    }
    
    .deployment-guide h4 {
        color: #364A63;
        margin-bottom: 12px;
        font-size: 16px;
    }
    
    .deployment-steps {
        counter-reset: step;
        list-style: none;
        padding: 0;
    }
    
    .deployment-steps li {
        counter-increment: step;
        padding-left: 40px;
        margin-bottom: 16px;
        position: relative;
        line-height: 1.8;
    }
    
    .deployment-steps li::before {
        content: counter(step);
        position: absolute;
        left: 0;
        top: 0;
        width: 28px;
        height: 28px;
        background: #667eea;
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 14px;
    }
    
    .code-block {
        background: #1e1e1e;
        color: #d4d4d4;
        padding: 16px;
        border-radius: 6px;
        font-family: 'Consolas', 'Monaco', monospace;
        font-size: 13px;
        overflow-x: auto;
        margin: 12px 0;
        position: relative;
    }
    
    .code-block .copy-btn {
        position: absolute;
        top: 8px;
        right: 8px;
        background: rgba(255,255,255,0.1);
        border: none;
        color: white;
        padding: 6px 12px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 12px;
        transition: all 0.3s;
    }
    
    .code-block .copy-btn:hover {
        background: rgba(255,255,255,0.2);
    }
    
    .api-info {
        background: #f7f9fc;
        border: 1px solid #e8e8e8;
        border-radius: 6px;
        padding: 16px;
        margin-top: 16px;
    }
    
    .api-endpoint {
        font-family: 'Consolas', 'Monaco', monospace;
        background: white;
        padding: 8px 12px;
        border-radius: 4px;
        margin: 8px 0;
        border: 1px solid #e8e8e8;
        font-size: 13px;
    }
</style>

<div class="layui-fluid">
    <!-- 当前模式状态 -->
    <div class="nginx-mode-card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
            <div>
                <h2 style="margin: 0; color: #364A63; font-size: 20px;">部署模式配置</h2>
                <p style="margin: 8px 0 0 0; color: #999; font-size: 14px;">选择适合你的部署架构</p>
            </div>
            <div id="currentStatus"></div>
        </div>
        
        <!-- 模式选择器 -->
        <div class="mode-selector">
            <!-- 直连模式 -->
            <div class="mode-option" data-mode="direct">
                <div class="mode-icon">🚀</div>
                <div class="mode-title">直连模式</div>
                <div class="mode-desc">
                    浏览器直接连接 WAF，适合开发和测试环境
                </div>
                <ul class="mode-features">
                    <li>部署简单，开箱即用</li>
                    <li>适合开发和测试</li>
                    <li>无需额外 Nginx</li>
                    <li>单点故障风险较高</li>
                </ul>
            </div>
            
            <!-- 前置 Nginx 模式 -->
            <div class="mode-option active" data-mode="nginx">
                <div class="mode-icon">⚡</div>
                <div class="mode-title">前置 Nginx 模式（推荐）</div>
                <div class="mode-desc">
                    Nginx 作为前置代理，提供更好的性能和稳定性
                </div>
                <ul class="mode-features">
                    <li>SSL/TLS 卸载</li>
                    <li>静态资源加速</li>
                    <li>负载均衡和故障转移</li>
                    <li>动态配置更新（Lua）</li>
                    <li>推荐生产环境使用</li>
                </ul>
            </div>
        </div>
        
        <!-- 直连模式配置面板 -->
        <div class="config-panel" id="directConfig">
            <h3 style="color: #364A63; margin-bottom: 16px;">直连模式配置</h3>
            <p style="color: #666; margin-bottom: 20px;">
                当前架构：<code>浏览器 → 天罡 WAF (<?= env('PROXY_PORT', 8787) ?>) → 后端应用</code>
            </p>
            
            <div class="deployment-guide">
                <h4>部署步骤</h4>
                <ul class="deployment-steps">
                    <li>
                        <strong>配置域名映射：</strong>
                        在「域名映射」页面添加你的域名和后端地址
                    </li>
                    <li>
                        <strong>配置 DNS 解析：</strong>
                        将域名解析到运行 WAF 的服务器 IP
                    </li>
                    <li>
                        <strong>测试访问：</strong>
                        <div class="code-block">
                            <button class="copy-btn" onclick="copyCode(this)">复制</button>
curl http://your-domain.com/</div>
                    </li>
                </ul>
            </div>
            
            <div style="margin-top: 20px; padding: 16px; background: #fffbe6; border: 1px solid #ffe58f; border-radius: 6px;">
                <strong>⚠️ 注意：</strong> 直连模式下，WAF 需要暴露在公网，请确保管理后台端口 (<?= env('ADMIN_PORT', 8989) ?>) 不对外开放。
            </div>
        </div>
        
        <!-- 前置 Nginx 模式配置面板 -->
        <div class="config-panel active" id="nginxConfig">
            <h3 style="color: #364A63; margin-bottom: 16px;">前置 Nginx 模式配置</h3>
            <p style="color: #666; margin-bottom: 20px;">
                当前架构：<code>浏览器 → Nginx (80/443) → 天罡 WAF (<?= env('PROXY_PORT', 8787) ?>) → 后端应用</code>
            </p>
            
            <!-- 配置状态 -->
            <div class="api-info">
                <h4 style="margin: 0 0 12px 0; color: #364A63; font-size: 15px;">📡 配置 API 状态</h4>
                <div id="nginxApiStatus">
                    <div style="text-align: center; padding: 20px; color: #999;">
                        <i class="layui-icon layui-icon-loading layui-anim layui-anim-rotate layui-anim-loop"></i> 正在检测...
                    </div>
                </div>
            </div>
            
            <div class="deployment-guide">
                <h4>📋 部署步骤</h4>
                <ul class="deployment-steps">
                    <li>
                        <strong>安装 OpenResty：</strong>
                        <div class="code-block">
                            <button class="copy-btn" onclick="copyCode(this)">复制</button>
# Ubuntu/Debian
sudo apt update
sudo apt install -y openresty

# CentOS/RHEL
sudo yum install -y openresty

# macOS
brew install openresty/brew/openresty</div>
                    </li>
                    
                    <li>
                        <strong>复制 Lua 脚本：</strong>
                        <div class="code-block">
                            <button class="copy-btn" onclick="copyCode(this)">复制</button>
sudo cp nginx/lua/waf_config.lua /usr/local/openresty/lualib/</div>
                    </li>
                    
                    <li>
                        <strong>配置 Nginx：</strong>
                        将 <code>nginx/conf/waf.conf</code> 的内容添加到 Nginx 配置文件
                        <div style="margin-top: 12px;">
                            <button class="layui-btn layui-btn-sm" onclick="downloadConfig()">
                                <i class="layui-icon layui-icon-download-circle"></i> 下载配置文件
                            </button>
                            <button class="layui-btn layui-btn-sm layui-btn-normal" onclick="viewDocs()">
                                <i class="layui-icon layui-icon-read"></i> 查看详细文档
                            </button>
                        </div>
                    </li>
                    
                    <li>
                        <strong>测试并启动：</strong>
                        <div class="code-block">
                            <button class="copy-btn" onclick="copyCode(this)">复制</button>
# 测试配置
sudo openresty -t

# 启动服务
sudo systemctl start openresty
sudo systemctl enable openresty</div>
                    </li>
                    
                    <li>
                        <strong>验证部署：</strong>
                        <div style="margin-top: 12px;">
                            <button class="layui-btn layui-btn-sm layui-btn-warm" onclick="testNginxConnection()">
                                <i class="layui-icon layui-icon-ok-circle"></i> 测试 Nginx 连接
                            </button>
                            <button class="layui-btn layui-btn-sm" onclick="refreshCache()">
                                <i class="layui-icon layui-icon-refresh"></i> 刷新配置缓存
                            </button>
                        </div>
                    </li>
                </ul>
            </div>
            
            <!-- API 接口说明 -->
            <div style="margin-top: 30px;">
                <h4 style="color: #364A63; margin-bottom: 16px;">🔌 配置 API 接口</h4>
                <div class="api-info">
                    <div style="margin-bottom: 12px;">
                        <strong>配置导出接口：</strong>（供 Nginx Lua 脚本读取）
                        <div class="api-endpoint">
                            GET http://<?= $_SERVER['HTTP_HOST'] ?? 'localhost:8989' ?>/admin/api/nginx/config
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 12px;">
                        <strong>配置重载接口：</strong>
                        <div class="api-endpoint">
                            POST http://<?= $_SERVER['HTTP_HOST'] ?? 'localhost:8989' ?>/admin/api/nginx/reload
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 12px;">
                        <strong>状态查询接口：</strong>
                        <div class="api-endpoint">
                            GET http://<?= $_SERVER['HTTP_HOST'] ?? 'localhost:8989' ?>/admin/api/nginx/status
                        </div>
                    </div>
                    
                    <div>
                        <button class="layui-btn layui-btn-xs layui-btn-primary" onclick="testApi()">
                            <i class="layui-icon layui-icon-test"></i> 测试 API
                        </button>
                    </div>
                </div>
            </div>
            
            <div style="margin-top: 20px; padding: 16px; background: #e6f7ff; border: 1px solid #91d5ff; border-radius: 6px;">
                <strong>💡 提示：</strong> 配置更新后，Nginx 会在 60 秒内自动重新加载。如需立即生效，请点击「刷新配置缓存」按钮。
            </div>
        </div>
    </div>
</div>

<script>
layui.use(['layer', 'jquery'], function(){
    const layer = layui.layer;
    const $ = layui.jquery;
    
    // 加载状态
    loadStatus();
    
    // 模式切换
    $('.mode-option').on('click', function(){
        const mode = $(this).data('mode');
        
        $('.mode-option').removeClass('active');
        $(this).addClass('active');
        
        $('.config-panel').removeClass('active');
        $('#' + mode + 'Config').addClass('active');
        
        // 如果切换到 Nginx 模式，检查 API 状态
        if (mode === 'nginx') {
            checkNginxApiStatus();
        }
    });
    
    // 加载当前状态
    function loadStatus() {
        $.get('/admin/api/nginx/status', function(res){
            if (res.code === 0) {
                const data = res.data;
                const isNginxMode = data.nginx_mode === 'frontend_proxy';
                
                $('#currentStatus').html(
                    '<div class="status-indicator ' + (isNginxMode ? 'active' : 'inactive') + '">' +
                    '<span class="status-dot"></span>' +
                    '<span>' + (isNginxMode ? '前置 Nginx 模式' : '直连模式') + '</span>' +
                    '</div>'
                );
                
                if (isNginxMode) {
                    checkNginxApiStatus();
                }
            }
        });
    }
    
    // 检查 Nginx API 状态
    function checkNginxApiStatus() {
        $.get('/admin/api/nginx/status', function(res){
            if (res.code === 0) {
                const data = res.data;
                const html = `
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px;">
                        <div>
                            <span style="color: #999; font-size: 13px;">配置文件：</span>
                            <span style="font-size: 13px; color: #364A63;">${data.config_file}</span>
                        </div>
                        <div>
                            <span style="color: #999; font-size: 13px;">最后更新：</span>
                            <span style="font-size: 13px; color: #364A63;">${data.config_updated}</span>
                        </div>
                        <div>
                            <span style="color: #999; font-size: 13px;">域名映射：</span>
                            <span style="font-size: 13px; color: #52c41a; font-weight: bold;">${data.mappings.enabled} 个启用</span>
                            <span style="font-size: 13px; color: #999;"> / ${data.mappings.total} 个总计</span>
                        </div>
                        <div>
                            <span style="color: #999; font-size: 13px;">缓存时间：</span>
                            <span style="font-size: 13px; color: #364A63;">${data.cache_ttl} 秒</span>
                        </div>
                    </div>
                `;
                $('#nginxApiStatus').html(html);
            } else {
                $('#nginxApiStatus').html(
                    '<div style="text-align: center; padding: 20px; color: #ff4d4f;">' +
                    '<i class="layui-icon layui-icon-close"></i> API 连接失败：' + res.message +
                    '</div>'
                );
            }
        }).fail(function(){
            $('#nginxApiStatus').html(
                '<div style="text-align: center; padding: 20px; color: #ff4d4f;">' +
                '<i class="layui-icon layui-icon-close"></i> 无法连接到 API' +
                '</div>'
            );
        });
    }
    
    // 复制代码
    window.copyCode = function(btn) {
        const code = $(btn).parent().text().replace('复制', '').trim();
        
        if (navigator.clipboard) {
            navigator.clipboard.writeText(code).then(function(){
                layer.msg('已复制到剪贴板');
            });
        } else {
            // 兜底方案
            const textarea = document.createElement('textarea');
            textarea.value = code;
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
            layer.msg('已复制到剪贴板');
        }
    };
    
    // 下载配置文件
    window.downloadConfig = function() {
        window.location.href = '/admin/api/nginx/download-config';
    };
    
    // 查看文档
    window.viewDocs = function() {
        window.open('/docs/nginx-deployment.md', '_blank');
    };
    
    // 测试 Nginx 连接
    window.testNginxConnection = function() {
        const loading = layer.load(1, { shade: 0.3 });
        
        $.get('/admin/api/nginx/status', function(res){
            layer.close(loading);
            
            if (res.code === 0) {
                layer.alert('✅ Nginx 配置 API 连接成功！<br><br>' +
                    '域名映射：' + res.data.mappings.enabled + ' 个启用<br>' +
                    '缓存时间：' + res.data.cache_ttl + ' 秒',
                    { icon: 1, title: '连接测试' }
                );
            } else {
                layer.alert('❌ 连接失败：' + res.message, { icon: 2, title: '连接测试' });
            }
        }).fail(function(){
            layer.close(loading);
            layer.alert('❌ 无法连接到 Nginx API<br><br>请确认：<br>1. WAF 服务已启动<br>2. 端口配置正确', 
                { icon: 2, title: '连接测试' }
            );
        });
    };
    
    // 刷新配置缓存
    window.refreshCache = function() {
        const loading = layer.load(1, { shade: 0.3 });
        
        $.post('/admin/api/nginx/reload', function(res){
            layer.close(loading);
            
            if (res.code === 0) {
                layer.msg('✅ 配置缓存已刷新', { icon: 1 });
                checkNginxApiStatus();
            } else {
                layer.msg('❌ 刷新失败：' + res.message, { icon: 2 });
            }
        }).fail(function(){
            layer.close(loading);
            layer.msg('❌ 请求失败', { icon: 2 });
        });
    };
    
    // 测试 API
    window.testApi = function() {
        const loading = layer.load(1, { shade: 0.3 });
        
        $.get('/admin/api/nginx/config', function(res){
            layer.close(loading);
            
            if (res.code === 0) {
                layer.open({
                    type: 1,
                    title: 'API 测试结果',
                    area: ['800px', '600px'],
                    content: '<pre style="padding: 20px; background: #1e1e1e; color: #d4d4d4; overflow: auto; max-height: 500px;">' +
                        JSON.stringify(res.data, null, 2) +
                        '</pre>'
                });
            } else {
                layer.alert('❌ API 返回错误：' + res.message, { icon: 2 });
            }
        }).fail(function(){
            layer.close(loading);
            layer.alert('❌ API 请求失败', { icon: 2 });
        });
    };
});
</script>

<?php include __DIR__ . '/../layout/footer.php'; ?>

