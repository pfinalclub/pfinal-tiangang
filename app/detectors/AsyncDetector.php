<?php

namespace Tiangang\Waf\Detectors;

use PfinalClub\Asyncio\{create_task, gather, wait_for, sleep, run};
use Tiangang\Waf\Plugins\PluginManager;
use Tiangang\Waf\Config\ConfigManager;

/**
 * 异步检测器
 * 
 * 负责异步深度检测，使用 pfinal-asyncio 进行并发检测
 */
class AsyncDetector
{
    private PluginManager $pluginManager;
    private ConfigManager $configManager;
    private array $config;
    
    public function __construct()
    {
        $this->pluginManager = new PluginManager();
        $this->configManager = new ConfigManager();
        $this->config = $this->configManager->get('waf');
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
                
                // 如果是 Generator，运行它
                if ($result instanceof \Generator) {
                    $result = \PfinalClub\Asyncio\run($result);
                }
                
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
    public function check(array $requestData): \Generator
    {
        // 加载启用的插件
        $enabledPlugins = $this->getEnabledPlugins();
        
        if (empty($enabledPlugins)) {
            return [];
        }
        
        // 并发执行所有插件检测
        $tasks = [];
        foreach ($enabledPlugins as $plugin) {
            $tasks[] = $this->runPluginAsync($plugin, $requestData);
        }
        
        $results = yield \PfinalClub\Asyncio\gather($tasks);
        
        // 过滤有效结果
        $validResults = array_filter($results, function($result) {
            return $result['success'] && ($result['result']['matched'] ?? false);
        });
        
        return $validResults;
    }
    
    /**
     * 异步运行单个插件
     */
    private function runPluginAsync($plugin, array $requestData): \Generator
    {
        try {
            yield \PfinalClub\Asyncio\sleep(0.001); // 模拟异步处理
            
            $result = $plugin->detect($requestData);
            
            // 如果是 Generator，运行它
            if ($result instanceof \Generator) {
                $result = yield $result;
            }
            
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
    private function runPlugin($plugin, array $requestData): \Generator
    {
        try {
            $result = yield $plugin->detect($requestData);
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
        $enabledRules = $this->config['rules']['enabled'] ?? [];
        $plugins = [];
        
        foreach ($enabledRules as $ruleName) {
            $plugin = $this->pluginManager->getPlugin($ruleName);
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
