<?php

namespace app\admin\controller;

use support\Request;
use support\Response;
use Tiangang\Waf\Web\Controllers\DashboardController;

/**
 * WAF 管理控制器
 */
class WafController
{
    private DashboardController $dashboardController;

    public function __construct()
    {
        $this->dashboardController = new DashboardController();
    }

    /**
     * WAF 仪表板
     */
    public function dashboard(Request $request): Response
    {
        $data = $this->dashboardController->getDashboardData();
        
        return json([
            'code' => 0,
            'msg' => 'success',
            'data' => $data
        ]);
    }

    /**
     * 性能报告
     */
    public function performance(Request $request): Response
    {
        $period = $request->get('period', '1h');
        $data = $this->dashboardController->getPerformanceReport($period);
        
        return json([
            'code' => 0,
            'msg' => 'success',
            'data' => $data
        ]);
    }

    /**
     * 安全报告
     */
    public function security(Request $request): Response
    {
        $period = $request->get('period', '1d');
        $data = $this->dashboardController->getSecurityReport($period);
        
        return json([
            'code' => 0,
            'msg' => 'success',
            'data' => $data
        ]);
    }

    /**
     * 导出数据
     */
    public function export(Request $request): Response
    {
        $type = $request->get('type', 'dashboard');
        $format = $request->get('format', 'json');
        
        $data = $this->dashboardController->exportData($type, $format);
        
        return response($data, 200, [
            'Content-Type' => $format === 'json' ? 'application/json' : 'text/plain',
            'Content-Disposition' => 'attachment; filename="waf_export.' . $format . '"'
        ]);
    }

    /**
     * 系统状态
     */
    public function status(Request $request): Response
    {
        $data = [
            'status' => 'online',
            'uptime' => '7天 12小时',
            'memory_usage' => rand(60, 85),
            'cpu_usage' => rand(20, 60),
            'timestamp' => time()
        ];
        
        return json([
            'code' => 0,
            'msg' => 'success',
            'data' => $data
        ]);
    }

    /**
     * 健康检查
     */
    public function health(Request $request): Response
    {
        return json([
            'status' => 'ok',
            'timestamp' => time(),
            'service' => 'Tiangang WAF',
            'version' => '1.0.0'
        ]);
    }
}
