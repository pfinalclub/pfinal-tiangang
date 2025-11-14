<?php

namespace app\waf\plugins;

use app\waf\plugins\WafPluginInterface;
use Predis\Client as RedisClient;

/**
 * 频率限制规则插件
 */
class RateLimitRule implements WafPluginInterface
{
    private RedisClient $redis;
    private array $config;
    
    public function __construct()
    {
        $this->config = [
            'enabled' => true,
            'priority' => 90,
            'limits' => [
                'ip' => [
                    'max_requests' => 100,
                    'window' => 60,
                    'burst' => 20
                ],
                'user' => [
                    'max_requests' => 200,
                    'window' => 60,
                    'burst' => 50
                ],
                'path' => [
                    'max_requests' => 50,
                    'window' => 60,
                    'burst' => 10
                ]
            ],
            'strategy' => 'sliding_window',
            'whitelist_ips' => ['127.0.0.1', '::1']
        ];
        
        $this->redis = $this->getRedisClient();
    }
    
    public function getName(): string
    {
        return 'rate_limit';
    }
    
    public function getVersion(): string
    {
        return '1.0.0';
    }
    
    public function getPriority(): int
    {
        return $this->config['priority'];
    }
    
    public function isEnabled(): bool
    {
        return $this->config['enabled'];
    }
    
    public function detect(array $requestData): array
    {
        $ip = $requestData['ip'] ?? '';
        $uri = $requestData['uri'] ?? '';
        $userAgent = $requestData['user_agent'] ?? '';
        
        // 检查白名单
        if (in_array($ip, $this->config['whitelist_ips'])) {
            return ['matched' => false];
        }
        
        // 检查各种限流策略
        $results = [];
        
        // IP 限流
        $ipResult = $this->checkRateLimit('ip', $ip, $this->config['limits']['ip']);
        if ($ipResult['exceeded']) {
            $results[] = $ipResult;
        }
        
        // 路径限流
        $pathResult = $this->checkRateLimit('path', $ip . ':' . $uri, $this->config['limits']['path']);
        if ($pathResult['exceeded']) {
            $results[] = $pathResult;
        }
        
        // 用户限流（基于 User-Agent）
        if (!empty($userAgent)) {
            $userResult = $this->checkRateLimit('user', $ip . ':' . md5($userAgent), $this->config['limits']['user']);
            if ($userResult['exceeded']) {
                $results[] = $userResult;
            }
        }
        
        if (empty($results)) {
            return ['matched' => false];
        }
        
        // 返回最严重的限流结果
        usort($results, function ($a, $b) {
            return $b['severity'] <=> $a['severity'];
        });
        
        return $results[0];
    }
    
    public function getDescription(): string
    {
        return '请求频率限制规则';
    }
    
    public function getConfig(): array
    {
        return $this->config;
    }
    
    public function supportsQuickDetection(): bool
    {
        // 频率限制适合快速检测（同步操作）
        return true;
    }
    
    public function requiresLicense(): bool
    {
        // 高级频率限制功能需要付费许可证
        return true;
    }
    
    /**
     * 检查限流
     */
    private function checkRateLimit(string $type, string $key, array $limit): array
    {
        $redisKey = "rate_limit:{$type}:{$key}";
        $maxRequests = $limit['max_requests'];
        $window = $limit['window'];
        $burst = $limit['burst'] ?? 0;
        
        // 滑动窗口算法
        $current = $this->redis->incr($redisKey);
        if ($current === 1) {
            $this->redis->expire($redisKey, $window);
        }
        
        $exceeded = $current > $maxRequests;
        $severity = $this->calculateSeverity($current, $maxRequests, $burst);
        
        return [
            'matched' => $exceeded,
            'exceeded' => $exceeded,
            'rule' => $this->getName(),
            'severity' => $severity,
            'description' => "{$type} 频率限制超出",
            'details' => [
                'type' => $type,
                'key' => $key,
                'current' => $current,
                'limit' => $maxRequests,
                'window' => $window,
                'burst' => $burst,
                'exceeded_by' => max(0, $current - $maxRequests)
            ]
        ];
    }
    
    /**
     * 计算严重程度
     */
    private function calculateSeverity(int $current, int $limit, int $burst): int
    {
        $ratio = $current / $limit;
        
        if ($ratio >= 2.0) {
            return 4; // critical
        } elseif ($ratio >= 1.5) {
            return 3; // high
        } elseif ($ratio >= 1.2) {
            return 2; // medium
        } else {
            return 1; // low
        }
    }
    
    /**
     * 获取 Redis 客户端
     */
    private function getRedisClient(): RedisClient
    {
        return new RedisClient([
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'port' => env('REDIS_PORT', 6379),
            'password' => env('REDIS_PASSWORD', ''),
            'database' => env('REDIS_DATABASE', 0),
        ]);
    }
}
