<?php

namespace Tiangang\Waf\Monitoring;

use Predis\Client as RedisClient;
use Tiangang\Waf\Config\ConfigManager;
use PfinalClub\Asyncio\{create_task, gather, wait_for, sleep};

/**
 * 指标收集器
 * 
 * 负责收集和存储 WAF 性能指标
 */
class MetricsCollector
{
    private RedisClient $redis;
    private ConfigManager $configManager;
    private array $config;
    private array $metrics = [];
    private bool $isRunning = false;
    
    public function __construct()
    {
        $this->configManager = new ConfigManager();
        $this->config = $this->configManager->get('monitoring');
        $this->redis = $this->getRedisClient();
    }
    
    /**
     * 异步记录请求指标
     */
    public function asyncRecordRequest(array $data): \Generator
    {
        // 模拟异步指标收集
        yield sleep(0.001);
        
        $this->incrementCounter('requests_total');
        $this->recordHistogram('request_duration', $data['duration'] ?? 0);
        $this->recordGauge('active_requests', 1);
        
        if ($data['blocked'] ?? false) {
            $this->incrementCounter('requests_blocked_total');
            $this->incrementCounter('requests_blocked_by_rule', [
                'rule' => $data['rule'] ?? 'unknown'
            ]);
        } else {
            $this->incrementCounter('requests_allowed_total');
        }
        
        // 异步记录响应时间分布
        yield create_task($this->asyncRecordResponseTime($data['duration'] ?? 0));
    }
    
    /**
     * 异步记录响应时间
     */
    private function asyncRecordResponseTime(float $duration): \Generator
    {
        // 模拟异步响应时间记录
        yield sleep(0.001);
        
        $this->recordResponseTime($duration);
    }
    
    /**
     * 记录请求指标（同步版本，保留兼容性）
     */
    public function recordRequest(array $data): void
    {
        $this->incrementCounter('requests_total');
        $this->recordHistogram('request_duration', $data['duration'] ?? 0);
        $this->recordGauge('active_requests', 1);
        
        if ($data['blocked'] ?? false) {
            $this->incrementCounter('requests_blocked_total');
            $this->incrementCounter('requests_blocked_by_rule', [
                'rule' => $data['rule'] ?? 'unknown'
            ]);
        } else {
            $this->incrementCounter('requests_allowed_total');
        }
        
        // 记录响应时间分布
        $this->recordResponseTime($data['duration'] ?? 0);
    }
    
    /**
     * 记录安全事件指标
     */
    public function recordSecurityEvent(string $event, array $data): void
    {
        $this->incrementCounter('security_events_total', [
            'event' => $event,
            'severity' => $data['severity'] ?? 'unknown'
        ]);
        
        $this->incrementCounter('security_events_by_rule', [
            'rule' => $data['rule'] ?? 'unknown',
            'event' => $event
        ]);
    }
    
    /**
     * 记录性能指标
     */
    public function recordPerformance(string $metric, float $value, array $tags = []): void
    {
        $this->recordGauge("performance_{$metric}", $value, $tags);
    }
    
    /**
     * 记录系统指标
     */
    public function recordSystemMetrics(): void
    {
        $this->recordGauge('memory_usage', memory_get_usage(true));
        $this->recordGauge('memory_peak', memory_get_peak_usage(true));
        $this->recordGauge('cpu_usage', $this->getCpuUsage());
        $this->recordGauge('load_average', sys_getloadavg()[0] ?? 0);
    }
    
    /**
     * 记录代理指标
     */
    public function recordProxyMetrics(array $data): void
    {
        $this->incrementCounter('proxy_requests_total');
        $this->recordHistogram('proxy_duration', $data['duration'] ?? 0);
        
        if ($data['success'] ?? false) {
            $this->incrementCounter('proxy_success_total');
        } else {
            $this->incrementCounter('proxy_errors_total', [
                'error_type' => $data['error_type'] ?? 'unknown'
            ]);
        }
        
        $this->recordGauge('proxy_backend_connections', $data['connections'] ?? 0, [
            'backend' => $data['backend'] ?? 'unknown'
        ]);
    }
    
    /**
     * 增加计数器
     */
    private function incrementCounter(string $name, array $labels = []): void
    {
        try {
            $key = $this->buildMetricKey($name, $labels);
            $this->redis->incr($key);
            $this->redis->expire($key, 3600); // 1小时过期
        } catch (\Exception $e) {
            // Redis 不可用时，忽略
        }
    }
    
    /**
     * 记录直方图
     */
    private function recordHistogram(string $name, float $value, array $labels = []): void
    {
        try {
            $key = $this->buildMetricKey($name, $labels);
            $this->redis->lpush($key, $value);
            $this->redis->ltrim($key, 0, 999); // 保留最近 1000 个值
            $this->redis->expire($key, 3600);
        } catch (\Exception $e) {
            // Redis 不可用时，忽略
        }
    }
    
    /**
     * 记录仪表盘
     */
    private function recordGauge(string $name, float $value, array $labels = []): void
    {
        try {
            $key = $this->buildMetricKey($name, $labels);
            $this->redis->set($key, $value);
            $this->redis->expire($key, 300); // 5分钟过期
        } catch (\Exception $e) {
            // Redis 不可用时，忽略
        }
    }
    
    /**
     * 记录响应时间
     */
    private function recordResponseTime(float $duration): void
    {
        $buckets = [0.001, 0.005, 0.01, 0.05, 0.1, 0.5, 1.0, 5.0];
        
        foreach ($buckets as $bucket) {
            if ($duration <= $bucket) {
                $this->incrementCounter('request_duration_bucket', [
                    'le' => $bucket
                ]);
            }
        }
        
        $this->incrementCounter('request_duration_bucket', [
            'le' => '+Inf'
        ]);
    }
    
    /**
     * 构建指标键
     */
    private function buildMetricKey(string $name, array $labels = []): string
    {
        $key = "metrics:{$name}";
        
        if (!empty($labels)) {
            $labelStr = http_build_query($labels, '', ',');
            $key .= ":{$labelStr}";
        }
        
        return $key;
    }
    
    /**
     * 获取指标数据
     */
    public function getMetrics(string $name, array $labels = [], int $timeRange = 3600): array
    {
        $key = $this->buildMetricKey($name, $labels);
        
        try {
            $data = $this->redis->get($key);
            return $data ? json_decode($data, true) : [];
        } catch (\Exception $e) {
            return [];
        }
    }
    
    /**
     * 获取计数器值
     */
    public function getCounter(string $name, array $labels = []): int
    {
        $key = $this->buildMetricKey($name, $labels);
        
        try {
            return (int) $this->redis->get($key);
        } catch (\Exception $e) {
            return 0;
        }
    }
    
    /**
     * 获取直方图统计
     */
    public function getHistogramStats(string $name, array $labels = []): array
    {
        $key = $this->buildMetricKey($name, $labels);
        
        try {
            $values = $this->redis->lrange($key, 0, -1);
            $values = array_map('floatval', $values);
            
            if (empty($values)) {
                return [
                    'count' => 0,
                    'sum' => 0,
                    'avg' => 0,
                    'min' => 0,
                    'max' => 0,
                    'p50' => 0,
                    'p95' => 0,
                    'p99' => 0
                ];
            }
            
            sort($values);
            $count = count($values);
            $sum = array_sum($values);
            
            return [
                'count' => $count,
                'sum' => $sum,
                'avg' => $sum / $count,
                'min' => min($values),
                'max' => max($values),
                'p50' => $this->percentile($values, 50),
                'p95' => $this->percentile($values, 95),
                'p99' => $this->percentile($values, 99)
            ];
        } catch (\Exception $e) {
            return [];
        }
    }
    
    /**
     * 计算百分位数
     */
    private function percentile(array $values, float $percentile): float
    {
        $index = ($percentile / 100) * (count($values) - 1);
        $lower = floor($index);
        $upper = ceil($index);
        
        if ($lower === $upper) {
            return $values[$lower];
        }
        
        $weight = $index - $lower;
        return $values[$lower] * (1 - $weight) + $values[$upper] * $weight;
    }
    
    /**
     * 获取系统指标
     */
    public function getSystemMetrics(): array
    {
        return [
            'memory_usage' => $this->getGauge('memory_usage'),
            'memory_peak' => $this->getGauge('memory_peak'),
            'cpu_usage' => $this->getGauge('cpu_usage'),
            'load_average' => $this->getGauge('load_average'),
            'active_requests' => $this->getGauge('active_requests')
        ];
    }
    
    /**
     * 获取仪表盘值
     */
    private function getGauge(string $name, array $labels = []): float
    {
        $key = $this->buildMetricKey($name, $labels);
        
        try {
            return (float) $this->redis->get($key);
        } catch (\Exception $e) {
            return 0.0;
        }
    }
    
    /**
     * 获取 CPU 使用率
     */
    private function getCpuUsage(): float
    {
        $stat1 = $this->getCpuStats();
        usleep(100000); // 100ms
        $stat2 = $this->getCpuStats();
        
        if (!$stat1 || !$stat2) {
            return 0.0;
        }
        
        $idle1 = $stat1['idle'];
        $idle2 = $stat2['idle'];
        $total1 = $stat1['total'];
        $total2 = $stat2['total'];
        
        $idleDiff = $idle2 - $idle1;
        $totalDiff = $total2 - $total1;
        
        if ($totalDiff === 0) {
            return 0.0;
        }
        
        return (1 - $idleDiff / $totalDiff) * 100;
    }
    
    /**
     * 获取 CPU 统计信息
     */
    private function getCpuStats(): ?array
    {
        $stat = file_get_contents('/proc/stat');
        if (!$stat) {
            return null;
        }
        
        $lines = explode("\n", $stat);
        $cpu = explode(' ', $lines[0]);
        
        $user = (int) $cpu[1];
        $nice = (int) $cpu[2];
        $system = (int) $cpu[3];
        $idle = (int) $cpu[4];
        $iowait = (int) $cpu[5];
        $irq = (int) $cpu[6];
        $softirq = (int) $cpu[7];
        
        return [
            'user' => $user,
            'nice' => $nice,
            'system' => $system,
            'idle' => $idle,
            'iowait' => $iowait,
            'irq' => $irq,
            'softirq' => $softirq,
            'total' => $user + $nice + $system + $idle + $iowait + $irq + $softirq
        ];
    }
    
    /**
     * 启动指标收集
     */
    public function start(): void
    {
        if ($this->isRunning) {
            return;
        }
        
        $this->isRunning = true;
        \PfinalClub\Asyncio\run($this->collectMetrics());
    }
    
    /**
     * 收集指标
     */
    private function collectMetrics(): \Generator
    {
        while ($this->isRunning) {
            // 收集系统指标
            $this->recordSystemMetrics();
            
            // 等待下次收集
            yield sleep($this->config['collection_interval'] ?? 60);
        }
    }
    
    /**
     * 停止指标收集
     */
    public function stop(): void
    {
        $this->isRunning = false;
    }
    
    /**
     * 获取 Redis 客户端
     */
    private function getRedisClient(): RedisClient
    {
        $config = $this->configManager->get('database.redis');
        return new RedisClient([
            'host' => $config['host'] ?? '127.0.0.1',
            'port' => $config['port'] ?? 6379,
            'password' => $config['password'] ?? '',
            'database' => $config['database'] ?? 0,
        ]);
    }
}
