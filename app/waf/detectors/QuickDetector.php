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
    private ?RedisClient $redis;
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
     * 
     * @param array $requestData 请求数据
     * @param array $enabledRules 启用的规则列表（可选，如果不提供则使用全局配置）
     */
    public function check(array $requestData, ?array $enabledRules = null): WafResult
    {
        // IP 黑名单检查（总是启用）
        $ipResult = $this->checkIpBlacklist($requestData['ip']);
        if ($ipResult->isBlocked()) {
            return $ipResult;
        }
        
        // IP 白名单检查（总是启用）
        $whitelist = $this->getIpWhitelist();
        if (!empty($whitelist)) {
            $ipResult = $this->checkIpWhitelist($requestData['ip']);
            if (!$ipResult->isBlocked()) {
                // IP 在白名单中，直接放行
                return WafResult::allow();
            }
            // IP 不在白名单中，继续检测（会被后续规则拦截）
        }
        
        // 基础正则检查（根据启用的规则）
        $regexResult = $this->checkBasicRegex($requestData, $enabledRules);
        if ($regexResult->isBlocked()) {
            return $regexResult;
        }
        
        // 频率限制检查（如果启用了 rate_limit 规则）
        if ($this->isRuleEnabled('rate_limit', $enabledRules)) {
            $rateResult = $this->checkRateLimit($requestData);
            if ($rateResult->isBlocked()) {
                return $rateResult;
            }
        }
        
        return WafResult::allow();
    }
    
    /**
     * 检查规则是否启用
     */
    private function isRuleEnabled(string $ruleName, ?array $enabledRules = null): bool
    {
        if ($enabledRules === null) {
            // 使用全局配置
            $globalEnabled = $this->config['rules']['enabled'] ?? [];
            return in_array($ruleName, $globalEnabled);
        }
        
        // 使用传入的启用规则列表
        return in_array($ruleName, $enabledRules);
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
     * 
     * 修复 P0: 白名单逻辑错误
     * - 如果没有配置白名单，直接放行（白名单是可选的）
     * - 如果配置了白名单，只有 IP 在白名单中才放行
     */
    private function checkIpWhitelist(string $ip): WafResult
    {
        $whitelist = $this->getIpWhitelist();
        
        // 如果没有配置白名单，直接放行（白名单是可选的）
        if (empty($whitelist)) {
            return WafResult::allow();
        }
        
        // 如果配置了白名单，检查 IP 是否在其中
        foreach ($whitelist as $whiteIp) {
            if ($this->matchIp($ip, $whiteIp)) {
                return WafResult::allow();
            }
        }
        
        // IP 不在白名单中，拦截
        return WafResult::block('ip_not_whitelisted', "IP {$ip} is not in whitelist");
    }
    
    /**
     * 基础正则检查
     * 
     * @param array $requestData 请求数据
     * @param array|null $enabledRules 启用的规则列表（可选）
     */
    private function checkBasicRegex(array $requestData, ?array $enabledRules = null): WafResult
    {
        $patterns = $this->getBasicPatterns();
        
        // 同时检测原始内容和 URL 解码后的内容
        $content = json_encode($requestData);
        $decodedContent = $this->decodeUrlEncodedContent($content);
        
        foreach ($patterns as $pattern => $rule) {
            // 检查规则是否启用
            if (!$this->isRuleEnabled($rule, $enabledRules)) {
                continue; // 跳过未启用的规则
            }
            
            // 检测原始内容
            if (preg_match($pattern, $content)) {
                return WafResult::block(
                    $rule,
                    "Request matched pattern: {$pattern}",
                    403,
                    ['pattern' => $pattern, 'content' => $content]
                );
            }
            
            // 检测 URL 解码后的内容
            if (preg_match($pattern, $decodedContent)) {
                return WafResult::block(
                    $rule,
                    "Request matched pattern: {$pattern} (URL decoded)",
                    403,
                    ['pattern' => $pattern, 'content' => $decodedContent]
                );
            }
        }
        
        return WafResult::allow();
    }
    
    /**
     * 解码 URL 编码的内容
     */
    private function decodeUrlEncodedContent(string $content): string
    {
        // 解码常见的 URL 编码字符
        return urldecode($content);
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
        
        // 使用 Redis 或文件后备方案
        if ($this->redis) {
            try {
                $current = $this->redis->incr($key);
                if ($current === 1) {
                    $this->redis->expire($key, $window);
                }
            } catch (\Exception $e) {
                // Redis 操作失败，使用文件后备方案
                $current = $this->incrementFileCounter($key, $window);
            }
        } else {
            // 使用文件后备方案
            $current = $this->incrementFileCounter($key, $window);
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
            // SQL 注入模式
            '/(union\s+select)/i' => 'sql_injection',
            '/(or\s+1\s*=\s*1)/i' => 'sql_injection',  // OR 1=1 逻辑攻击
            '/(and\s+1\s*=\s*1)/i' => 'sql_injection',  // AND 1=1 逻辑攻击
            '/(select\s+.*\s+from)/i' => 'sql_injection',  // SELECT 查询攻击
            '/(insert\s+into)/i' => 'sql_injection',  // INSERT 注入攻击
            '/(delete\s+from)/i' => 'sql_injection',  // DELETE 注入攻击
            '/(drop\s+table)/i' => 'sql_injection',  // DROP TABLE 攻击
            '/(sleep\s*\()/i' => 'sql_injection',  // SLEEP 时间盲注
            '/(benchmark\s*\()/i' => 'sql_injection',  // BENCHMARK 时间盲注
            // XSS 模式
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
        
        // 同时检测原始内容和 URL 解码后的内容
        $content = json_encode($requestData);
        $decodedContent = $this->decodeUrlEncodedContent($content);
        
        foreach ($patterns as $pattern => $rule) {
            // 检测原始内容
            if (preg_match($pattern, $content)) {
                return [WafResult::block(
                    $rule,
                    "Request matched pattern: {$pattern}",
                    403,
                    ['pattern' => $pattern, 'content' => $content]
                )];
            }
            
            // 检测 URL 解码后的内容
            if (preg_match($pattern, $decodedContent)) {
                return [WafResult::block(
                    $rule,
                    "Request matched pattern: {$pattern} (URL decoded)",
                    403,
                    ['pattern' => $pattern, 'content' => $decodedContent]
                )];
            }
        }
        
        return [];
    }

    /**
     * 异步频率限制检查（修复：支持离线模式）
     */
    private function checkRateLimitAsync(array $requestData): \Generator
    {
        yield \PfinalClub\Asyncio\sleep(0.001); // 模拟异步操作
        
        $ip = $requestData['ip'];
        $key = "rate_limit:{$ip}";
        $limit = $this->config['rules']['rate_limit']['max_requests'] ?? 100;
        $window = $this->config['rules']['rate_limit']['window'] ?? 60;
        
        // 使用 Redis 或文件后备方案
        if ($this->redis) {
            try {
                $current = $this->redis->incr($key);
                if ($current === 1) {
                    $this->redis->expire($key, $window);
                }
            } catch (\Exception $e) {
                // Redis 操作失败，使用文件后备方案
                $current = $this->incrementFileCounter($key, $window);
            }
        } else {
            // 使用文件后备方案
            $current = $this->incrementFileCounter($key, $window);
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
     * 获取 Redis 客户端（修复：支持离线模式，Redis 不可用时返回 null）
     */
    private function getRedisClient(): ?RedisClient
    {
        try {
            $config = $this->configManager->get('database.redis') ?? [];
            
            // 如果没有配置 Redis，返回 null（离线模式）
            if (empty($config) || !($config['host'] ?? false)) {
                return null;
            }
            
            $client = new RedisClient([
                'scheme' => 'tcp',
                'host' => $config['host'] ?? '127.0.0.1',
                'port' => $config['port'] ?? 6379,
                'password' => $config['password'] ?? null,
                'database' => $config['database'] ?? 0,
                'timeout' => 1, // 快速超时，避免阻塞
                'read_timeout' => 1,
            ]);
            
            // 测试连接（非阻塞）
            try {
                $client->ping();
            } catch (\Exception $e) {
                // 连接失败，返回 null（使用文件后备方案）
                return null;
            }
            
            return $client;
        } catch (\Exception $e) {
            // Redis 不可用，返回 null（离线模式）
            return null;
        }
    }
    
    /**
     * 文件后备方案：递增计数器（用于频率限制）
     */
    private function incrementFileCounter(string $key, int $ttl): int
    {
        $filePath = runtime_path('rate_limit/' . md5($key) . '.json');
        $dir = dirname($filePath);
        
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        $data = ['count' => 0, 'expires' => time() + $ttl];
        
        if (file_exists($filePath)) {
            $content = @file_get_contents($filePath);
            if ($content) {
                $data = json_decode($content, true) ?? $data;
            }
        }
        
        // 检查是否过期
        if ($data['expires'] < time()) {
            $data = ['count' => 0, 'expires' => time() + $ttl];
        }
        
        $data['count']++;
        
        file_put_contents($filePath, json_encode($data), LOCK_EX);
        
        return $data['count'];
    }
}
