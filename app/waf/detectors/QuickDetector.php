<?php

namespace app\waf\detectors;

use app\waf\core\WafResult;
use app\waf\config\ConfigManager;
use Predis\Client as RedisClient;

/**
 * 快速检测器
 * 
 * 负责快速同步检测，如 IP 黑白名单、基础正则匹配等
 */
class QuickDetector
{
    private ConfigManager $configManager;
    private RedisClient $redis;
    private ?array $config;
    
    public function __construct()
    {
        $this->configManager = new ConfigManager();
        $this->config = $this->configManager->get('waf') ?? [];
        $this->redis = $this->getRedisClient();
    }
    
    /**
     * 异步快速检测
     */
    public function checkAsync(array $requestData): \Generator
    {
        // 并发执行多个检测
        $results = yield \PfinalClub\Asyncio\gather([
            $this->checkIpBlacklistAsync($requestData['ip']),
            $this->checkBasicRegexAsync($requestData),
            $this->checkRateLimitAsync($requestData)
        ]);

        // 合并结果
        $allResults = array_merge($results[0], $results[1], $results[2]);
        
        if (empty($allResults)) {
            return WafResult::allow();
        }

        // 返回第一个匹配的结果
        return $allResults[0];
    }

    /**
     * 快速检测（同步版本，保留兼容性）
     */
    public function check(array $requestData): WafResult
    {
        // IP 黑名单检查
        $ipResult = $this->checkIpBlacklist($requestData['ip']);
        if ($ipResult->isBlocked()) {
            return $ipResult;
        }
        
        // IP 白名单检查
        $ipResult = $this->checkIpWhitelist($requestData['ip']);
        if (!$ipResult->isBlocked()) {
            return WafResult::allow(); // 白名单直接放行
        }
        
        // 基础正则检查
        $regexResult = $this->checkBasicRegex($requestData);
        if ($regexResult->isBlocked()) {
            return $regexResult;
        }
        
        // 频率限制检查
        $rateResult = $this->checkRateLimit($requestData);
        if ($rateResult->isBlocked()) {
            return $rateResult;
        }
        
        return WafResult::allow();
    }
    
    /**
     * 检查 IP 黑名单
     */
    private function checkIpBlacklist(string $ip): WafResult
    {
        $blacklist = $this->getIpBlacklist();
        
        foreach ($blacklist as $blackIp) {
            if ($this->matchIp($ip, $blackIp)) {
                return WafResult::block(
                    'ip_blacklist',
                    "IP {$ip} is in blacklist",
                    403,
                    ['ip' => $ip, 'blacklist_entry' => $blackIp]
                );
            }
        }
        
        return WafResult::allow();
    }
    
    /**
     * 检查 IP 白名单
     */
    private function checkIpWhitelist(string $ip): WafResult
    {
        $whitelist = $this->getIpWhitelist();
        
        if (empty($whitelist)) {
            return WafResult::block('no_whitelist', 'No whitelist configured');
        }
        
        foreach ($whitelist as $whiteIp) {
            if ($this->matchIp($ip, $whiteIp)) {
                return WafResult::allow();
            }
        }
        
        return WafResult::block('ip_not_whitelisted', "IP {$ip} is not in whitelist");
    }
    
    /**
     * 基础正则检查
     */
    private function checkBasicRegex(array $requestData): WafResult
    {
        $patterns = $this->getBasicPatterns();
        $content = json_encode($requestData);
        
        foreach ($patterns as $pattern => $rule) {
            if (preg_match($pattern, $content)) {
                return WafResult::block(
                    $rule,
                    "Request matched pattern: {$pattern}",
                    403,
                    ['pattern' => $pattern, 'content' => $content]
                );
            }
        }
        
        return WafResult::allow();
    }
    
    /**
     * 频率限制检查
     */
    private function checkRateLimit(array $requestData): WafResult
    {
        $ip = $requestData['ip'];
        $key = "rate_limit:{$ip}";
        $limit = $this->config['rules']['rate_limit']['max_requests'] ?? 100;
        $window = $this->config['rules']['rate_limit']['window'] ?? 60;
        
        $current = $this->redis->incr($key);
        if ($current === 1) {
            $this->redis->expire($key, $window);
        }
        
        if ($current > $limit) {
            return WafResult::block(
                'rate_limit',
                "Rate limit exceeded: {$current}/{$limit} requests per {$window}s",
                429,
                ['current' => $current, 'limit' => $limit, 'window' => $window]
            );
        }
        
        return WafResult::allow();
    }
    
    /**
     * 获取 IP 黑名单
     */
    private function getIpBlacklist(): array
    {
        return $this->config['rules']['ip_blacklist'] ?? [];
    }
    
    /**
     * 获取 IP 白名单
     */
    private function getIpWhitelist(): array
    {
        return $this->config['rules']['ip_whitelist'] ?? [];
    }
    
    /**
     * 获取基础正则模式
     */
    private function getBasicPatterns(): array
    {
        return [
            '/(union\s+select)/i' => 'sql_injection',
            '/(<script[^>]*>)/i' => 'xss',
            '/(javascript\s*:)/i' => 'xss',
            '/(on\w+\s*=)/i' => 'xss',
        ];
    }
    
    /**
     * IP 匹配
     */
    private function matchIp(string $ip, string $pattern): bool
    {
        // 支持 CIDR 格式
        if (strpos($pattern, '/') !== false) {
            return $this->matchCidr($ip, $pattern);
        }
        
        // 支持通配符
        if (strpos($pattern, '*') !== false) {
            $pattern = str_replace('*', '.*', $pattern);
            return preg_match("/^{$pattern}$/", $ip);
        }
        
        // 精确匹配
        return $ip === $pattern;
    }
    
    /**
     * CIDR 匹配
     */
    private function matchCidr(string $ip, string $cidr): bool
    {
        list($subnet, $mask) = explode('/', $cidr);
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        $maskLong = -1 << (32 - $mask);
        
        return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
    }
    
    /**
     * 异步检查 IP 黑名单
     */
    private function checkIpBlacklistAsync(string $ip): \Generator
    {
        yield \PfinalClub\Asyncio\sleep(0.001); // 模拟异步查询
        
        $blacklist = $this->getIpBlacklist();
        
        foreach ($blacklist as $blackIp) {
            if ($this->matchIp($ip, $blackIp)) {
                return [WafResult::block(
                    'ip_blacklist',
                    "IP {$ip} is in blacklist",
                    403,
                    ['ip' => $ip, 'blacklist_entry' => $blackIp]
                )];
            }
        }
        
        return [];
    }

    /**
     * 异步基础正则检查
     */
    private function checkBasicRegexAsync(array $requestData): \Generator
    {
        yield \PfinalClub\Asyncio\sleep(0.001); // 模拟异步处理
        
        $patterns = $this->getBasicPatterns();
        $content = json_encode($requestData);
        
        foreach ($patterns as $pattern => $rule) {
            if (preg_match($pattern, $content)) {
                return [WafResult::block(
                    $rule,
                    "Request matched pattern: {$pattern}",
                    403,
                    ['pattern' => $pattern, 'content' => $content]
                )];
            }
        }
        
        return [];
    }

    /**
     * 异步频率限制检查
     */
    private function checkRateLimitAsync(array $requestData): \Generator
    {
        yield \PfinalClub\Asyncio\sleep(0.001); // 模拟异步Redis操作
        
        $ip = $requestData['ip'];
        $key = "rate_limit:{$ip}";
        $limit = $this->config['rules']['rate_limit']['max_requests'] ?? 100;
        $window = $this->config['rules']['rate_limit']['window'] ?? 60;
        
        $current = $this->redis->incr($key);
        if ($current === 1) {
            $this->redis->expire($key, $window);
        }
        
        if ($current > $limit) {
            return [WafResult::block(
                'rate_limit',
                "Rate limit exceeded: {$current}/{$limit} requests per {$window}s",
                429,
                ['current' => $current, 'limit' => $limit, 'window' => $window]
            )];
        }
        
        return [];
    }

    /**
     * 获取 Redis 客户端
     */
    private function getRedisClient(): RedisClient
    {
        $config = $this->configManager->get('database.redis') ?? [];
        return new RedisClient([
            'host' => $config['host'] ?? '127.0.0.1',
            'port' => $config['port'] ?? 6379,
            'password' => $config['password'] ?? '',
            'database' => $config['database'] ?? 0,
        ]);
    }
}
