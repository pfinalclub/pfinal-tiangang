<?php

namespace app\admin\controller;

use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;
use app\admin\Base\BaseController;
use app\admin\service\DashboardService;

/**
 * 仪表板控制器
 * 
 * 负责处理管理界面的仪表板相关请求
 * 遵循 MVC 架构：Controller -> Service -> Model
 */
class DashboardController extends BaseController
{
    private DashboardService $dashboardService;
    
    public function __construct()
    {
        $this->dashboardService = new DashboardService();
    }

    /**
     * 仪表板首页（视图）
     */
    public function generateDashboardHtml(Request $request): Response
    {
        return $this->view('dashboard.index');
    }

    /**
     * 获取仪表板数据（API 接口）
     */
    public function getData(Request $request): Response
    {
        try {
            $data = $this->dashboardService->getDashboardData();
            return $this->success($data);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 获取性能报告（API 接口）
     */
    public function getPerformance(Request $request): Response
    {
        try {
            $period = $request->get('period', '1h');
            $data = $this->dashboardService->getPerformanceReport($period);
            return $this->success($data);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 获取安全报告（API 接口）
     */
    public function getSecurity(Request $request): Response
    {
        try {
            $period = $request->get('period', '1d');
            $data = $this->dashboardService->getSecurityReport($period);
            return $this->success($data);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 导出数据（API 接口）
     */
    public function export(Request $request): Response
    {
        try {
            $type = $request->get('type', 'dashboard');
            $format = $request->get('format', 'json');
            $data = $this->dashboardService->exportData($type, $format);
            
            $contentType = match($format) {
                'json' => 'application/json',
                'csv' => 'text/csv',
                'xml' => 'application/xml',
                default => 'text/plain'
            };
            
            return new Response(200, [
                'Content-Type' => $contentType . '; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="waf_export.' . $format . '"'
            ], $data);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 健康检查（API 接口）
     */
    public function health(Request $request): Response
    {
        return $this->success([
            'status' => 'ok',
            'timestamp' => time(),
            'service' => 'Tiangang WAF',
            'version' => '1.0.0'
        ]);
    }
}
