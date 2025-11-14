<?php

namespace app\waf\plugins;

use app\waf\config\ConfigManager;
use app\license\manager\PluginLicenseManager;

/**
 * 插件管理器
 * 
 * 负责插件的加载、管理和调度，支持许可证检查
 */
class PluginManager
{
    private array $plugins = [];
    private array $pluginInfo = [];
    private ConfigManager $configManager;
    private PluginLicenseManager $licenseManager;
    private string $pluginPath;
    
    public function __construct()
    {
        $this->configManager = new ConfigManager();
        $this->licenseManager = new PluginLicenseManager(
            new \app\license\validator\LicenseValidator($this->configManager),
            $this,
            $this->configManager
        );
        $this->pluginPath = base_path('plugins/waf');
        $this->loadPlugins();
    }
    
    /**
     * 加载所有插件（修复：加强路径验证）
     */
    private function loadPlugins(): void
    {
        if (!is_dir($this->pluginPath)) {
            return;
        }
        
        // 获取真实路径
        $realPluginPath = realpath($this->pluginPath);
        if ($realPluginPath === false) {
            error_log('Plugin directory does not exist: ' . $this->pluginPath);
            return;
        }
        
        // 使用 glob 查找插件文件，但进一步验证每个文件
        $pluginFiles = glob($realPluginPath . '/*.php');
        if ($pluginFiles === false) {
            return;
        }
        
        foreach ($pluginFiles as $pluginFile) {
            // 再次验证文件在允许的目录内（防御深度）
            $realFile = realpath($pluginFile);
            if ($realFile !== false && strpos($realFile, $realPluginPath) === 0) {
                $this->loadPlugin($realFile);
            } else {
                error_log('Skipping invalid plugin file path: ' . $pluginFile);
            }
        }
    }
    
    /**
     * 加载单个插件（修复：加强路径验证，防止路径遍历和任意文件包含）
     */
    private function loadPlugin(string $pluginFile): void
    {
        try {
            // 1. 验证文件路径（防止路径遍历）
            $realPluginPath = realpath($this->pluginPath);
            if ($realPluginPath === false) {
                throw new \RuntimeException('Plugin directory does not exist: ' . $this->pluginPath);
            }
            
            $realPluginFile = realpath($pluginFile);
            if ($realPluginFile === false) {
                throw new \RuntimeException('Plugin file does not exist: ' . $pluginFile);
            }
            
            // 2. 验证文件在允许的插件目录内
            if (strpos($realPluginFile, $realPluginPath) !== 0) {
                throw new \InvalidArgumentException('Plugin file path is outside allowed directory. Attempted: ' . $pluginFile);
            }
            
            // 3. 验证文件扩展名
            if (pathinfo($pluginFile, PATHINFO_EXTENSION) !== 'php') {
                throw new \InvalidArgumentException('Plugin file must be a PHP file: ' . $pluginFile);
            }
            
            // 4. 验证文件可读
            if (!is_readable($realPluginFile)) {
                throw new \RuntimeException('Plugin file is not readable: ' . $pluginFile);
            }
            
            // 5. 验证文件不是符号链接（防止通过符号链接访问其他目录）
            if (is_link($realPluginFile)) {
                $linkTarget = readlink($realPluginFile);
                $realLinkTarget = realpath($linkTarget);
                if ($realLinkTarget === false || strpos($realLinkTarget, $realPluginPath) !== 0) {
                    throw new \InvalidArgumentException('Plugin file is a symlink pointing outside allowed directory');
                }
            }
            
            // 6. 包含插件文件（现在相对安全）
            require_once $realPluginFile;
            
            // 7. 动态加载插件类
            $className = $this->getClassNameFromFile($realPluginFile);
            if ($className && class_exists($className)) {
                $plugin = new $className();
                
                if ($plugin instanceof WafPluginInterface) {
                    $pluginName = $plugin->getName();
                    
                    // 检查插件是否需要许可证
                    $requiresLicense = $plugin->requiresLicense();
                    
                    // 检查插件许可证（传入 requiresLicense 参数避免循环依赖）
                    $licenseResult = $this->licenseManager->validatePluginLicense($pluginName, null, $requiresLicense);
                    
                    // 存储插件信息
                    $this->pluginInfo[$pluginName] = [
                        'class' => $className,
                        'file' => basename($realPluginFile),
                        'requires_license' => $requiresLicense,
                        'license_valid' => $licenseResult['valid'],
                        'license_message' => $licenseResult['message']
                    ];
                    
                    // 只有许可证有效的插件才被加载（免费插件默认有效）
                    if ($licenseResult['valid']) {
                        $this->plugins[$pluginName] = $plugin;
                    } else {
                        error_log("Plugin {$pluginName} license invalid: " . $licenseResult['message']);
                    }
                }
            }
        } catch (\Exception $e) {
            // 记录插件加载错误（记录真实路径而非用户提供的路径）
            error_log(sprintf(
                'Failed to load plugin [%s]: %s',
                basename($pluginFile),
                $e->getMessage()
            ));
        }
    }
    
    /**
     * 从文件路径获取类名（修复：加强验证）
     */
    private function getClassNameFromFile(string $pluginFile): ?string
    {
        // 验证文件路径
        if (!is_readable($pluginFile)) {
            return null;
        }
        
        $content = file_get_contents($pluginFile);
        if (!$content) {
            return null;
        }
        
        // 限制文件大小（防止读取超大文件）
        if (strlen($content) > 1024 * 1024) { // 1MB
            error_log('Plugin file too large: ' . basename($pluginFile));
            return null;
        }
        
        // 提取命名空间和类名
        $namespace = '';
        $className = '';
        
        // 提取命名空间（验证格式）
        if (preg_match('/namespace\s+([a-zA-Z0-9\\\\_]+);/', $content, $matches)) {
            $namespace = trim($matches[1]);
        }
        
        // 提取类名（验证格式）
        if (preg_match('/class\s+([a-zA-Z0-9_]+)/', $content, $matches)) {
            $className = trim($matches[1]);
        }
        
        if ($className) {
            return $namespace ? "{$namespace}\\{$className}" : $className;
        }
        
        return null;
    }
    
    /**
     * 获取插件
     */
    public function getPlugin(string $name): ?WafPluginInterface
    {
        return $this->plugins[$name] ?? null;
    }
    
    /**
     * 获取所有插件
     */
    public function getAllPlugins(): array
    {
        return $this->plugins;
    }
    
    /**
     * 获取启用的插件
     */
    public function getEnabledPlugins(): array
    {
        return array_filter($this->plugins, function ($plugin) {
            return $plugin->isEnabled();
        });
    }
    
    /**
     * 重新加载插件
     */
    public function reload(): void
    {
        $this->plugins = [];
        $this->loadPlugins();
    }
    
    /**
     * 启用插件
     */
    public function enablePlugin(string $name): bool
    {
        $plugin = $this->getPlugin($name);
        if ($plugin) {
            // TODO: 实现插件启用逻辑
            return true;
        }
        return false;
    }
    
    /**
     * 禁用插件
     */
    public function disablePlugin(string $name): bool
    {
        $plugin = $this->getPlugin($name);
        if ($plugin) {
            // TODO: 实现插件禁用逻辑
            return true;
        }
        return false;
    }
    
    /**
     * 获取插件信息
     */
    public function getPluginInfo(string $name): ?array
    {
        return $this->pluginInfo[$name] ?? null;
    }
    
    /**
     * 获取所有插件信息
     */
    public function getAllPluginInfo(): array
    {
        return $this->pluginInfo;
    }
    
    /**
     * 检查插件是否已授权
     */
    public function isPluginAuthorized(string $name): bool
    {
        return $this->licenseManager->isPluginAuthorized($name);
    }
    
    /**
     * 验证插件许可证
     */
    public function validatePluginLicense(string $name, ?string $licenseKey = null): array
    {
        return $this->licenseManager->validatePluginLicense($name, $licenseKey);
    }
    
    /**
     * 获取许可证管理器
     */
    public function getLicenseManager(): PluginLicenseManager
    {
        return $this->licenseManager;
    }
    
    /**
     * 获取已授权的插件列表
     */
    public function getAuthorizedPlugins(): array
    {
        return array_filter($this->plugins, function ($plugin) {
            return $this->isPluginAuthorized($plugin->getName());
        });
    }
    
    /**
     * 获取需要许可证但未授权的插件列表
     */
    public function getUnauthorizedPlugins(): array
    {
        $unauthorized = [];
        
        foreach ($this->pluginInfo as $pluginName => $info) {
            if ($info['requires_license'] && !$info['license_valid']) {
                $unauthorized[$pluginName] = $info;
            }
        }
        
        return $unauthorized;
    }
}
