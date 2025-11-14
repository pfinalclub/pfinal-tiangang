<?php

namespace app\license\manager;

use app\license\validator\LicenseValidator;
use app\waf\plugins\PluginManager;
use app\waf\config\ConfigManager;

/**
 * 插件许可证管理器
 * 负责验证插件许可证的有效性，管理许可证状态
 */
class PluginLicenseManager
{
    private LicenseValidator $validator;
    private PluginManager $pluginManager;
    private ConfigManager $configManager;
    
    /** @var array 许可证缓存 */
    private array $licenseCache = [];
    
    /** @var array 许可证状态 */
    private array $licenseStatus = [];
    
    public function __construct(
        LicenseValidator $validator,
        PluginManager $pluginManager,
        ConfigManager $configManager
    ) {
        $this->validator = $validator;
        $this->pluginManager = $pluginManager;
        $this->configManager = $configManager;
    }
    
    /**
     * 验证插件许可证
     * 
     * @param string $pluginName 插件名称
     * @param string|null $licenseKey 许可证密钥
     * @param bool $requiresLicense 是否需要许可证（在插件加载时传入）
     * @return array 验证结果
     */
    public function validatePluginLicense(string $pluginName, ?string $licenseKey = null, bool $requiresLicense = true): array
    {
        // 检查缓存
        if (isset($this->licenseCache[$pluginName])) {
            return $this->licenseCache[$pluginName];
        }
        
        // 如果不需要许可证，直接返回成功
        if (!$requiresLicense) {
            $result = $this->createLicenseResult(true, "免费插件，无需许可证");
            $this->licenseCache[$pluginName] = $result;
            $this->licenseStatus[$pluginName] = true;
            return $result;
        }
        
        // 获取许可证密钥
        if (!$licenseKey) {
            $licenseKey = $this->configManager->get("license.{$pluginName}.key");
        }
        
        if (!$licenseKey) {
            $result = $this->createLicenseResult(false, "未找到插件许可证密钥");
            $this->licenseCache[$pluginName] = $result;
            $this->licenseStatus[$pluginName] = false;
            return $result;
        }
        
        // 验证许可证
        $validationResult = $this->validator->validateLicense($pluginName, $licenseKey);
        
        // 缓存结果
        $this->licenseCache[$pluginName] = $validationResult;
        $this->licenseStatus[$pluginName] = $validationResult['valid'];
        
        return $validationResult;
    }
    
    /**
     * 检查插件是否已授权
     * 
     * @param string $pluginName 插件名称
     * @return bool 是否已授权
     */
    public function isPluginAuthorized(string $pluginName): bool
    {
        if (isset($this->licenseStatus[$pluginName])) {
            return $this->licenseStatus[$pluginName];
        }
        
        $result = $this->validatePluginLicense($pluginName);
        return $result['valid'];
    }
    
    /**
     * 获取所有插件许可证状态
     * 
     * @return array 许可证状态数组
     */
    public function getAllLicenseStatus(): array
    {
        $plugins = $this->pluginManager->getAllPlugins();
        $status = [];
        
        foreach ($plugins as $pluginName => $pluginInfo) {
            $status[$pluginName] = [
                'name' => $pluginName,
                'requires_license' => $pluginInfo['requires_license'] ?? false,
                'authorized' => $this->isPluginAuthorized($pluginName),
                'last_validation' => $this->licenseCache[$pluginName]['timestamp'] ?? null,
                'message' => $this->licenseCache[$pluginName]['message'] ?? ''
            ];
        }
        
        return $status;
    }
    
    /**
     * 清除许可证缓存
     * 
     * @param string|null $pluginName 插件名称，为空则清除所有缓存
     */
    public function clearLicenseCache(?string $pluginName = null): void
    {
        if ($pluginName) {
            unset($this->licenseCache[$pluginName]);
            unset($this->licenseStatus[$pluginName]);
        } else {
            $this->licenseCache = [];
            $this->licenseStatus = [];
        }
    }
    
    /**
     * 创建许可证验证结果
     * 
     * @param bool $valid 是否有效
     * @param string $message 消息
     * @param array $extraData 额外数据
     * @return array 验证结果
     */
    private function createLicenseResult(bool $valid, string $message, array $extraData = []): array
    {
        return array_merge([
            'valid' => $valid,
            'message' => $message,
            'timestamp' => time()
        ], $extraData);
    }
}