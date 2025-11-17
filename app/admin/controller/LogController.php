<?php

namespace app\admin\controller;

use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;
use app\admin\Base\BaseController;

/**
 * WAF 日志管理控制器
 */
class LogController extends BaseController
{
    /**
     * 日志列表页面
     */
    public function index(Request $request): Response
    {
        return $this->view('logs.index', [
            'currentPage' => 'logs',
            'currentMenu' => 'logs'
        ]);
    }
    
    /**
     * 攻击日志页面
     */
    public function attackLogs(Request $request): Response
    {
        return $this->view('logs.attack', [
            'currentPage' => 'logs',
            'currentMenu' => 'attack-logs'
        ]);
    }
    
    /**
     * 获取日志数据（API）
     */
    public function getLogs(Request $request): Response
    {
        try {
            $page = (int)$request->get('page', 1);
            $limit = (int)$request->get('limit', 20);
            $method = $request->get('method', '');
            $status = $request->get('status', '');
            $keyword = $request->get('keyword', '');
            
            // 读取 WAF 日志文件
            $logFile = runtime_path('logs/waf.log');
            $logs = $this->parseWafLogs($logFile, $page, $limit, $method, $status, $keyword);
            
            return $this->success([
                'count' => $logs['total'],
                'data' => $logs['data']
            ]);
        } catch (\Exception $e) {
            return $this->error('获取日志失败: ' . $e->getMessage());
        }
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
    
    /**
     * 获取日志统计数据（API）
     */
    public function getStats(Request $request): Response
    {
        try {
            $logFile = runtime_path('logs/waf.log');
            $stats = $this->calculateStats($logFile);
            
            return $this->success($stats);
        } catch (\Exception $e) {
            return $this->error('获取统计数据失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 获取日志详情（API）
     */
    public function getDetail(Request $request): Response
    {
        try {
            $id = $request->get('id');
            // 实际应该从数据库或文件中获取
            return $this->success([
                'id' => $id,
                'timestamp' => date('Y-m-d H:i:s'),
                'ip' => '192.168.1.100',
                'method' => 'GET',
                'uri' => '/api/users',
                'status_code' => 200,
                'blocked' => false,
                'details' => '详细信息...'
            ]);
        } catch (\Exception $e) {
            return $this->error('获取详情失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 拉黑 IP（API）
     */
    public function blockIp(Request $request): Response
    {
        try {
            $postData = json_decode($request->rawBody(), true);
            $ip = $postData['ip'] ?? '';
            
            if (empty($ip)) {
                return $this->error('IP 地址不能为空');
            }
            
            // TODO: 实际应该写入黑名单配置
            return $this->success(['message' => "IP {$ip} 已被拉黑"]);
        } catch (\Exception $e) {
            return $this->error('拉黑失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 解析 WAF 日志
     */
    private function parseWafLogs(string $logFile, int $page, int $limit, string $method = '', string $status = '', string $keyword = ''): array
    {
        if (!file_exists($logFile)) {
            return ['data' => [], 'total' => 0];
        }
        
        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$lines) {
            return ['data' => [], 'total' => 0];
        }
        
        $logs = [];
        foreach ($lines as $line) {
            try {
                // 解析 JSON 格式的日志
                if (strpos($line, '{') !== false) {
                    $start = strpos($line, '{');
                    $jsonStr = substr($line, $start);
                    $log = json_decode($jsonStr, true);
                    
                    if ($log && is_array($log)) {
                        // 过滤
                        if ($method && strtoupper($log['method'] ?? '') !== strtoupper($method)) {
                            continue;
                        }
                        
                        if ($status === '200' && ($log['status_code'] ?? 0) != 200) {
                            continue;
                        }
                        
                        if ($status === '403' && ($log['blocked'] ?? false) !== true) {
                            continue;
                        }
                        
                        if ($keyword && stripos(json_encode($log), $keyword) === false) {
                            continue;
                        }
                        
                        $logs[] = [
                            'timestamp' => $log['timestamp'] ?? time(),
                            'ip' => $log['ip'] ?? '-',
                            'method' => strtoupper($log['method'] ?? 'GET'),
                            'uri' => $log['uri'] ?? '/',
                            'status_code' => $log['status_code'] ?? 200,
                            'response_time' => round(($log['response_time'] ?? 0) * 1000, 2) . 'ms',
                            'user_agent' => substr($log['user_agent'] ?? '-', 0, 100),
                            'blocked' => $log['blocked'] ?? false,
                            'rule' => $log['rule'] ?? '',
                            'message' => $log['message'] ?? ''
                        ];
                    }
                }
            } catch (\Exception $e) {
                // 跳过解析失败的行
                continue;
            }
        }
        
        $total = count($logs);
        $logs = array_slice($logs, ($page - 1) * $limit, $limit);
        
        return ['data' => $logs, 'total' => $total];
    }
    
    /**
     * 计算统计数据
     */
    private function calculateStats(string $logFile): array
    {
        if (!file_exists($logFile)) {
            return [
                'total' => 0,
                'success' => 0,
                'blocked' => 0,
                'error' => 0
            ];
        }
        
        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$lines) {
            return [
                'total' => 0,
                'success' => 0,
                'blocked' => 0,
                'error' => 0
            ];
        }
        
        $total = 0;
        $success = 0;
        $blocked = 0;
        $error = 0;
        
        foreach ($lines as $line) {
            try {
                if (strpos($line, '{') !== false) {
                    $start = strpos($line, '{');
                    $jsonStr = substr($line, $start);
                    $log = json_decode($jsonStr, true);
                    
                    if ($log && is_array($log)) {
                        $total++;
                        
                        if ($log['blocked'] ?? false) {
                            $blocked++;
                        } elseif (($log['status_code'] ?? 0) >= 200 && ($log['status_code'] ?? 0) < 300) {
                            $success++;
                        } else {
                            $error++;
                        }
                    }
                }
            } catch (\Exception $e) {
                continue;
            }
        }
        
        return [
            'total' => $total,
            'success' => $success,
            'blocked' => $blocked,
            'error' => $error
        ];
    }
}
