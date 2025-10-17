<?php

namespace Tiangang\Waf\Api\Controllers;

use PfinalClub\Asyncio\{create_task, gather, wait_for, sleep};
use Tiangang\Waf\Config\ConfigManager;
use Tiangang\Waf\Cache\AsyncCacheManager;
use Tiangang\Waf\Database\AsyncDatabaseManager;

/**
 * 配置管理 API 控制器
 * 
 * 负责处理配置相关的 REST API 请求
 */
class ConfigController
{
    private ConfigManager $configManager;
    private AsyncCacheManager $cacheManager;
    private AsyncDatabaseManager $dbManager;

    public function __construct()
    {
        $this->configManager = new ConfigManager();
        $this->cacheManager = new AsyncCacheManager();
        $this->dbManager = new AsyncDatabaseManager();
    }

    /**
     * 异步获取所有配置
     */
    public function asyncGetConfigs(): \Generator
    {
        // 模拟异步获取配置
        yield sleep(0.002);
        
        $configs = $this->configManager->all();
        
        // 异步获取配置统计信息
        $stats = yield create_task($this->asyncGetConfigStats($configs));
        
        return [
            'success' => true,
            'data' => [
                'configs' => $configs,
                'stats' => $stats,
                'total_count' => count($configs)
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * 异步获取单个配置
     */
    public function asyncGetConfig(string $configKey): \Generator
    {
        // 模拟异步获取单个配置
        yield sleep(0.001);
        
        $config = $this->configManager->get($configKey);
        
        if ($config === null) {
            return [
                'success' => false,
                'error' => 'Config not found',
                'code' => 404
            ];
        }
        
        // 异步获取配置详细信息
        $details = yield create_task($this->asyncGetConfigDetails($configKey));
        
        return [
            'success' => true,
            'data' => [
                'key' => $configKey,
                'value' => $config,
                'details' => $details
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * 异步更新配置
     */
    public function asyncUpdateConfig(string $configKey, mixed $configValue): \Generator
    {
        // 模拟异步更新配置
        yield sleep(0.003);
        
        try {
            // 验证配置数据
            $validation = yield create_task($this->asyncValidateConfigData($configKey, $configValue));
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'error' => 'Invalid config data',
                    'details' => $validation['errors'],
                    'code' => 400
                ];
            }
            
            // 更新配置
            $this->configManager->set($configKey, $configValue);
            
            // 异步更新缓存
            yield create_task($this->cacheManager->asyncSet("config:{$configKey}", $configValue, 3600));
            
            // 异步记录配置变更
            yield create_task($this->asyncLogConfigChange($configKey, $configValue));
            
            return [
                'success' => true,
                'data' => [
                    'key' => $configKey,
                    'value' => $configValue
                ],
                'message' => 'Config updated successfully',
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to update config',
                'details' => $e->getMessage(),
                'code' => 500
            ];
        }
    }

    /**
     * 异步批量更新配置
     */
    public function asyncBatchUpdateConfigs(array $configs): \Generator
    {
        // 模拟异步批量更新配置
        yield sleep(0.005);
        
        try {
            $results = [];
            $errors = [];
            
            // 并发更新多个配置
            $tasks = [];
            foreach ($configs as $key => $value) {
                $tasks[] = create_task($this->asyncUpdateSingleConfig($key, $value));
            }
            
            $updateResults = yield gather(...$tasks);
            
            foreach ($updateResults as $index => $result) {
                $key = array_keys($configs)[$index];
                if ($result['success']) {
                    $results[$key] = $result['data'];
                } else {
                    $errors[$key] = $result['error'];
                }
            }
            
            return [
                'success' => empty($errors),
                'data' => [
                    'updated_configs' => $results,
                    'errors' => $errors,
                    'total_updated' => count($results),
                    'total_errors' => count($errors)
                ],
                'message' => empty($errors) ? 'All configs updated successfully' : 'Some configs failed to update',
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to batch update configs',
                'details' => $e->getMessage(),
                'code' => 500
            ];
        }
    }

    /**
     * 异步重置配置
     */
    public function asyncResetConfig(string $configKey): \Generator
    {
        // 模拟异步重置配置
        yield sleep(0.002);
        
        try {
            // 获取默认配置
            $defaultValue = yield create_task($this->asyncGetDefaultConfig($configKey));
            
            if ($defaultValue === null) {
                return [
                    'success' => false,
                    'error' => 'Default config not found',
                    'code' => 404
                ];
            }
            
            // 重置配置
            $this->configManager->set($configKey, $defaultValue);
            
            // 异步更新缓存
            yield create_task($this->cacheManager->asyncSet("config:{$configKey}", $defaultValue, 3600));
            
            // 异步记录配置重置
            yield create_task($this->asyncLogConfigReset($configKey, $defaultValue));
            
            return [
                'success' => true,
                'data' => [
                    'key' => $configKey,
                    'value' => $defaultValue
                ],
                'message' => 'Config reset to default successfully',
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to reset config',
                'details' => $e->getMessage(),
                'code' => 500
            ];
        }
    }

    /**
     * 异步重新加载配置
     */
    public function asyncReloadConfigs(): \Generator
    {
        // 模拟异步重新加载配置
        yield sleep(0.01);
        
        try {
            // 异步重新加载配置
            yield $this->configManager->asyncReload();
            
            // 异步清理配置缓存
            yield create_task($this->asyncClearConfigCache());
            
            // 异步记录配置重载
            yield create_task($this->asyncLogConfigReload());
            
            return [
                'success' => true,
                'data' => [
                    'reloaded_at' => date('Y-m-d H:i:s'),
                    'config_count' => count($this->configManager->all())
                ],
                'message' => 'Configs reloaded successfully',
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to reload configs',
                'details' => $e->getMessage(),
                'code' => 500
            ];
        }
    }

    /**
     * 异步获取配置历史
     */
    public function asyncGetConfigHistory(string $configKey, int $limit = 10): \Generator
    {
        // 模拟异步获取配置历史
        yield sleep(0.002);
        
        $result = yield $this->dbManager->asyncQuery(
            "SELECT 
                config_key,
                old_value,
                new_value,
                changed_at,
                changed_by
             FROM config_change_logs 
             WHERE config_key = ? 
             ORDER BY changed_at DESC 
             LIMIT ?",
            [$configKey, $limit]
        );
        
        return [
            'success' => true,
            'data' => [
                'config_key' => $configKey,
                'history' => $result ?? [],
                'total_count' => count($result ?? [])
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * 异步获取配置统计信息
     */
    private function asyncGetConfigStats(array $configs): \Generator
    {
        yield sleep(0.002);
        
        $stats = [
            'total_configs' => count($configs),
            'config_categories' => [],
            'last_updated' => null
        ];
        
        foreach ($configs as $key => $value) {
            $category = explode('.', $key)[0] ?? 'other';
            $stats['config_categories'][$category] = ($stats['config_categories'][$category] ?? 0) + 1;
        }
        
        return $stats;
    }

    /**
     * 异步获取配置详细信息
     */
    private function asyncGetConfigDetails(string $configKey): \Generator
    {
        yield sleep(0.001);
        
        // 异步获取配置历史
        $history = yield create_task($this->asyncGetConfigHistory($configKey, 5));
        
        // 异步获取配置使用统计
        $usageStats = yield create_task($this->asyncGetConfigUsageStats($configKey));
        
        return [
            'history' => $history['data']['history'],
            'usage_stats' => $usageStats,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * 异步验证配置数据
     */
    private function asyncValidateConfigData(string $configKey, mixed $configValue): \Generator
    {
        yield sleep(0.001);
        
        $errors = [];
        
        // 验证配置键格式
        if (empty($configKey) || !is_string($configKey)) {
            $errors[] = 'Config key must be a non-empty string';
        }
        
        // 验证配置值类型
        if (is_null($configValue)) {
            $errors[] = 'Config value cannot be null';
        }
        
        // 验证特定配置的格式
        if (strpos($configKey, 'timeout') !== false && (!is_numeric($configValue) || $configValue < 0)) {
            $errors[] = 'Timeout config must be a positive number';
        }
        
        if (strpos($configKey, 'enabled') !== false && !is_bool($configValue)) {
            $errors[] = 'Enabled config must be a boolean';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * 异步更新单个配置
     */
    private function asyncUpdateSingleConfig(string $key, mixed $value): \Generator
    {
        yield sleep(0.001);
        
        try {
            $this->configManager->set($key, $value);
            yield create_task($this->cacheManager->asyncSet("config:{$key}", $value, 3600));
            
            return [
                'success' => true,
                'data' => ['key' => $key, 'value' => $value]
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 异步获取默认配置
     */
    private function asyncGetDefaultConfig(string $configKey): \Generator
    {
        yield sleep(0.001);
        
        // 模拟获取默认配置
        $defaultConfigs = [
            'waf.enabled' => true,
            'waf.timeout' => 5.0,
            'waf.detection.quick_enabled' => true,
            'waf.detection.async_enabled' => true,
            'proxy.timeout' => 30.0,
            'proxy.retry_count' => 3
        ];
        
        return $defaultConfigs[$configKey] ?? null;
    }

    /**
     * 异步记录配置变更
     */
    private function asyncLogConfigChange(string $configKey, mixed $configValue): \Generator
    {
        yield sleep(0.001);
        
        yield $this->dbManager->asyncInsert('config_change_logs', [
            'config_key' => $configKey,
            'old_value' => json_encode($this->configManager->get($configKey)),
            'new_value' => json_encode($configValue),
            'changed_at' => date('Y-m-d H:i:s'),
            'changed_by' => 'api_user'
        ]);
    }

    /**
     * 异步记录配置重置
     */
    private function asyncLogConfigReset(string $configKey, mixed $defaultValue): \Generator
    {
        yield sleep(0.001);
        
        yield $this->dbManager->asyncInsert('config_change_logs', [
            'config_key' => $configKey,
            'old_value' => json_encode($this->configManager->get($configKey)),
            'new_value' => json_encode($defaultValue),
            'changed_at' => date('Y-m-d H:i:s'),
            'changed_by' => 'api_user',
            'action' => 'reset'
        ]);
    }

    /**
     * 异步清理配置缓存
     */
    private function asyncClearConfigCache(): \Generator
    {
        yield sleep(0.002);
        
        // 模拟清理配置缓存
        $cacheKeys = [
            'config:waf.enabled',
            'config:waf.timeout',
            'config:proxy.timeout'
        ];
        
        foreach ($cacheKeys as $key) {
            yield create_task($this->cacheManager->asyncDelete($key));
        }
    }

    /**
     * 异步记录配置重载
     */
    private function asyncLogConfigReload(): \Generator
    {
        yield sleep(0.001);
        
        yield $this->dbManager->asyncInsert('config_change_logs', [
            'config_key' => 'system.reload',
            'old_value' => null,
            'new_value' => 'reloaded',
            'changed_at' => date('Y-m-d H:i:s'),
            'changed_by' => 'api_user',
            'action' => 'reload'
        ]);
    }

    /**
     * 异步获取配置使用统计
     */
    private function asyncGetConfigUsageStats(string $configKey): \Generator
    {
        yield sleep(0.002);
        
        $result = yield $this->dbManager->asyncQuery(
            "SELECT 
                COUNT(*) as change_count,
                MAX(changed_at) as last_changed
             FROM config_change_logs 
             WHERE config_key = ?",
            [$configKey]
        );
        
        return $result[0] ?? [
            'change_count' => 0,
            'last_changed' => null
        ];
    }
}
