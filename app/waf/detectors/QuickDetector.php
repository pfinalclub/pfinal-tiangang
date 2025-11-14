<?php

namespace app\waf\detectors;

use app\waf\core\WafResult;
use app\waf\config\ConfigManager;
use app\waf\plugins\PluginManager;
use Predis\Client as RedisClient;

/**
 * 快速检测器
 * 
 * 负责快速同步检测，如 IP 黑白名单、基础正则匹配等
 */
class QuickDetector
{
    private ConfigManager $configManager;
    private PluginManager $pluginManager;
    private ?RedisClient $redis;
    private ?array $config;
    
    public function __construct(ConfigManager $configManager, PluginManager $pluginManager)
    {
        $this->configManager = $configManager;
        $this->pluginManager = $pluginManager;
        $this->config = $configManager->get('waf') ?? [];
        $this->redis = $this->getRedisClient();
    }
    
    /**
     * 异步快速检测
     */
    public function checkAsync(array $requestData, ?array $enabledPlugins = null): WafResult
    {
        // 获取启用的插件
        if ($enabledPlugins === null) {
            $enabledPlugins = $this->pluginManager->getAuthorizedPlugins();
        }
        
        // 并发执行IP黑名单检查和插件检测
        $results = \PfinalClub\Asyncio\gather(
            $this->checkIpBlacklistAsync($requestData['ip']),
            $this->checkWithPluginsAsync($requestData, $enabledPlugins)
        );

        // 合并结果
        $allResults = array_merge($results[0], $results[1]);
        
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
     * @param array $enabledPlugins 启用的插件类名列表（可选，如果不提供则使用插件管理器）
     */
    public function check(array $requestData, ?array $enabledPlugins = null): WafResult
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
        
        // 使用插件系统进行检测
        $pluginResult = $this->checkWithPlugins($requestData, $enabledPlugins);
        if ($pluginResult->isBlocked()) {
            return $pluginResult;
        }
        
        return WafResult::allow();
    }
    
    /**
     * 使用插件系统进行检测
     */
    private function checkWithPlugins(array $requestData, ?array $enabledPlugins = null): WafResult
    {
        // 获取启用的插件
        if ($enabledPlugins === null) {
            $enabledPlugins = $this->pluginManager->getAuthorizedPlugins();
        }
        
        // 获取所有插件实例
        $allPlugins = $this->pluginManager->getAllPlugins();
        
        foreach ($allPlugins as $plugin) {
            $pluginClass = get_class($plugin);
            
            // 检查插件是否启用且已授权
            if (!in_array($pluginClass, $enabledPlugins)) {
                continue;
            }
            
            // 检查插件是否支持快速检测
            if (!$plugin->supportsQuickDetection()) {
                continue;
            }
            
            // 执行插件检测
            $result = $plugin->check($requestData);
            if ($result->isBlocked()) {
                return $result;
            }
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
     * 异步使用插件系统进行检测
     */
    private function checkWithPluginsAsync(array $requestData, ?array $enabledPlugins = null): array
    {
        \PfinalClub\Asyncio\sleep(0.001); // 模拟异步处理
        
        // 获取启用的插件
        if ($enabledPlugins === null) {
            $enabledPlugins = $this->pluginManager->getAuthorizedPlugins();
        }
        
        // 获取所有插件实例
        $allPlugins = $this->pluginManager->getAllPlugins();
        
        $results = [];
        foreach ($allPlugins as $plugin) {
            $pluginClass = get_class($plugin);
            
            // 检查插件是否启用且已授权
            if (!in_array($pluginClass, $enabledPlugins)) {
                continue;
            }
            
            // 检查插件是否支持快速检测
            if (!$plugin->supportsQuickDetection()) {
                continue;
            }
            
            // 执行插件检测
            $result = $plugin->check($requestData);
            if ($result->isBlocked()) {
                $results[] = $result;
            }
        }
        
        return $results;
    }

    /**
     * 异步检查 IP 黑名单
     */
    private function checkIpBlacklistAsync(string $ip): array
    {
        \PfinalClub\Asyncio\sleep(0.001); // 模拟异步查询
        
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
