<?php

namespace app\waf\detectors;

use PfinalClub\Asyncio\{create_task, gather, wait_for, sleep, run};
use app\waf\plugins\PluginManager;
use app\waf\config\ConfigManager;

/**
 * 异步检测器
 * 
 * 负责异步深度检测，使用 pfinal-asyncio 进行并发检测
 */
class AsyncDetector
{
    private PluginManager $pluginManager;
    private ConfigManager $configManager;
    private ?array $config;
    
    public function __construct(ConfigManager $configManager, PluginManager $pluginManager)
    {
        $this->configManager = $configManager;
        $this->pluginManager = $pluginManager;
        $this->config = $this->configManager->get('waf') ?? [];
    }
    
    /**
     * 同步检测
     */
    public function detectSync(array $requestData): array
    {
        // 加载启用的插件
        $enabledPlugins = $this->getEnabledPlugins();
        
        if (empty($enabledPlugins)) {
            return [];
        }
        
        // 同步运行插件检测
        $results = [];
        foreach ($enabledPlugins as $plugin) {
            try {
                $result = $plugin->detect($requestData);
                
                $results[] = [
                    'matched' => $result['matched'] ?? false,
                    'rule' => $result['rule'] ?? 'unknown',
                    'severity' => $result['severity'] ?? 'medium',
                    'details' => $result['details'] ?? [],
                ];
            } catch (\Exception $e) {
                // 插件检测失败，记录日志但不影响其他检测
                error_log('Plugin detection failed: ' . $e->getMessage());
            }
        }
        
        return $results;
    }

    /**
     * 异步检测
     */
    public function check(array $requestData): array
    {
        // 加载启用的插件
        $enabledPlugins = $this->getEnabledPlugins();
        
        if (empty($enabledPlugins)) {
            return [];
        }
        
        // 并发执行所有插件检测
        $tasks = [];
        foreach ($enabledPlugins as $plugin) {
            $tasks[] = \PfinalClub\Asyncio\create_task(
                fn() => $this->runPluginAsync($plugin, $requestData)
            );
        }
        
        $results = \PfinalClub\Asyncio\gather(...$tasks);
        
        // 过滤有效结果
        $validResults = array_filter($results, function($result) {
            return $result['success'] && ($result['result']['matched'] ?? false);
        });
        
        return $validResults;
    }
    
    /**
     * 异步运行单个插件
     */
    private function runPluginAsync($plugin, array $requestData): array
    {
        try {
            \PfinalClub\Asyncio\sleep(0.001); // 模拟异步处理
            
            $result = $plugin->detect($requestData);
            
            return [
                'plugin' => $plugin->getName(),
                'result' => $result,
                'success' => true,
            ];
        } catch (\Exception $e) {
            return [
                'plugin' => $plugin->getName(),
                'result' => null,
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * 运行单个插件（保留兼容性）
     */
    private function runPlugin($plugin, array $requestData): array
    {
        try {
            $result = $plugin->detect($requestData);
            return [
                'plugin' => $plugin->getName(),
                'result' => $result,
                'success' => true,
            ];
        } catch (\Exception $e) {
            return [
                'plugin' => $plugin->getName(),
                'result' => null,
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
    
    /**
     * 获取启用的插件
     */
    private function getEnabledPlugins(): array
    {
        // 从插件管理器获取已启用且已授权的插件
        $enabledPlugins = $this->pluginManager->getEnabledPlugins();
        $authorizedPlugins = $this->pluginManager->getAuthorizedPlugins();
        
        // 获取已启用且已授权的插件交集（基于插件名称）
        $enabledPluginNames = array_keys($enabledPlugins);
        $authorizedPluginNames = array_keys($authorizedPlugins);
        $availablePluginNames = array_intersect($enabledPluginNames, $authorizedPluginNames);
        
        $plugins = [];
        
        foreach ($availablePluginNames as $pluginName) {
            $plugin = $this->pluginManager->getPlugin($pluginName);
            if ($plugin && $plugin->isEnabled()) {
                $plugins[] = $plugin;
            }
        }
        
        // 按优先级排序
        usort($plugins, function ($a, $b) {
            return $b->getPriority() <=> $a->getPriority();
        });
        
        return $plugins;
    }
    
    /**
     * 合并检测结果
     */
    private function mergeResults(array $results): array
    {
        $merged = [];
        
        foreach ($results as $result) {
            if (!$result['success']) {
                continue;
            }
            
            $pluginResult = $result['result'];
            if (is_array($pluginResult)) {
                $merged[] = array_merge($pluginResult, [
                    'plugin' => $result['plugin'],
                ]);
            }
        }
        
        return $merged;
    }
}
