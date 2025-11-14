<?php

namespace App\License\Config;

use App\Waf\Config\ConfigManager;

/**
 * 许可证配置管理器
 * 负责管理许可证相关的配置信息
 */
class LicenseConfig
{
    private ConfigManager $configManager;
    
    public function __construct(ConfigManager $configManager)
    {
        $this->configManager = $configManager;
    }
    
    /**
     * 获取许可证配置
     * 
     * @return array 许可证配置
     */
    public function getLicenseConfig(): array
    {
        return [
            'server' => $this->configManager->get('license.server', 'https://license.tiangangwaf.com'),
            'timeout' => $this->configManager->get('license.timeout', 10),
            'offline_mode' => $this->configManager->get('license.offline_mode', false),
            'cache_ttl' => $this->configManager->get('license.cache_ttl', 3600),
            'retry_count' => $this->configManager->get('license.retry_count', 3),
            'instance_id' => $this->configManager->get('license.instance_id'),
            'cached_licenses' => $this->configManager->get('license.cached_licenses', []),
            'plugins' => $this->getPluginLicenseConfig()
        ];
    }
    
    /**
     * 获取插件许可证配置
     * 
     * @return array 插件许可证配置
     */
    public function getPluginLicenseConfig(): array
    {
        $plugins = [
            'SqlInjectionRule' => [
                'requires_license' => false, // 基础插件免费
                'license_type' => 'free',
                'features' => ['basic_sql_detection']
            ],
            'XssRule' => [
                'requires_license' => false, // 基础插件免费
                'license_type' => 'free',
                'features' => ['basic_xss_detection']
            ],
            'RateLimitRule' => [
                'requires_license' => false, // 基础插件免费
                'license_type' => 'free',
                'features' => ['basic_rate_limiting']
            ],
            'IpBlacklistRule' => [
                'requires_license' => false, // 基础插件免费
                'license_type' => 'free',
                'features' => ['basic_ip_blacklist']
            ],
            'AdvancedAsyncRule' => [
                'requires_license' => true, // 高级插件需要许可证
                'license_type' => 'premium',
                'features' => ['advanced_async_detection', 'ai_threat_detection']
            ]
        ];
        
        // 合并用户自定义配置
        $customConfig = $this->configManager->get('license.plugins', []);
        return array_merge($plugins, $customConfig);
    }
    
    /**
     * 设置插件许可证密钥
     * 
     * @param string $pluginName 插件名称
     * @param string $licenseKey 许可证密钥
     */
    public function setPluginLicenseKey(string $pluginName, string $licenseKey): void
    {
        $pluginConfig = $this->configManager->get('license.plugins', []);
        $pluginConfig[$pluginName] = $pluginConfig[$pluginName] ?? [];
        $pluginConfig[$pluginName]['license_key'] = $licenseKey;
        
        $this->configManager->set('license.plugins', $pluginConfig);
    }
    
    /**
     * 获取插件许可证密钥
     * 
     * @param string $pluginName 插件名称
     * @return string|null 许可证密钥
     */
    public function getPluginLicenseKey(string $pluginName): ?string
    {
        $pluginConfig = $this->configManager->get('license.plugins', []);
        return $pluginConfig[$pluginName]['license_key'] ?? null;
    }
    
    /**
     * 移除插件许可证密钥
     * 
     * @param string $pluginName 插件名称
     */
    public function removePluginLicenseKey(string $pluginName): void
    {
        $pluginConfig = $this->configManager->get('license.plugins', []);
        if (isset($pluginConfig[$pluginName]['license_key'])) {
            unset($pluginConfig[$pluginName]['license_key']);
            $this->configManager->set('license.plugins', $pluginConfig);
        }
    }
    
    /**
     * 检查插件是否需要许可证
     * 
     * @param string $pluginName 插件名称
     * @return bool 是否需要许可证
     */
    public function requiresLicense(string $pluginName): bool
    {
        $pluginConfig = $this->getPluginLicenseConfig();
        return $pluginConfig[$pluginName]['requires_license'] ?? false;
    }
    
    /**
     * 获取插件许可证类型
     * 
     * @param string $pluginName 插件名称
     * @return string 许可证类型
     */
    public function getLicenseType(string $pluginName): string
    {
        $pluginConfig = $this->getPluginLicenseConfig();
        return $pluginConfig[$pluginName]['license_type'] ?? 'free';
    }
    
    /**
     * 获取插件支持的功能
     * 
     * @param string $pluginName 插件名称
     * @return array 功能列表
     */
    public function getPluginFeatures(string $pluginName): array
    {
        $pluginConfig = $this->getPluginLicenseConfig();
        return $pluginConfig[$pluginName]['features'] ?? [];
    }
    
    /**
     * 设置许可证服务器配置
     * 
     * @param string $server 服务器地址
     * @param int $timeout 超时时间
     */
    public function setServerConfig(string $server, int $timeout = 10): void
    {
        $this->configManager->set('license.server', $server);
        $this->configManager->set('license.timeout', $timeout);
    }
    
    /**
     * 设置离线模式
     * 
     * @param bool $enabled 是否启用离线模式
     */
    public function setOfflineMode(bool $enabled): void
    {
        $this->configManager->set('license.offline_mode', $enabled);
    }
    
    /**
     * 获取许可证统计信息
     * 
     * @return array 统计信息
     */
    public function getLicenseStats(): array
    {
        $pluginConfig = $this->getPluginLicenseConfig();
        $totalPlugins = count($pluginConfig);
        $freePlugins = 0;
        $premiumPlugins = 0;
        
        foreach ($pluginConfig as $config) {
            if (($config['license_type'] ?? 'free') === 'free') {
                $freePlugins++;
            } else {
                $premiumPlugins++;
            }
        }
        
        return [
            'total_plugins' => $totalPlugins,
            'free_plugins' => $freePlugins,
            'premium_plugins' => $premiumPlugins,
            'premium_ratio' => $totalPlugins > 0 ? round($premiumPlugins / $totalPlugins * 100, 2) : 0
        ];
    }
}