<?php

namespace Tiangang\Waf\Monitoring;

use Tiangang\Waf\Config\ConfigManager;
use Predis\Client as RedisClient;
use PfinalClub\Asyncio\{create_task, gather, sleep};

/**
 * 告警管理器
 * 
 * 负责监控指标并触发告警
 */
class AlertManager
{
    private ConfigManager $configManager;
    private MetricsCollector $metricsCollector;
    private RedisClient $redis;
    private array $config;
    private array $alerts = [];
    private bool $isRunning = false;
    
    public function __construct()
    {
        $this->configManager = new ConfigManager();
        $this->config = $this->configManager->get('monitoring.alerts');
        $this->metricsCollector = new MetricsCollector();
        $this->redis = $this->getRedisClient();
        $this->loadAlertRules();
    }
    
    /**
     * 启动告警监控
     */
    public function start(): void
    {
        if ($this->isRunning) {
            return;
        }
        
        $this->isRunning = true;
        \PfinalClub\Asyncio\run($this->monitorAlerts());
    }
    
    /**
     * 监控告警
     */
    private function monitorAlerts(): \Generator
    {
        while ($this->isRunning) {
            $tasks = [];
            
            foreach ($this->alerts as $alert) {
                $tasks[] = create_task($this->checkAlert($alert));
            }
            
            // 并发检查所有告警
            yield gather(...$tasks);
            
            // 等待下次检查
            yield sleep($this->config['check_interval'] ?? 30);
        }
    }
    
    /**
     * 检查单个告警
     */
    private function checkAlert(array $alert): \Generator
    {
        try {
            $condition = $alert['condition'];
            $threshold = $alert['threshold'];
            $duration = $alert['duration'] ?? 0;
            
            $value = yield $this->evaluateCondition($condition);
            
            if ($this->shouldTriggerAlert($value, $threshold, $condition['operator'])) {
                if ($this->isAlertCooldown($alert['name'], $duration)) {
                    return;
                }
                
                yield $this->triggerAlert($alert, $value);
            } else {
                // 重置告警状态
                $this->resetAlert($alert['name']);
            }
            
        } catch (\Exception $e) {
            logger('error', 'Alert check failed', [
                'alert' => $alert['name'],
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * 评估告警条件
     */
    private function evaluateCondition(array $condition): \Generator
    {
        $metric = $condition['metric'];
        $labels = $condition['labels'] ?? [];
        $timeRange = $condition['time_range'] ?? 300; // 5分钟
        
        switch ($metric) {
            case 'request_rate':
                return $this->calculateRequestRate($timeRange);
                
            case 'error_rate':
                return $this->calculateErrorRate($timeRange);
                
            case 'response_time':
                return $this->getAverageResponseTime($timeRange);
                
            case 'memory_usage':
                return $this->getMemoryUsage();
                
            case 'cpu_usage':
                return $this->getCpuUsage();
                
            case 'block_rate':
                return $this->calculateBlockRate($timeRange);
                
            default:
                return $this->metricsCollector->getGauge($metric, $labels);
        }
    }
    
    /**
     * 计算请求率
     */
    private function calculateRequestRate(int $timeRange): float
    {
        $total = $this->metricsCollector->getCounter('requests_total');
        return $total / ($timeRange / 60); // 每分钟请求数
    }
    
    /**
     * 计算错误率
     */
    private function calculateErrorRate(int $timeRange): float
    {
        $total = $this->metricsCollector->getCounter('requests_total');
        $errors = $this->metricsCollector->getCounter('proxy_errors_total');
        
        if ($total === 0) {
            return 0.0;
        }
        
        return ($errors / $total) * 100;
    }
    
    /**
     * 获取平均响应时间
     */
    private function getAverageResponseTime(int $timeRange): float
    {
        $stats = $this->metricsCollector->getHistogramStats('request_duration');
        return $stats['avg'] ?? 0.0;
    }
    
    /**
     * 获取内存使用率
     */
    private function getMemoryUsage(): float
    {
        $usage = $this->metricsCollector->getGauge('memory_usage');
        $peak = $this->metricsCollector->getGauge('memory_peak');
        
        if ($peak === 0) {
            return 0.0;
        }
        
        return ($usage / $peak) * 100;
    }
    
    /**
     * 获取 CPU 使用率
     */
    private function getCpuUsage(): float
    {
        return $this->metricsCollector->getGauge('cpu_usage');
    }
    
    /**
     * 计算拦截率
     */
    private function calculateBlockRate(int $timeRange): float
    {
        $total = $this->metricsCollector->getCounter('requests_total');
        $blocked = $this->metricsCollector->getCounter('requests_blocked_total');
        
        if ($total === 0) {
            return 0.0;
        }
        
        return ($blocked / $total) * 100;
    }
    
    /**
     * 判断是否应该触发告警
     */
    private function shouldTriggerAlert(float $value, float $threshold, string $operator): bool
    {
        switch ($operator) {
            case 'gt':
                return $value > $threshold;
            case 'gte':
                return $value >= $threshold;
            case 'lt':
                return $value < $threshold;
            case 'lte':
                return $value <= $threshold;
            case 'eq':
                return $value === $threshold;
            case 'ne':
                return $value !== $threshold;
            default:
                return false;
        }
    }
    
    /**
     * 检查告警冷却期
     */
    private function isAlertCooldown(string $alertName, int $duration): bool
    {
        $key = "alert:cooldown:{$alertName}";
        $lastTriggered = $this->redis->get($key);
        
        if ($lastTriggered && (time() - $lastTriggered) < $duration) {
            return true;
        }
        
        return false;
    }
    
    /**
     * 触发告警
     */
    private function triggerAlert(array $alert, float $value): \Generator
    {
        $alertData = [
            'name' => $alert['name'],
            'severity' => $alert['severity'],
            'message' => $alert['message'],
            'value' => $value,
            'threshold' => $alert['threshold'],
            'timestamp' => time(),
            'status' => 'firing'
        ];
        
        // 记录告警
        $this->recordAlert($alertData);
        
        // 发送告警通知
        yield $this->sendAlertNotifications($alert, $alertData);
        
        // 设置冷却期
        $this->setAlertCooldown($alert['name']);
    }
    
    /**
     * 记录告警
     */
    private function recordAlert(array $alertData): void
    {
        $key = "alerts:active:{$alertData['name']}";
        $this->redis->setex($key, 3600, json_encode($alertData));
        
        // 记录到历史
        $historyKey = "alerts:history:{$alertData['name']}:" . date('Y-m-d-H');
        $this->redis->lpush($historyKey, json_encode($alertData));
        $this->redis->expire($historyKey, 86400 * 7); // 保留 7 天
    }
    
    /**
     * 发送告警通知
     */
    private function sendAlertNotifications(array $alert, array $alertData): \Generator
    {
        $channels = $alert['channels'] ?? ['log'];
        $tasks = [];
        
        foreach ($channels as $channel) {
            $tasks[] = create_task($this->sendToChannel($channel, $alertData));
        }
        
        yield gather(...$tasks);
    }
    
    /**
     * 发送到指定通道
     */
    private function sendToChannel(string $channel, array $alertData): \Generator
    {
        switch ($channel) {
            case 'log':
                yield $this->sendToLog($alertData);
                break;
            case 'email':
                yield $this->sendToEmail($alertData);
                break;
            case 'webhook':
                yield $this->sendToWebhook($alertData);
                break;
            case 'slack':
                yield $this->sendToSlack($alertData);
                break;
        }
    }
    
    /**
     * 发送到日志
     */
    private function sendToLog(array $alertData): \Generator
    {
        logger('alert', 'Alert triggered', $alertData);
    }
    
    /**
     * 发送邮件
     */
    private function sendToEmail(array $alertData): \Generator
    {
        // TODO: 实现邮件发送
        logger('info', 'Email alert sent', $alertData);
    }
    
    /**
     * 发送 Webhook
     */
    private function sendToWebhook(array $alertData): \Generator
    {
        // TODO: 实现 Webhook 发送
        logger('info', 'Webhook alert sent', $alertData);
    }
    
    /**
     * 发送到 Slack
     */
    private function sendToSlack(array $alertData): \Generator
    {
        // TODO: 实现 Slack 发送
        logger('info', 'Slack alert sent', $alertData);
    }
    
    /**
     * 设置告警冷却期
     */
    private function setAlertCooldown(string $alertName): void
    {
        $key = "alert:cooldown:{$alertName}";
        $this->redis->setex($key, 3600, time());
    }
    
    /**
     * 重置告警状态
     */
    private function resetAlert(string $alertName): void
    {
        $key = "alerts:active:{$alertName}";
        $this->redis->del($key);
    }
    
    /**
     * 加载告警规则
     */
    private function loadAlertRules(): void
    {
        $this->alerts = [
            [
                'name' => 'high_response_time',
                'severity' => 'warning',
                'message' => 'High response time detected',
                'condition' => [
                    'metric' => 'response_time',
                    'operator' => 'gt',
                    'time_range' => 300
                ],
                'threshold' => $this->config['high_response_time'] ?? 5000,
                'duration' => 300,
                'channels' => ['log', 'email']
            ],
            [
                'name' => 'high_error_rate',
                'severity' => 'critical',
                'message' => 'High error rate detected',
                'condition' => [
                    'metric' => 'error_rate',
                    'operator' => 'gt',
                    'time_range' => 300
                ],
                'threshold' => $this->config['high_error_rate'] ?? 10,
                'duration' => 60,
                'channels' => ['log', 'email', 'slack']
            ],
            [
                'name' => 'high_memory_usage',
                'severity' => 'warning',
                'message' => 'High memory usage detected',
                'condition' => [
                    'metric' => 'memory_usage',
                    'operator' => 'gt',
                    'time_range' => 60
                ],
                'threshold' => $this->config['high_memory_usage'] ?? 80,
                'duration' => 300,
                'channels' => ['log']
            ],
            [
                'name' => 'high_block_rate',
                'severity' => 'info',
                'message' => 'High block rate detected',
                'condition' => [
                    'metric' => 'block_rate',
                    'operator' => 'gt',
                    'time_range' => 300
                ],
                'threshold' => $this->config['high_block_rate'] ?? 50,
                'duration' => 600,
                'channels' => ['log']
            ]
        ];
    }
    
    /**
     * 获取活跃告警
     */
    public function getActiveAlerts(): array
    {
        $alerts = [];
        
        try {
            $keys = $this->redis->keys('alerts:active:*');
            foreach ($keys as $key) {
                $data = $this->redis->get($key);
                if ($data) {
                    $alerts[] = json_decode($data, true);
                }
            }
        } catch (\Exception $e) {
            logger('error', 'Failed to get active alerts', [
                'error' => $e->getMessage()
            ]);
        }
        
        return $alerts;
    }
    
    /**
     * 停止告警监控
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
            'host' => $config['host'],
            'port' => $config['port'],
            'password' => $config['password'],
            'database' => $config['database'],
        ]);
    }
}
