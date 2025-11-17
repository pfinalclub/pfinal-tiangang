<?php 
$pageTitle = '访问日志 - 天罡 WAF 管理控制台';
$currentPage = 'logs';
$currentMenu = 'logs';
$showBreadcrumb = true;
$breadcrumbs = [
    ['title' => '日志管理'],
    ['title' => '访问日志']
];

include __DIR__ . '/../layout/header.php';
?>

<style>
    .stats-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 15px;
        margin-bottom: 20px;
    }
    
    .stat-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: #FFFFFF;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    
    .stat-card.success {
        background: linear-gradient(135deg, #52c41a 0%, #389e0d 100%);
    }
    
    .stat-card.warning {
        background: linear-gradient(135deg, #fa8c16 0%, #d46b08 100%);
    }
    
    .stat-card.danger {
        background: linear-gradient(135deg, #ff4d4f 0%, #cf1322 100%);
    }
    
    .stat-label {
        font-size: 13px;
        opacity: 0.9;
        margin-bottom: 8px;
    }
    
    .stat-value {
        font-size: 28px;
        font-weight: 600;
    }
    
    .filter-bar {
        background: #FFFFFF;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    }
    
    .filter-bar .layui-form-item {
        margin-bottom: 10px;
    }
    
    .log-table-container {
        background: #FFFFFF;
        border-radius: 8px;
        padding: 0;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    }
    
    .status-badge {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 500;
    }
    
    .status-success {
        background: #f6ffed;
        color: #52c41a;
        border: 1px solid #b7eb8f;
    }
    
    .status-error {
        background: #fff2f0;
        color: #ff4d4f;
        border: 1px solid #ffccc7;
    }
    
    .status-warning {
        background: #fff7e6;
        color: #fa8c16;
        border: 1px solid #ffd591;
    }
    
    .method-badge {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 600;
        font-family: 'Courier New', monospace;
    }
    
    .method-get { background: #e6f7ff; color: #0050b3; }
    .method-post { background: #f6ffed; color: #237804; }
    .method-put { background: #fff7e6; color: #ad4e00; }
    .method-delete { background: #fff2f0; color: #a8071a; }
</style>

<div class="content-card">
    <div class="content-card-header">
        <h2 class="content-card-title">
            <i class="layui-icon layui-icon-file"></i> 访问日志统计
        </h2>
        <div>
            <button class="layui-btn layui-btn-sm" onclick="refreshLogs()">
                <i class="layui-icon layui-icon-refresh"></i> 刷新
            </button>
            <button class="layui-btn layui-btn-sm layui-btn-primary" onclick="exportLogs()">
                <i class="layui-icon layui-icon-export"></i> 导出
            </button>
        </div>
    </div>
    
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-label">总请求数</div>
            <div class="stat-value" id="totalRequests">-</div>
        </div>
        <div class="stat-card success">
            <div class="stat-label">成功请求</div>
            <div class="stat-value" id="successRequests">-</div>
        </div>
        <div class="stat-card warning">
            <div class="stat-label">被拦截</div>
            <div class="stat-value" id="blockedRequests">-</div>
        </div>
        <div class="stat-card danger">
            <div class="stat-label">错误请求</div>
            <div class="stat-value" id="errorRequests">-</div>
        </div>
    </div>
</div>

<div class="filter-bar">
    <form class="layui-form" lay-filter="logFilter">
        <div class="layui-row layui-col-space10">
            <div class="layui-col-md3">
                <div class="layui-form-item">
                    <label class="layui-form-label">时间范围</label>
                    <div class="layui-input-block">
                        <input type="text" class="layui-input" id="timeRange" placeholder="选择时间范围">
                    </div>
                </div>
            </div>
            <div class="layui-col-md2">
                <div class="layui-form-item">
                    <label class="layui-form-label">请求方法</label>
                    <div class="layui-input-block">
                        <select name="method" lay-filter="method">
                            <option value="">全部</option>
                            <option value="GET">GET</option>
                            <option value="POST">POST</option>
                            <option value="PUT">PUT</option>
                            <option value="DELETE">DELETE</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="layui-col-md2">
                <div class="layui-form-item">
                    <label class="layui-form-label">状态</label>
                    <div class="layui-input-block">
                        <select name="status" lay-filter="status">
                            <option value="">全部</option>
                            <option value="200">成功</option>
                            <option value="403">拦截</option>
                            <option value="error">错误</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="layui-col-md3">
                <div class="layui-form-item">
                    <label class="layui-form-label">搜索</label>
                    <div class="layui-input-block">
                        <input type="text" name="keyword" placeholder="IP/URL/UA" class="layui-input">
                    </div>
                </div>
            </div>
            <div class="layui-col-md2">
                <div class="layui-form-item">
                    <div class="layui-input-block" style="margin-left: 0;">
                        <button class="layui-btn" lay-submit lay-filter="search">
                            <i class="layui-icon layui-icon-search"></i> 搜索
                        </button>
                        <button type="reset" class="layui-btn layui-btn-primary">重置</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<div class="log-table-container">
    <table class="layui-table" lay-data="{url:'/admin/api/logs', page:true, limit:20, id:'logTable'}" lay-filter="logTable">
        <thead>
            <tr>
                <th lay-data="{field:'timestamp', width:180, sort:true}">时间</th>
                <th lay-data="{field:'ip', width:140}">客户端IP</th>
                <th lay-data="{field:'method', width:80, templet:'#methodTpl'}">方法</th>
                <th lay-data="{field:'uri', minWidth:200}">请求URI</th>
                <th lay-data="{field:'status_code', width:80, sort:true, templet:'#statusTpl'}">状态</th>
                <th lay-data="{field:'response_time', width:100, sort:true}">响应时间</th>
                <th lay-data="{field:'user_agent', width:200}">User-Agent</th>
                <th lay-data="{field:'blocked', width:80, templet:'#blockedTpl'}">拦截</th>
                <th lay-data="{fixed:'right', width:120, toolbar:'#barTpl'}">操作</th>
            </tr>
        </thead>
    </table>
</div>

<!-- 表格工具模板 -->
<script type="text/html" id="barTpl">
    <a class="layui-btn layui-btn-xs" lay-event="detail">详情</a>
    <a class="layui-btn layui-btn-xs layui-btn-danger" lay-event="block">拉黑</a>
</script>

<!-- 状态模板 -->
<script type="text/html" id="statusTpl">
    {{# if(d.status_code >= 200 && d.status_code < 300) { }}
        <span class="status-badge status-success">{{d.status_code}}</span>
    {{# } else if(d.status_code >= 400 && d.status_code < 500) { }}
        <span class="status-badge status-warning">{{d.status_code}}</span>
    {{# } else if(d.status_code >= 500) { }}
        <span class="status-badge status-error">{{d.status_code}}</span>
    {{# } else { }}
        <span class="status-badge">{{d.status_code}}</span>
    {{# } }}
</script>

<!-- 方法模板 -->
<script type="text/html" id="methodTpl">
    <span class="method-badge method-{{d.method.toLowerCase()}}">{{d.method}}</span>
</script>

<!-- 拦截模板 -->
<script type="text/html" id="blockedTpl">
    {{# if(d.blocked) { }}
        <span class="status-badge status-error">是</span>
    {{# } else { }}
        <span class="status-badge status-success">否</span>
    {{# } }}
</script>

<script>
layui.use(['table', 'laydate', 'form', 'layer'], function(){
    var table = layui.table;
    var laydate = layui.laydate;
    var form = layui.form;
    var layer = layui.layer;
    
    // 日期范围选择器
    laydate.render({
        elem: '#timeRange',
        type: 'datetime',
        range: true
    });
    
    // 监听工具条
    table.on('tool(logTable)', function(obj){
        var data = obj.data;
        
        if(obj.event === 'detail'){
            showLogDetail(data);
        } else if(obj.event === 'block'){
            blockIp(data.ip);
        }
    });
    
    // 监听搜索
    form.on('submit(search)', function(data){
        table.reload('logTable', {
            where: data.field,
            page: {curr: 1}
        });
        return false;
    });
    
    // 加载统计数据
    loadStats();
});

// 加载统计数据
function loadStats() {
    ajaxGet('/admin/api/logs/stats', function(data) {
        document.getElementById('totalRequests').textContent = formatNumber(data.total || 0);
        document.getElementById('successRequests').textContent = formatNumber(data.success || 0);
        document.getElementById('blockedRequests').textContent = formatNumber(data.blocked || 0);
        document.getElementById('errorRequests').textContent = formatNumber(data.error || 0);
    });
}

// 显示日志详情
function showLogDetail(data) {
    var content = '<div style="padding: 15px;">' +
        '<p><strong>时间：</strong>' + data.timestamp + '</p>' +
        '<p><strong>客户端IP：</strong>' + data.ip + '</p>' +
        '<p><strong>请求方法：</strong>' + data.method + '</p>' +
        '<p><strong>请求URI：</strong>' + data.uri + '</p>' +
        '<p><strong>状态码：</strong>' + data.status_code + '</p>' +
        '<p><strong>响应时间：</strong>' + data.response_time + 'ms</p>' +
        '<p><strong>User-Agent：</strong>' + data.user_agent + '</p>' +
        '<p><strong>是否拦截：</strong>' + (data.blocked ? '是' : '否') + '</p>';
    
    if (data.blocked && data.rule) {
        content += '<p><strong>拦截规则：</strong>' + data.rule + '</p>';
    }
    
    if (data.message) {
        content += '<p><strong>详细信息：</strong>' + data.message + '</p>';
    }
    
    content += '</div>';
    
    layer.open({
        type: 1,
        title: '日志详情',
        area: ['600px', '500px'],
        content: content
    });
}

// 拉黑IP
function blockIp(ip) {
    layer.confirm('确定要拉黑IP: ' + ip + ' 吗？', {
        icon: 3,
        title: '提示'
    }, function(index){
        ajaxRequest('/admin/api/ip/block', {ip: ip}, function(data) {
            layer.msg('拉黑成功', {icon: 1});
            refreshLogs();
        });
        layer.close(index);
    });
}

// 刷新日志
function refreshLogs() {
    layui.use('table', function(){
        var table = layui.table;
        table.reload('logTable');
        loadStats();
    });
}

// 导出日志
function exportLogs() {
    layer.confirm('确定要导出当前筛选的日志吗？', {
        icon: 3,
        title: '提示'
    }, function(index){
        window.location.href = '/admin/api/logs/export';
        layer.close(index);
    });
}
</script>

<?php include __DIR__ . '/../layout/footer.php'; ?>

