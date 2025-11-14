<?php

namespace app\license\validator;

use app\waf\config\ConfigManager;

/**
 * 许可证验证器
 * 负责验证许可证密钥的有效性
 */
class LicenseValidator
{
    private ConfigManager $configManager;
    
    /** @var string 许可证验证服务器地址 */
    private string $licenseServer;
    
    /** @var int 验证超时时间 */
    private int $timeout;
    
    /** @var bool 离线模式 */
    private bool $offlineMode;
    
    public function __construct(ConfigManager $configManager)
    {
        $this->configManager = $configManager;
        $this->licenseServer = $this->configManager->get('license.server', 'https://license.tiangangwaf.com');
        $this->timeout = $this->configManager->get('license.timeout', 10);
        $this->offlineMode = $this->configManager->get('license.offline_mode', false);
    }
    
    /**
     * 验证许可证
     * 
     * @param string $pluginName 插件名称
     * @param string $licenseKey 许可证密钥
     * @return array 验证结果
     */
    public function validateLicense(string $pluginName, string $licenseKey): array
    {
        // 离线模式验证
        if ($this->offlineMode) {
            return $this->validateOffline($pluginName, $licenseKey);
        }
        
        // 在线验证
        return $this->validateOnline($pluginName, $licenseKey);
    }
    
    /**
     * 在线验证许可证
     * 
     * @param string $pluginName 插件名称
     * @param string $licenseKey 许可证密钥
     * @return array 验证结果
     */
    private function validateOnline(string $pluginName, string $licenseKey): array
    {
        try {
            $url = $this->licenseServer . '/api/v1/validate';
            $data = [
                'plugin_name' => $pluginName,
                'license_key' => $licenseKey,
                'instance_id' => $this->getInstanceId(),
                'timestamp' => time()
            ];
            
            $options = [
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/json\r\n",
                    'content' => json_encode($data),
                    'timeout' => $this->timeout
                ]
            ];
            
            $context = stream_context_create($options);
            $response = file_get_contents($url, false, $context);
            
            if ($response === false) {
                throw new \RuntimeException('无法连接到许可证服务器');
            }
            
            $result = json_decode($response, true);
            
            if (!$result || !isset($result['success'])) {
                throw new \RuntimeException('无效的服务器响应');
            }
            
            if ($result['success']) {
                return [
                    'valid' => true,
                    'message' => $result['message'] ?? '许可证验证成功',
                    'expires_at' => $result['expires_at'] ?? null,
                    'features' => $result['features'] ?? []
                ];
            } else {
                return [
                    'valid' => false,
                    'message' => $result['message'] ?? '许可证验证失败',
                    'error_code' => $result['error_code'] ?? 'UNKNOWN_ERROR'
                ];
            }
            
        } catch (\Exception $e) {
            // 在线验证失败时，尝试离线验证
            return $this->validateOffline($pluginName, $licenseKey);
        }
    }
    
    /**
     * 离线验证许可证
     * 
     * @param string $pluginName 插件名称
     * @param string $licenseKey 许可证密钥
     * @return array 验证结果
     */
    private function validateOffline(string $pluginName, string $licenseKey): array
    {
        // 检查本地缓存的有效许可证
        $cachedLicenses = $this->configManager->get('license.cached_licenses', []);
        
        if (isset($cachedLicenses[$pluginName]) && $cachedLicenses[$pluginName]['key'] === $licenseKey) {
            $license = $cachedLicenses[$pluginName];
            
            // 检查过期时间
            if (isset($license['expires_at']) && $license['expires_at'] < time()) {
                return [
                    'valid' => false,
                    'message' => '许可证已过期',
                    'error_code' => 'LICENSE_EXPIRED'
                ];
            }
            
            return [
                'valid' => true,
                'message' => '离线许可证验证成功',
                'expires_at' => $license['expires_at'] ?? null,
                'features' => $license['features'] ?? []
            ];
        }
        
        // 简单格式验证
        if (!$this->validateLicenseFormat($licenseKey)) {
            return [
                'valid' => false,
                'message' => '许可证格式无效',
                'error_code' => 'INVALID_FORMAT'
            ];
        }
        
        // 对于开发环境，允许特定格式的测试许可证
        if ($this->isDevelopmentEnvironment() && $this->isTestLicense($licenseKey)) {
            return [
                'valid' => true,
                'message' => '开发环境测试许可证',
                'expires_at' => time() + 86400 * 30, // 30天
                'features' => ['basic']
            ];
        }
        
        return [
            'valid' => false,
            'message' => '离线许可证验证失败，请检查网络连接或联系技术支持',
            'error_code' => 'OFFLINE_VALIDATION_FAILED'
        ];
    }
    
    /**
     * 验证许可证格式
     * 
     * @param string $licenseKey 许可证密钥
     * @return bool 格式是否有效
     */
    private function validateLicenseFormat(string $licenseKey): bool
    {
        // 基本格式检查：长度、字符类型等
        if (strlen($licenseKey) < 16 || strlen($licenseKey) > 128) {
            return false;
        }
        
        // 检查是否包含无效字符
        if (!preg_match('/^[a-zA-Z0-9\-]+$/', $licenseKey)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * 检查是否为测试许可证
     * 
     * @param string $licenseKey 许可证密钥
     * @return bool 是否为测试许可证
     */
    private function isTestLicense(string $licenseKey): bool
    {
        return strpos($licenseKey, 'TEST-') === 0 || 
               strpos($licenseKey, 'DEV-') === 0;
    }
    
    /**
     * 检查是否为开发环境
     * 
     * @return bool 是否为开发环境
     */
    private function isDevelopmentEnvironment(): bool
    {
        return getenv('APP_ENV') === 'development' || 
               getenv('APP_DEBUG') === 'true' ||
               php_sapi_name() === 'cli-server';
    }
    
    /**
     * 获取实例ID
     * 
     * @return string 实例ID
     */
    private function getInstanceId(): string
    {
        $instanceId = $this->configManager->get('license.instance_id');
        if (!$instanceId) {
            // 生成基于机器信息的实例ID
            $instanceId = md5(gethostname() . php_uname('n') . $this->configManager->get('app.name', 'tiangang-waf'));
            $this->configManager->set('license.instance_id', $instanceId);
        }
        
        return $instanceId;
    }
    
    /**
     * 缓存许可证信息
     * 
     * @param string $pluginName 插件名称
     * @param array $licenseInfo 许可证信息
     */
    public function cacheLicense(string $pluginName, array $licenseInfo): void
    {
        $cachedLicenses = $this->configManager->get('license.cached_licenses', []);
        $cachedLicenses[$pluginName] = array_merge($licenseInfo, [
            'cached_at' => time()
        ]);
        
        $this->configManager->set('license.cached_licenses', $cachedLicenses);
    }
    
    /**
     * 清除缓存的许可证
     * 
     * @param string|null $pluginName 插件名称，为空则清除所有缓存
     */
    public function clearCachedLicense(?string $pluginName = null): void
    {
        $cachedLicenses = $this->configManager->get('license.cached_licenses', []);
        
        if ($pluginName) {
            unset($cachedLicenses[$pluginName]);
        } else {
            $cachedLicenses = [];
        }
        
        $this->configManager->set('license.cached_licenses', $cachedLicenses);
    }
}