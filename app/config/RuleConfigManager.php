<?php

namespace Tiangang\Waf\Config;

use Symfony\Component\Yaml\Yaml;
use Predis\Client as RedisClient;

/**
 * 规则配置管理器
 * 
 * 负责规则配置的加载、缓存、热更新和版本管理
 */
class RuleConfigManager
{
    private ConfigManager $configManager;
    private RedisClient $redis;
    private string $rulesPath;
    private array $ruleCache = [];
    private array $ruleVersions = [];
    
    public function __construct()
    {
        $this->configManager = new ConfigManager();
        $this->redis = $this->getRedisClient();
        $this->rulesPath = $this->configManager->get('waf.rules_path', __DIR__ . '/../../config/rules');
        $this->loadRuleVersions();
    }
    
    /**
     * 获取规则配置
     */
    public function getRuleConfig(string $ruleName): ?array
    {
        // 先从缓存获取
        if (isset($this->ruleCache[$ruleName])) {
            return $this->ruleCache[$ruleName];
        }
        
        // 从文件加载
        $config = $this->loadRuleFromFile($ruleName);
        if ($config) {
            $this->ruleCache[$ruleName] = $config;
            $this->cacheRuleToRedis($ruleName, $config);
        }
        
        return $config;
    }
    
    /**
     * 获取所有规则配置
     */
    public function getAllRuleConfigs(): array
    {
        $rules = [];
        $ruleFiles = glob($this->rulesPath . '/*.yaml');
        
        foreach ($ruleFiles as $file) {
            $ruleName = pathinfo($file, PATHINFO_FILENAME);
            $rules[$ruleName] = $this->getRuleConfig($ruleName);
        }
        
        return $rules;
    }
    
    /**
     * 更新规则配置
     */
    public function updateRuleConfig(string $ruleName, array $config): bool
    {
        try {
            // 保存到文件
            $filePath = $this->rulesPath . '/' . $ruleName . '.yaml';
            $yaml = Yaml::dump($config, 4, 2);
            file_put_contents($filePath, $yaml);
            
            // 更新缓存
            $this->ruleCache[$ruleName] = $config;
            $this->cacheRuleToRedis($ruleName, $config);
            
            // 更新版本
            $this->updateRuleVersion($ruleName);
            
            // 通知热更新
            $this->notifyRuleUpdate($ruleName);
            
            return true;
        } catch (\Exception $e) {
            logger('error', "Failed to update rule config: {$ruleName}", [
                'error' => $e->getMessage(),
                'rule' => $ruleName
            ]);
            return false;
        }
    }
    
    /**
     * 启用/禁用规则
     */
    public function toggleRule(string $ruleName, bool $enabled): bool
    {
        $config = $this->getRuleConfig($ruleName);
        if (!$config) {
            return false;
        }
        
        $config['enabled'] = $enabled;
        return $this->updateRuleConfig($ruleName, $config);
    }
    
    /**
     * 检查规则是否有更新
     */
    public function checkRuleUpdates(): array
    {
        $updates = [];
        $ruleFiles = glob($this->rulesPath . '/*.yaml');
        
        foreach ($ruleFiles as $file) {
            $ruleName = pathinfo($file, PATHINFO_FILENAME);
            $fileMtime = filemtime($file);
            $cachedMtime = $this->ruleVersions[$ruleName] ?? 0;
            
            if ($fileMtime > $cachedMtime) {
                $updates[] = [
                    'rule' => $ruleName,
                    'file_mtime' => $fileMtime,
                    'cached_mtime' => $cachedMtime,
                    'needs_reload' => true
                ];
            }
        }
        
        return $updates;
    }
    
    /**
     * 热更新规则
     */
    public function hotReloadRules(): array
    {
        $reloaded = [];
        $updates = $this->checkRuleUpdates();
        
        foreach ($updates as $update) {
            if ($update['needs_reload']) {
                $ruleName = $update['rule'];
                
                // 清除缓存
                unset($this->ruleCache[$ruleName]);
                $this->redis->del("rule_config:{$ruleName}");
                
                // 重新加载
                $config = $this->getRuleConfig($ruleName);
                if ($config) {
                    $reloaded[] = $ruleName;
                    logger('info', "Rule hot reloaded: {$ruleName}");
                }
            }
        }
        
        return $reloaded;
    }
    
    /**
     * 获取规则统计信息
     */
    public function getRuleStats(): array
    {
        $stats = [
            'total_rules' => 0,
            'enabled_rules' => 0,
            'disabled_rules' => 0,
            'rules' => []
        ];
        
        $allConfigs = $this->getAllRuleConfigs();
        $stats['total_rules'] = count($allConfigs);
        
        foreach ($allConfigs as $ruleName => $config) {
            if ($config) {
                $enabled = $config['enabled'] ?? false;
                $stats['enabled_rules'] += $enabled ? 1 : 0;
                $stats['disabled_rules'] += $enabled ? 0 : 1;
                
                $stats['rules'][] = [
                    'name' => $ruleName,
                    'enabled' => $enabled,
                    'priority' => $config['priority'] ?? 50,
                    'version' => $this->ruleVersions[$ruleName] ?? 0,
                    'last_modified' => $this->ruleVersions[$ruleName] ?? 0
                ];
            }
        }
        
        return $stats;
    }
    
    /**
     * 从文件加载规则
     */
    private function loadRuleFromFile(string $ruleName): ?array
    {
        $filePath = $this->rulesPath . '/' . $ruleName . '.yaml';
        
        if (!file_exists($filePath)) {
            return null;
        }
        
        try {
            $config = Yaml::parseFile($filePath);
            return $config ?: null;
        } catch (\Exception $e) {
            logger('error', "Failed to parse rule file: {$filePath}", [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * 缓存规则到 Redis
     */
    private function cacheRuleToRedis(string $ruleName, array $config): void
    {
        try {
            $key = "rule_config:{$ruleName}";
            $this->redis->setex($key, 3600, json_encode($config)); // 1小时过期
        } catch (\Exception $e) {
            logger('warning', "Failed to cache rule to Redis: {$ruleName}", [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * 从 Redis 加载规则
     */
    private function loadRuleFromRedis(string $ruleName): ?array
    {
        try {
            $key = "rule_config:{$ruleName}";
            $data = $this->redis->get($key);
            return $data ? json_decode($data, true) : null;
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * 加载规则版本信息
     */
    private function loadRuleVersions(): void
    {
        try {
            $versions = $this->redis->hgetall('rule_versions');
            $this->ruleVersions = $versions;
        } catch (\Exception $e) {
            $this->ruleVersions = [];
        }
    }
    
    /**
     * 更新规则版本
     */
    private function updateRuleVersion(string $ruleName): void
    {
        $version = time();
        $this->ruleVersions[$ruleName] = $version;
        
        try {
            $this->redis->hset('rule_versions', $ruleName, $version);
        } catch (\Exception $e) {
            logger('warning', "Failed to update rule version: {$ruleName}", [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * 通知规则更新
     */
    private function notifyRuleUpdate(string $ruleName): void
    {
        try {
            $this->redis->publish('rule_updates', json_encode([
                'rule' => $ruleName,
                'timestamp' => time(),
                'action' => 'updated'
            ]));
        } catch (\Exception $e) {
            logger('warning', "Failed to notify rule update: {$ruleName}", [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * 获取 Redis 客户端
     */
    private function getRedisClient(): RedisClient
    {
        $config = $this->configManager->get('database.redis');
        return new RedisClient([
            'host' => $config['host'],
            'port' => $config['port'],
            'password' => $config['password'],
            'database' => $config['database'],
        ]);
    }
}
