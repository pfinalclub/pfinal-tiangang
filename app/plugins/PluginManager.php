<?php

namespace Tiangang\Waf\Plugins;

use Tiangang\Waf\Config\ConfigManager;

/**
 * 插件管理器
 * 
 * 负责插件的加载、管理和调度
 */
class PluginManager
{
    private array $plugins = [];
    private ConfigManager $configManager;
    private string $pluginPath;
    
    public function __construct()
    {
        $this->configManager = new ConfigManager();
        $this->pluginPath = __DIR__ . '/../../plugins/waf';
        $this->loadPlugins();
    }
    
    /**
     * 加载所有插件
     */
    private function loadPlugins(): void
    {
        if (!is_dir($this->pluginPath)) {
            return;
        }
        
        foreach (glob($this->pluginPath . '/*.php') as $pluginFile) {
            $this->loadPlugin($pluginFile);
        }
    }
    
    /**
     * 加载单个插件
     */
    private function loadPlugin(string $pluginFile): void
    {
        try {
            // 包含插件文件
            require_once $pluginFile;
            
            // 动态加载插件类
            $className = $this->getClassNameFromFile($pluginFile);
            if ($className && class_exists($className)) {
                $plugin = new $className();
                
                if ($plugin instanceof WafPluginInterface) {
                    $this->plugins[$plugin->getName()] = $plugin;
                }
            }
        } catch (\Exception $e) {
            // 记录插件加载错误
            error_log("Failed to load plugin {$pluginFile}: " . $e->getMessage());
        }
    }
    
    /**
     * 从文件路径获取类名
     */
    private function getClassNameFromFile(string $pluginFile): ?string
    {
        $content = file_get_contents($pluginFile);
        if (!$content) {
            return null;
        }
        
        // 提取命名空间和类名
        $namespace = '';
        $className = '';
        
        // 提取命名空间
        if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
            $namespace = $matches[1];
        }
        
        // 提取类名
        if (preg_match('/class\s+(\w+)/', $content, $matches)) {
            $className = $matches[1];
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
}
