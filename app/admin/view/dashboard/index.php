<?php 
$pageTitle = '数据概览 - 天罡 WAF 管理控制台';
$currentPage = 'dashboard';
$currentMenu = 'dashboard';
$showBreadcrumb = true;
$breadcrumbs = [
    ['title' => '数据概览']
];

include __DIR__ . '/../layout/header.php';
?>

<style>
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 20px;
        margin-bottom: 20px;
    }
    
    .stat-card {
        background: #FFFFFF;
        border-radius: 8px;
        padding: 24px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        position: relative;
        overflow: hidden;
    }
    
    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }
    
    .stat-card.success::before {
        background: linear-gradient(135deg, #52c41a 0%, #389e0d 100%);
    }
    
    .stat-card.warning::before {
        background: linear-gradient(135deg, #faad14 0%, #d46b08 100%);
    }
    
    .stat-card.danger::before {
        background: linear-gradient(135deg, #ff4d4f 0%, #cf1322 100%);
    }
    
    .stat-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 16px;
    }
    
    .stat-title {
        font-size: 14px;
        color: #8c8c8c;
        font-weight: 500;
    }
    
    .stat-icon {
        width: 40px;
        height: 40px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
        color: #667eea;
    }
    
    .stat-card.success .stat-icon {
        background: rgba(82, 196, 26, 0.1);
        color: #52c41a;
    }
    
    .stat-card.warning .stat-icon {
        background: rgba(250, 173, 20, 0.1);
        color: #faad14;
    }
    
    .stat-card.danger .stat-icon {
        background: rgba(255, 77, 79, 0.1);
        color: #ff4d4f;
    }
    
    .stat-value {
        font-size: 32px;
        font-weight: 600;
        color: #364A63;
        margin-bottom: 8px;
    }
    
    .stat-trend {
        font-size: 13px;
        display: flex;
        align-items: center;
        gap: 4px;
    }
    
    .stat-trend.up {
        color: #52c41a;
    }
    
    .stat-trend.down {
        color: #ff4d4f;
    }
    
    .chart-card {
        background: #FFFFFF;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        margin-bottom: 20px;
    }
    
    .chart-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 1px solid #f0f0f0;
    }
    
    .chart-title {
        font-size: 16px;
        font-weight: 600;
        color: #364A63;
    }
    
    .chart-container {
        height: 300px;
    }
    
    .quick-actions {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-bottom: 20px;
    }
    
    .action-btn {
        background: #FFFFFF;
        border: 1px solid #d9d9d9;
        border-radius: 8px;
        padding: 16px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s;
        text-decoration: none;
        color: #364A63;
        display: block;
    }
    
    .action-btn:hover {
        border-color: #667eea;
        box-shadow: 0 2px 8px rgba(102, 126, 234, 0.2);
        transform: translateY(-2px);
    }
    
    .action-btn i {
        font-size: 24px;
        color: #667eea;
        margin-bottom: 8px;
    }
    
    .action-btn-title {
        font-size: 14px;
        font-weight: 500;
    }
</style>

<div class="content-card">
    <div class="content-card-header">
        <h2 class="content-card-title">
            <i class="layui-icon layui-icon-chart"></i> 实时统计
        </h2>
        <div>
            <button class="layui-btn layui-btn-sm layui-btn-primary" onclick="refreshDashboard()">
                <i class="layui-icon layui-icon-refresh"></i> 刷新
            </button>
        </div>
    </div>
    
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-header">
                <div>
                    <div class="stat-title">总请求数</div>
                    <div class="stat-value" id="totalRequests">-</div>
                    <div class="stat-trend up">
                        <i class="layui-icon layui-icon-triangle-d" style="transform: rotate(180deg);"></i>
                        <span id="totalTrend">+12.5%</span>
                    </div>
                </div>
                <div class="stat-icon">
                    <i class="layui-icon layui-icon-fire"></i>
                </div>
            </div>
        </div>
        
        <div class="stat-card success">
            <div class="stat-header">
                <div>
                    <div class="stat-title">正常请求</div>
                    <div class="stat-value" id="normalRequests">-</div>
                    <div class="stat-trend up">
                        <i class="layui-icon layui-icon-triangle-d" style="transform: rotate(180deg);"></i>
                        <span id="normalTrend">+8.2%</span>
                    </div>
                </div>
                <div class="stat-icon">
                    <i class="layui-icon layui-icon-ok-circle"></i>
                </div>
            </div>
        </div>
        
        <div class="stat-card warning">
            <div class="stat-header">
                <div>
                    <div class="stat-title">拦截次数</div>
                    <div class="stat-value" id="blockedRequests">-</div>
                    <div class="stat-trend down">
                        <i class="layui-icon layui-icon-triangle-d"></i>
                        <span id="blockedTrend">-3.1%</span>
                    </div>
                </div>
                <div class="stat-icon">
                    <i class="layui-icon layui-icon-close-fill"></i>
                </div>
            </div>
        </div>
        
        <div class="stat-card danger">
            <div class="stat-header">
                <div>
                    <div class="stat-title">攻击检测</div>
                    <div class="stat-value" id="attackCount">-</div>
                    <div class="stat-trend down">
                        <i class="layui-icon layui-icon-triangle-d"></i>
                        <span id="attackTrend">-15.8%</span>
                    </div>
                </div>
                <div class="stat-icon">
                    <i class="layui-icon layui-icon-flag"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="quick-actions">
    <a href="/admin/config" class="action-btn">
        <i class="layui-icon layui-icon-set"></i>
        <div class="action-btn-title">配置管理</div>
    </a>
    <a href="/admin/logs" class="action-btn">
        <i class="layui-icon layui-icon-file"></i>
        <div class="action-btn-title">访问日志</div>
    </a>
    <a href="/admin/rules" class="action-btn">
        <i class="layui-icon layui-icon-list"></i>
        <div class="action-btn-title">规则管理</div>
    </a>
    <a href="/admin/plugins" class="action-btn">
        <i class="layui-icon layui-icon-component"></i>
        <div class="action-btn-title">插件管理</div>
    </a>
</div>

<div class="chart-card">
    <div class="chart-header">
        <h3 class="chart-title">
            <i class="layui-icon layui-icon-chart-screen"></i> 请求趋势（24小时）
        </h3>
        <div>
            <select id="trendPeriod" class="layui-input" style="width: 120px; display: inline-block;">
                <option value="1h">最近1小时</option>
                <option value="6h">最近6小时</option>
                <option value="24h" selected>最近24小时</option>
                <option value="7d">最近7天</option>
            </select>
        </div>
    </div>
    <div class="chart-container" id="trendChart"></div>
</div>

<div class="layui-row layui-col-space15">
    <div class="layui-col-md6">
        <div class="chart-card">
            <div class="chart-header">
                <h3 class="chart-title">
                    <i class="layui-icon layui-icon-circle-dot"></i> 攻击类型分布
                </h3>
            </div>
            <div class="chart-container" id="attackTypeChart"></div>
        </div>
    </div>
    
    <div class="layui-col-md6">
        <div class="chart-card">
            <div class="chart-header">
                <h3 class="chart-title">
                    <i class="layui-icon layui-icon-location"></i> TOP 10 来源地区
                </h3>
            </div>
            <div class="chart-container" id="regionChart"></div>
        </div>
    </div>
</div>

<script src="//cdn.jsdelivr.net/npm/echarts@5.4.3/dist/echarts.min.js"></script>
<script>
// 初始化图表
let trendChart, attackTypeChart, regionChart;

// 加载数据
function loadDashboardData() {
    ajaxGet('/admin/api/dashboard', function(data) {
        // 更新统计卡片
        document.getElementById('totalRequests').textContent = formatNumber(data.total_requests || 0);
        document.getElementById('normalRequests').textContent = formatNumber(data.normal_requests || 0);
        document.getElementById('blockedRequests').textContent = formatNumber(data.blocked_requests || 0);
        document.getElementById('attackCount').textContent = formatNumber(data.attack_count || 0);
        
        // 更新趋势
        updateTrend('totalTrend', data.total_trend || 0);
        updateTrend('normalTrend', data.normal_trend || 0);
        updateTrend('blockedTrend', data.blocked_trend || 0);
        updateTrend('attackTrend', data.attack_trend || 0);
        
        // 绘制图表
        drawTrendChart(data.trend || []);
        drawAttackTypeChart(data.attack_types || []);
        drawRegionChart(data.top_regions || []);
    });
}

function updateTrend(elementId, value) {
    const element = document.getElementById(elementId);
    if (!element) return;
    
    const isPositive = value >= 0;
    element.textContent = (isPositive ? '+' : '') + value + '%';
    element.parentElement.className = 'stat-trend ' + (isPositive ? 'up' : 'down');
}

// 绘制趋势图
function drawTrendChart(data) {
    if (!trendChart) {
        trendChart = echarts.init(document.getElementById('trendChart'));
    }
    
    const option = {
        tooltip: {
            trigger: 'axis'
        },
        legend: {
            data: ['总请求', '正常请求', '拦截请求']
        },
        grid: {
            left: '3%',
            right: '4%',
            bottom: '3%',
            containLabel: true
        },
        xAxis: {
            type: 'category',
            boundaryGap: false,
            data: data.map(item => item.time || '')
        },
        yAxis: {
            type: 'value'
        },
        series: [
            {
                name: '总请求',
                type: 'line',
                data: data.map(item => item.total || 0),
                smooth: true,
                areaStyle: {
                    color: 'rgba(102, 126, 234, 0.1)'
                },
                itemStyle: {
                    color: '#667eea'
                }
            },
            {
                name: '正常请求',
                type: 'line',
                data: data.map(item => item.normal || 0),
                smooth: true,
                areaStyle: {
                    color: 'rgba(82, 196, 26, 0.1)'
                },
                itemStyle: {
                    color: '#52c41a'
                }
            },
            {
                name: '拦截请求',
                type: 'line',
                data: data.map(item => item.blocked || 0),
                smooth: true,
                areaStyle: {
                    color: 'rgba(255, 77, 79, 0.1)'
                },
                itemStyle: {
                    color: '#ff4d4f'
                }
            }
        ]
    };
    
    trendChart.setOption(option);
}

// 绘制攻击类型图
function drawAttackTypeChart(data) {
    if (!attackTypeChart) {
        attackTypeChart = echarts.init(document.getElementById('attackTypeChart'));
    }
    
    const option = {
        tooltip: {
            trigger: 'item'
        },
        legend: {
            orient: 'vertical',
            left: 'left'
        },
        series: [
            {
                type: 'pie',
                radius: '50%',
                data: data.map(item => ({
                    value: item.count || 0,
                    name: item.type || '未知'
                })),
                emphasis: {
                    itemStyle: {
                        shadowBlur: 10,
                        shadowOffsetX: 0,
                        shadowColor: 'rgba(0, 0, 0, 0.5)'
                    }
                }
            }
        ]
    };
    
    attackTypeChart.setOption(option);
}

// 绘制地区图
function drawRegionChart(data) {
    if (!regionChart) {
        regionChart = echarts.init(document.getElementById('regionChart'));
    }
    
    const option = {
        tooltip: {
            trigger: 'axis',
            axisPointer: {
                type: 'shadow'
            }
        },
        grid: {
            left: '3%',
            right: '4%',
            bottom: '3%',
            containLabel: true
        },
        xAxis: {
            type: 'value'
        },
        yAxis: {
            type: 'category',
            data: data.map(item => item.region || '未知')
        },
        series: [
            {
                type: 'bar',
                data: data.map(item => item.count || 0),
                itemStyle: {
                    color: new echarts.graphic.LinearGradient(0, 0, 1, 0, [
                        { offset: 0, color: '#667eea' },
                        { offset: 1, color: '#764ba2' }
                    ])
                }
            }
        ]
    };
    
    regionChart.setOption(option);
}

// 刷新仪表板
function refreshDashboard() {
    loadDashboardData();
    layui.use('layer', function(){
        var layer = layui.layer;
        layer.msg('数据已刷新', {icon: 1});
    });
}

// 响应式处理
window.addEventListener('resize', function() {
    trendChart && trendChart.resize();
    attackTypeChart && attackTypeChart.resize();
    regionChart && regionChart.resize();
});

// 页面加载完成后初始化
document.addEventListener('DOMContentLoaded', function() {
    loadDashboardData();
    
    // 每30秒自动刷新
    setInterval(loadDashboardData, 30000);
});
</script>

<?php include __DIR__ . '/../layout/footer.php'; ?>
