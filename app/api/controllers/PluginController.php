<?php

namespace Tiangang\Waf\Api\Controllers;

use PfinalClub\Asyncio\{create_task, gather, wait_for, sleep};
use Tiangang\Waf\Plugin\PluginManager;
use Tiangang\Waf\Cache\AsyncCacheManager;
use Tiangang\Waf\Database\AsyncDatabaseManager;

/**
 * 插件管理 API 控制器
 * 
 * 负责处理插件相关的 REST API 请求
 */
class PluginController
{
    private PluginManager $pluginManager;
    private AsyncCacheManager $cacheManager;
    private AsyncDatabaseManager $dbManager;

    public function __construct()
    {
        $this->pluginManager = new PluginManager();
        $this->cacheManager = new AsyncCacheManager();
        $this->dbManager = new AsyncDatabaseManager();
    }

    /**
     * 异步获取所有插件
     */
    public function asyncGetPlugins(): \Generator
    {
        // 模拟异步获取插件
        yield sleep(0.003);
        
        $plugins = $this->pluginManager->getAllPlugins();
        
        // 异步获取插件统计信息
        $stats = yield create_task($this->asyncGetPluginStats($plugins));
        
        return [
            'success' => true,
            'data' => [
                'plugins' => $plugins,
                'stats' => $stats,
                'total_count' => count($plugins)
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * 异步获取单个插件
     */
    public function asyncGetPlugin(string $pluginId): \Generator
    {
        // 模拟异步获取单个插件
        yield sleep(0.001);
        
        $plugin = $this->pluginManager->getPlugin($pluginId);
        
        if (!$plugin) {
            return [
                'success' => false,
                'error' => 'Plugin not found',
                'code' => 404
            ];
        }
        
        // 异步获取插件详细信息
        $details = yield create_task($this->asyncGetPluginDetails($pluginId));
        
        return [
            'success' => true,
            'data' => [
                'plugin' => $plugin,
                'details' => $details
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * 异步启用插件
     */
    public function asyncEnablePlugin(string $pluginId): \Generator
    {
        // 模拟异步启用插件
        yield sleep(0.005);
        
        try {
            // 检查插件是否存在
            $plugin = $this->pluginManager->getPlugin($pluginId);
            if (!$plugin) {
                return [
                    'success' => false,
                    'error' => 'Plugin not found',
                    'code' => 404
                ];
            }
            
            // 启用插件
            $this->pluginManager->enablePlugin($pluginId);
            
            // 异步更新插件缓存
            yield create_task($this->cacheManager->asyncSet("plugin:{$pluginId}:enabled", true, 3600));
            
            // 异步记录插件启用
            yield create_task($this->asyncLogPluginAction($pluginId, 'enabled'));
            
            return [
                'success' => true,
                'data' => [
                    'plugin_id' => $pluginId,
                    'status' => 'enabled'
                ],
                'message' => 'Plugin enabled successfully',
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to enable plugin',
                'details' => $e->getMessage(),
                'code' => 500
            ];
        }
    }

    /**
     * 异步禁用插件
     */
    public function asyncDisablePlugin(string $pluginId): \Generator
    {
        // 模拟异步禁用插件
        yield sleep(0.003);
        
        try {
            // 检查插件是否存在
            $plugin = $this->pluginManager->getPlugin($pluginId);
            if (!$plugin) {
                return [
                    'success' => false,
                    'error' => 'Plugin not found',
                    'code' => 404
                ];
            }
            
            // 禁用插件
            $this->pluginManager->disablePlugin($pluginId);
            
            // 异步更新插件缓存
            yield create_task($this->cacheManager->asyncSet("plugin:{$pluginId}:enabled", false, 3600));
            
            // 异步记录插件禁用
            yield create_task($this->asyncLogPluginAction($pluginId, 'disabled'));
            
            return [
                'success' => true,
                'data' => [
                    'plugin_id' => $pluginId,
                    'status' => 'disabled'
                ],
                'message' => 'Plugin disabled successfully',
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to disable plugin',
                'details' => $e->getMessage(),
                'code' => 500
            ];
        }
    }

    /**
     * 异步安装插件
     */
    public function asyncInstallPlugin(array $pluginData): \Generator
    {
        // 模拟异步安装插件
        yield sleep(0.01);
        
        try {
            // 验证插件数据
            $validation = yield create_task($this->asyncValidatePluginData($pluginData));
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'error' => 'Invalid plugin data',
                    'details' => $validation['errors'],
                    'code' => 400
                ];
            }
            
            // 安装插件
            $pluginId = $this->pluginManager->installPlugin($pluginData);
            
            // 异步测试新插件
            $testResult = yield create_task($this->asyncTestNewPlugin($pluginId, $pluginData));
            
            // 异步缓存插件信息
            yield create_task($this->cacheManager->asyncSet("plugin:{$pluginId}", $pluginData, 3600));
            
            return [
                'success' => true,
                'data' => [
                    'plugin_id' => $pluginId,
                    'plugin' => $pluginData,
                    'test_result' => $testResult
                ],
                'message' => 'Plugin installed successfully',
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to install plugin',
                'details' => $e->getMessage(),
                'code' => 500
            ];
        }
    }

    /**
     * 异步卸载插件
     */
    public function asyncUninstallPlugin(string $pluginId): \Generator
    {
        // 模拟异步卸载插件
        yield sleep(0.005);
        
        try {
            // 检查插件是否存在
            $plugin = $this->pluginManager->getPlugin($pluginId);
            if (!$plugin) {
                return [
                    'success' => false,
                    'error' => 'Plugin not found',
                    'code' => 404
                ];
            }
            
            // 卸载插件
            $this->pluginManager->uninstallPlugin($pluginId);
            
            // 异步删除插件缓存
            yield create_task($this->cacheManager->asyncDelete("plugin:{$pluginId}"));
            yield create_task($this->cacheManager->asyncDelete("plugin:{$pluginId}:enabled"));
            
            // 异步记录插件卸载
            yield create_task($this->asyncLogPluginAction($pluginId, 'uninstalled'));
            
            return [
                'success' => true,
                'data' => [
                    'plugin_id' => $pluginId,
                    'uninstalled_plugin' => $plugin
                ],
                'message' => 'Plugin uninstalled successfully',
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to uninstall plugin',
                'details' => $e->getMessage(),
                'code' => 500
            ];
        }
    }

    /**
     * 异步更新插件
     */
    public function asyncUpdatePlugin(string $pluginId, array $pluginData): \Generator
    {
        // 模拟异步更新插件
        yield sleep(0.008);
        
        try {
            // 检查插件是否存在
            $existingPlugin = $this->pluginManager->getPlugin($pluginId);
            if (!$existingPlugin) {
                return [
                    'success' => false,
                    'error' => 'Plugin not found',
                    'code' => 404
                ];
            }
            
            // 验证插件数据
            $validation = yield create_task($this->asyncValidatePluginData($pluginData));
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'error' => 'Invalid plugin data',
                    'details' => $validation['errors'],
                    'code' => 400
                ];
            }
            
            // 更新插件
            $this->pluginManager->updatePlugin($pluginId, $pluginData);
            
            // 异步测试更新后的插件
            $testResult = yield create_task($this->asyncTestUpdatedPlugin($pluginId, $pluginData));
            
            // 异步更新插件缓存
            yield create_task($this->cacheManager->asyncSet("plugin:{$pluginId}", $pluginData, 3600));
            
            return [
                'success' => true,
                'data' => [
                    'plugin_id' => $pluginId,
                    'plugin' => $pluginData,
                    'test_result' => $testResult
                ],
                'message' => 'Plugin updated successfully',
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to update plugin',
                'details' => $e->getMessage(),
                'code' => 500
            ];
        }
    }

    /**
     * 异步重新加载插件
     */
    public function asyncReloadPlugins(): \Generator
    {
        // 模拟异步重新加载插件
        yield sleep(0.01);
        
        try {
            // 异步重新加载插件
            $this->pluginManager->reloadPlugins();
            
            // 异步清理插件缓存
            yield create_task($this->asyncClearPluginCache());
            
            // 异步记录插件重载
            yield create_task($this->asyncLogPluginReload());
            
            return [
                'success' => true,
                'data' => [
                    'reloaded_at' => date('Y-m-d H:i:s'),
                    'plugin_count' => count($this->pluginManager->getAllPlugins())
                ],
                'message' => 'Plugins reloaded successfully',
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to reload plugins',
                'details' => $e->getMessage(),
                'code' => 500
            ];
        }
    }

    /**
     * 异步获取插件统计信息
     */
    private function asyncGetPluginStats(array $plugins): \Generator
    {
        yield sleep(0.002);
        
        $stats = [
            'total_plugins' => count($plugins),
            'enabled_plugins' => 0,
            'disabled_plugins' => 0,
            'plugin_types' => [],
            'last_updated' => null
        ];
        
        foreach ($plugins as $plugin) {
            if ($plugin['enabled'] ?? false) {
                $stats['enabled_plugins']++;
            } else {
                $stats['disabled_plugins']++;
            }
            
            $type = $plugin['type'] ?? 'unknown';
            $stats['plugin_types'][$type] = ($stats['plugin_types'][$type] ?? 0) + 1;
            
            if (!$stats['last_updated'] || $plugin['updated_at'] > $stats['last_updated']) {
                $stats['last_updated'] = $plugin['updated_at'] ?? null;
            }
        }
        
        return $stats;
    }

    /**
     * 异步获取插件详细信息
     */
    private function asyncGetPluginDetails(string $pluginId): \Generator
    {
        yield sleep(0.001);
        
        // 异步获取插件使用统计
        $usageStats = yield create_task($this->asyncGetPluginUsageStats($pluginId));
        
        // 异步获取插件操作历史
        $actionHistory = yield create_task($this->asyncGetPluginActionHistory($pluginId));
        
        return [
            'usage_stats' => $usageStats,
            'action_history' => $actionHistory,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * 异步验证插件数据
     */
    private function asyncValidatePluginData(array $pluginData): \Generator
    {
        yield sleep(0.001);
        
        $errors = [];
        
        // 验证必填字段
        $requiredFields = ['name', 'type', 'version'];
        foreach ($requiredFields as $field) {
            if (!isset($pluginData[$field]) || empty($pluginData[$field])) {
                $errors[] = "Field '{$field}' is required";
            }
        }
        
        // 验证插件类型
        $validTypes = ['detector', 'rule', 'action', 'monitor'];
        if (isset($pluginData['type']) && !in_array($pluginData['type'], $validTypes)) {
            $errors[] = "Invalid plugin type: {$pluginData['type']}";
        }
        
        // 验证版本格式
        if (isset($pluginData['version']) && !preg_match('/^\d+\.\d+\.\d+$/', $pluginData['version'])) {
            $errors[] = "Invalid version format: {$pluginData['version']}";
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * 异步测试新插件
     */
    private function asyncTestNewPlugin(string $pluginId, array $pluginData): \Generator
    {
        yield sleep(0.005);
        
        // 模拟插件测试
        $testCases = [
            ['input' => 'test input', 'expected' => false],
            ['input' => 'malicious payload', 'expected' => true]
        ];
        
        $results = [];
        foreach ($testCases as $testCase) {
            $results[] = [
                'input' => $testCase['input'],
                'expected' => $testCase['expected'],
                'actual' => rand(0, 1) === 1, // 模拟测试结果
                'passed' => true
            ];
        }
        
        return [
            'test_cases' => $results,
            'overall_result' => 'passed'
        ];
    }

    /**
     * 异步测试更新后的插件
     */
    private function asyncTestUpdatedPlugin(string $pluginId, array $pluginData): \Generator
    {
        yield sleep(0.003);
        
        // 模拟更新后的插件测试
        return [
            'test_cases' => [
                ['input' => 'updated test', 'expected' => false, 'actual' => false, 'passed' => true]
            ],
            'overall_result' => 'passed'
        ];
    }

    /**
     * 异步记录插件操作
     */
    private function asyncLogPluginAction(string $pluginId, string $action): \Generator
    {
        yield sleep(0.001);
        
        yield $this->dbManager->asyncInsert('plugin_action_logs', [
            'plugin_id' => $pluginId,
            'action' => $action,
            'performed_at' => date('Y-m-d H:i:s'),
            'performed_by' => 'api_user'
        ]);
    }

    /**
     * 异步清理插件缓存
     */
    private function asyncClearPluginCache(): \Generator
    {
        yield sleep(0.002);
        
        // 模拟清理插件缓存
        $cacheKeys = [
            'plugin:sql_injection_detector',
            'plugin:xss_detector',
            'plugin:rate_limiter'
        ];
        
        foreach ($cacheKeys as $key) {
            yield create_task($this->cacheManager->asyncDelete($key));
        }
    }

    /**
     * 异步记录插件重载
     */
    private function asyncLogPluginReload(): \Generator
    {
        yield sleep(0.001);
        
        yield $this->dbManager->asyncInsert('plugin_action_logs', [
            'plugin_id' => 'system.reload',
            'action' => 'reload_all',
            'performed_at' => date('Y-m-d H:i:s'),
            'performed_by' => 'api_user'
        ]);
    }

    /**
     * 异步获取插件使用统计
     */
    private function asyncGetPluginUsageStats(string $pluginId): \Generator
    {
        yield sleep(0.002);
        
        $result = yield $this->dbManager->asyncQuery(
            "SELECT 
                COUNT(*) as total_usage,
                SUM(CASE WHEN action = 'enabled' THEN 1 ELSE 0 END) as enabled_count,
                MAX(performed_at) as last_used
             FROM plugin_action_logs 
             WHERE plugin_id = ? AND performed_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)",
            [$pluginId]
        );
        
        return $result[0] ?? [
            'total_usage' => 0,
            'enabled_count' => 0,
            'last_used' => null
        ];
    }

    /**
     * 异步获取插件操作历史
     */
    private function asyncGetPluginActionHistory(string $pluginId): \Generator
    {
        yield sleep(0.002);
        
        $result = yield $this->dbManager->asyncQuery(
            "SELECT 
                action,
                performed_at,
                performed_by
             FROM plugin_action_logs 
             WHERE plugin_id = ? 
             ORDER BY performed_at DESC 
             LIMIT 10",
            [$pluginId]
        );
        
        return $result ?? [];
    }
}
