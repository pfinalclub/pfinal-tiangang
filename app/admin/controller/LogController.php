<?php

namespace app\admin\controller;

use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;

/**
 * WAF 日志管理控制器
 */
class LogController
{
    /**
     * 日志列表
     */
    public function index(Request $request): Response
    {
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 20);
        $type = $request->get('type', 'all');
        $start_time = $request->get('start_time');
        $end_time = $request->get('end_time');

        // 模拟日志数据
        $logs = [];
        for ($i = 0; $i < $limit; $i++) {
            $logs[] = [
                'id' => ($page - 1) * $limit + $i + 1,
                'timestamp' => date('Y-m-d H:i:s', time() - rand(0, 86400)),
                'ip' => '192.168.1.' . rand(1, 254),
                'method' => ['GET', 'POST', 'PUT', 'DELETE'][rand(0, 3)],
                'path' => ['/api/users', '/admin/login', '/dashboard', '/api/data'][rand(0, 3)],
                'status' => rand(0, 1) ? 'blocked' : 'allowed',
                'rule' => ['SQL注入检测', 'XSS攻击检测', '频率限制', 'IP黑名单'][rand(0, 3)],
                'response_time' => rand(10, 500) . 'ms',
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'country' => ['中国', '美国', '日本', '韩国'][rand(0, 3)],
                'city' => ['北京', '上海', '深圳', '广州'][rand(0, 3)]
            ];
        }

        return json([
            'code' => 0,
            'msg' => 'success',
            'data' => [
                'list' => $logs,
                'total' => 1000,
                'page' => $page,
                'limit' => $limit
            ]
        ]);
    }

    /**
     * 安全事件统计
     */
    public function security(Request $request): Response
    {
        $period = $request->get('period', '24h');
        
        $data = [
            'total_events' => rand(100, 1000),
            'blocked_events' => rand(50, 200),
            'allowed_events' => rand(50, 800),
            'block_rate' => rand(5, 25),
            'top_threats' => [
                ['type' => 'SQL注入', 'count' => rand(20, 100)],
                ['type' => 'XSS攻击', 'count' => rand(10, 50)],
                ['type' => '恶意爬虫', 'count' => rand(5, 30)],
                ['type' => '暴力破解', 'count' => rand(3, 20)]
            ],
            'top_ips' => [
                ['ip' => '192.168.1.100', 'count' => rand(10, 50)],
                ['ip' => '10.0.0.50', 'count' => rand(5, 30)],
                ['ip' => '172.16.0.25', 'count' => rand(3, 20)]
            ],
            'timeline' => []
        ];

        // 生成时间线数据
        for ($i = 23; $i >= 0; $i--) {
            $data['timeline'][] = [
                'time' => date('H:i', time() - $i * 3600),
                'events' => rand(0, 50),
                'blocked' => rand(0, 20)
            ];
        }

        return json([
            'code' => 0,
            'msg' => 'success',
            'data' => $data
        ]);
    }

    /**
     * 导出日志
     */
    public function export(Request $request): Response
    {
        $format = $request->get('format', 'json');
        $type = $request->get('type', 'all');
        
        // 模拟导出数据
        $data = [
            'export_time' => date('Y-m-d H:i:s'),
            'total_records' => 1000,
            'format' => $format,
            'type' => $type
        ];

        if ($format === 'csv') {
            $csv = "时间,IP地址,方法,路径,状态,规则,响应时间\n";
            $csv .= date('Y-m-d H:i:s') . ",192.168.1.100,GET,/api/users,blocked,SQL注入检测,150ms\n";
            $csv .= date('Y-m-d H:i:s') . ",10.0.0.50,POST,/admin/login,allowed,-,80ms\n";
            
            return response($csv, 200, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="waf_logs.csv"'
            ]);
        }

        return json([
            'code' => 0,
            'msg' => 'success',
            'data' => $data
        ]);
    }

    /**
     * 清理日志
     */
    public function clean(Request $request): Response
    {
        $days = $request->get('days', 30);
        
        return json([
            'code' => 0,
            'msg' => "成功清理{$days}天前的日志",
            'data' => [
                'cleaned_count' => rand(100, 1000),
                'remaining_count' => rand(500, 2000)
            ]
        ]);
    }
}
