<?php

namespace app\waf\plugins;

use app\waf\plugins\WafPluginInterface;

/**
 * IP 黑名单规则插件
 */
class IpBlacklistRule implements WafPluginInterface
{
    private array $blacklist;
    private array $whitelist;
    private array $config;
    
    public function __construct()
    {
        $this->config = [
            'enabled' => true,
            'priority' => 100,
            'blacklist' => [
                '127.0.0.1',
                '192.168.1.100',
                '10.0.0.50',
                '192.168.1.0/24',
                '10.0.0.0/8'
            ],
            'whitelist' => [
                '127.0.0.1',
                '::1',
                '192.168.1.1'
            ],
            'dynamic_blacklist' => [
                'enabled' => true,
                'threshold' => 10,
                'window' => 300,
                'duration' => 3600
            ]
        ];
        
        $this->blacklist = $this->config['blacklist'];
        $this->whitelist = $this->config['whitelist'];
    }
    
    public function getName(): string
    {
        return 'ip_blacklist';
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
        
        if (empty($ip)) {
            return ['matched' => false];
        }
        
        // 检查白名单（优先级最高）
        if ($this->isWhitelisted($ip)) {
            return ['matched' => false];
        }
        
        // 检查黑名单
        if ($this->isBlacklisted($ip)) {
            return [
                'matched' => true,
                'rule' => $this->getName(),
                'severity' => 'critical',
                'description' => 'IP 地址在黑名单中',
                'details' => [
                    'ip' => $ip,
                    'blacklist_entry' => $this->getMatchedBlacklistEntry($ip),
                    'timestamp' => time()
                ]
            ];
        }
        
        // 检查动态黑名单
        if ($this->config['dynamic_blacklist']['enabled']) {
            $dynamicResult = $this->checkDynamicBlacklist($ip, $requestData);
            if ($dynamicResult['matched']) {
                return $dynamicResult;
            }
        }
        
        return ['matched' => false];
    }
    
    public function getDescription(): string
    {
        return 'IP 黑名单检测规则';
    }
    
    public function getConfig(): array
    {
        return $this->config;
    }
    
    public function supportsQuickDetection(): bool
    {
        // IP黑名单检查适合快速检测
        return true;
    }
    
    public function requiresLicense(): bool
    {
        // IP黑名单集成威胁情报需要付费许可证
        return true;
    }
    
    /**
     * 检查是否在白名单中
     */
    private function isWhitelisted(string $ip): bool
    {
        foreach ($this->whitelist as $whiteIp) {
            if ($this->matchIp($ip, $whiteIp)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * 检查是否在黑名单中
     */
    private function isBlacklisted(string $ip): bool
    {
        foreach ($this->blacklist as $blackIp) {
            if ($this->matchIp($ip, $blackIp)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * 检查动态黑名单
     */
    private function checkDynamicBlacklist(string $ip, array $requestData): array
    {
        // TODO: 实现动态黑名单逻辑
        // 这里可以基于请求频率、攻击模式等动态添加 IP 到黑名单
        return ['matched' => false];
    }
    
    /**
     * 获取匹配的黑名单条目
     */
    private function getMatchedBlacklistEntry(string $ip): string
    {
        foreach ($this->blacklist as $blackIp) {
            if ($this->matchIp($ip, $blackIp)) {
                return $blackIp;
            }
        }
        return '';
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
        
        if ($ipLong === false || $subnetLong === false) {
            return false;
        }
        
        $maskLong = -1 << (32 - $mask);
        return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
    }
}
