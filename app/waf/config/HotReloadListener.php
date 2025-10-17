<?php

namespace Tiangang\Waf\Config;

use Predis\Client as RedisClient;
use Tiangang\Waf\Plugins\PluginManager;

/**
 * 热更新监听器
 * 
 * 监听配置文件和规则变化，实现热更新
 */
class HotReloadListener
{
    private RuleConfigManager $ruleConfigManager;
    private PluginManager $pluginManager;
    private RedisClient $redis;
    private array $watchedFiles = [];
    private bool $isRunning = false;
    
    public function __construct()
    {
        $this->ruleConfigManager = new RuleConfigManager();
        $this->pluginManager = new PluginManager();
        $this->redis = $this->getRedisClient();
        $this->initWatchedFiles();
    }
    
    /**
     * 启动热更新监听
     */
    public function start(): void
    {
        if ($this->isRunning) {
            return;
        }
        
        $this->isRunning = true;
        logger('info', 'Hot reload listener started');
        
        // 启动文件监听
        $this->startFileWatcher();
        
        // 启动 Redis 订阅
        $this->startRedisSubscriber();
    }
    
    /**
     * 停止热更新监听
     */
    public function stop(): void
    {
        $this->isRunning = false;
        logger('info', 'Hot reload listener stopped');
    }
    
    /**
     * 启动文件监听器
     */
    private function startFileWatcher(): void
    {
        $lastCheck = time();
        
        while ($this->isRunning) {
            $currentTime = time();
            
            // 每5秒检查一次文件变化
            if ($currentTime - $lastCheck >= 5) {
                $this->checkFileChanges();
                $lastCheck = $currentTime;
            }
            
            usleep(100000); // 100ms
        }
    }
    
    /**
     * 启动 Redis 订阅
     */
    private function startRedisSubscriber(): void
    {
        try {
            $pubsub = $this->redis->pubSubLoop();
            $pubsub->subscribe(['rule_updates', 'config_updates']);
            
            foreach ($pubsub as $message) {
                if ($message->kind === 'message') {
                    $this->handleRedisMessage($message->channel, $message->payload);
                }
            }
        } catch (\Exception $e) {
            logger('error', 'Redis subscriber error', [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * 检查文件变化
     */
    private function checkFileChanges(): void
    {
        $changes = [];
        
        // 检查规则文件变化
        $ruleUpdates = $this->ruleConfigManager->checkRuleUpdates();
        if (!empty($ruleUpdates)) {
            $changes['rules'] = $ruleUpdates;
        }
        
        // 检查配置文件变化
        $configChanges = $this->checkConfigChanges();
        if (!empty($configChanges)) {
            $changes['config'] = $configChanges;
        }
        
        // 检查插件文件变化
        $pluginChanges = $this->checkPluginChanges();
        if (!empty($pluginChanges)) {
            $changes['plugins'] = $pluginChanges;
        }
        
        if (!empty($changes)) {
            $this->handleFileChanges($changes);
        }
    }
    
    /**
     * 检查配置文件变化
     */
    private function checkConfigChanges(): array
    {
        $changes = [];
        $configFiles = [
            'config/waf.php',
            'config/database.php',
            '.env'
        ];
        
        foreach ($configFiles as $file) {
            $filePath = __DIR__ . "/../../" . $file;
            if (file_exists($filePath)) {
                $mtime = filemtime($filePath);
                $cachedMtime = $this->getCachedFileMtime($file);
                
                if ($mtime > $cachedMtime) {
                    $changes[] = [
                        'file' => $file,
                        'mtime' => $mtime,
                        'cached_mtime' => $cachedMtime
                    ];
                    $this->setCachedFileMtime($file, $mtime);
                }
            }
        }
        
        return $changes;
    }
    
    /**
     * 检查插件文件变化
     */
    private function checkPluginChanges(): array
    {
        $changes = [];
        $pluginPath = plugin_path('waf');
        
        if (is_dir($pluginPath)) {
            $pluginFiles = glob($pluginPath . '/*.php');
            
            foreach ($pluginFiles as $file) {
                $mtime = filemtime($file);
                $cachedMtime = $this->getCachedFileMtime($file);
                
                if ($mtime > $cachedMtime) {
                    $changes[] = [
                        'file' => basename($file),
                        'mtime' => $mtime,
                        'cached_mtime' => $cachedMtime
                    ];
                    $this->setCachedFileMtime($file, $mtime);
                }
            }
        }
        
        return $changes;
    }
    
    /**
     * 处理文件变化
     */
    private function handleFileChanges(array $changes): void
    {
        logger('info', 'File changes detected', $changes);
        
        // 处理规则变化
        if (isset($changes['rules'])) {
            $reloaded = $this->ruleConfigManager->hotReloadRules();
            if (!empty($reloaded)) {
                logger('info', 'Rules hot reloaded', ['rules' => $reloaded]);
            }
        }
        
        // 处理配置变化
        if (isset($changes['config'])) {
            $this->handleConfigChanges($changes['config']);
        }
        
        // 处理插件变化
        if (isset($changes['plugins'])) {
            $this->handlePluginChanges($changes['plugins']);
        }
    }
    
    /**
     * 处理配置变化
     */
    private function handleConfigChanges(array $changes): void
    {
        foreach ($changes as $change) {
            $file = $change['file'];
            
            if (str_ends_with($file, '.php')) {
                // PHP 配置文件，需要重启服务
                logger('info', "PHP config file changed: {$file}, restart required");
                $this->notifyRestartRequired($file);
            } elseif ($file === '.env') {
                // 环境变量文件，需要重新加载
                logger('info', "Environment file changed: {$file}, reload required");
                $this->notifyConfigReload($file);
            }
        }
    }
    
    /**
     * 处理插件变化
     */
    private function handlePluginChanges(array $changes): void
    {
        foreach ($changes as $change) {
            $file = $change['file'];
            logger('info', "Plugin file changed: {$file}");
            
            // 重新加载插件
            $this->pluginManager->reload();
            
            // 通知插件更新
            $this->notifyPluginUpdate($file);
        }
    }
    
    /**
     * 处理 Redis 消息
     */
    private function handleRedisMessage(string $channel, string $payload): void
    {
        try {
            $data = json_decode($payload, true);
            
            switch ($channel) {
                case 'rule_updates':
                    $this->handleRuleUpdate($data);
                    break;
                case 'config_updates':
                    $this->handleConfigUpdate($data);
                    break;
            }
        } catch (\Exception $e) {
            logger('error', 'Failed to handle Redis message', [
                'channel' => $channel,
                'payload' => $payload,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * 处理规则更新
     */
    private function handleRuleUpdate(array $data): void
    {
        $rule = $data['rule'] ?? '';
        $action = $data['action'] ?? '';
        
        logger('info', "Rule update received: {$rule} ({$action})");
        
        // 清除相关缓存
        $this->clearRuleCache($rule);
    }
    
    /**
     * 处理配置更新
     */
    private function handleConfigUpdate(array $data): void
    {
        $config = $data['config'] ?? '';
        $action = $data['action'] ?? '';
        
        logger('info', "Config update received: {$config} ({$action})");
        
        // 清除配置缓存
        $this->clearConfigCache();
    }
    
    /**
     * 初始化监听文件
     */
    private function initWatchedFiles(): void
    {
        $this->watchedFiles = [
            'config/waf.php',
            'config/database.php',
            '.env'
        ];
    }
    
    /**
     * 获取缓存的文件修改时间
     */
    private function getCachedFileMtime(string $file): int
    {
        try {
            $key = "file_mtime:" . md5($file);
            return (int) $this->redis->get($key);
        } catch (\Exception $e) {
            return 0;
        }
    }
    
    /**
     * 设置缓存的文件修改时间
     */
    private function setCachedFileMtime(string $file, int $mtime): void
    {
        try {
            $key = "file_mtime:" . md5($file);
            $this->redis->setex($key, 3600, $mtime);
        } catch (\Exception $e) {
            // 忽略缓存错误
        }
    }
    
    /**
     * 清除规则缓存
     */
    private function clearRuleCache(string $ruleName): void
    {
        try {
            $this->redis->del("rule_config:{$ruleName}");
        } catch (\Exception $e) {
            logger('warning', "Failed to clear rule cache: {$ruleName}", [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * 清除配置缓存
     */
    private function clearConfigCache(): void
    {
        try {
            $this->redis->del('config_cache');
        } catch (\Exception $e) {
            logger('warning', 'Failed to clear config cache', [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * 通知需要重启
     */
    private function notifyRestartRequired(string $file): void
    {
        try {
            $this->redis->publish('system_events', json_encode([
                'type' => 'restart_required',
                'file' => $file,
                'timestamp' => time()
            ]));
        } catch (\Exception $e) {
            logger('warning', 'Failed to notify restart required', [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * 通知配置重新加载
     */
    private function notifyConfigReload(string $file): void
    {
        try {
            $this->redis->publish('system_events', json_encode([
                'type' => 'config_reload',
                'file' => $file,
                'timestamp' => time()
            ]));
        } catch (\Exception $e) {
            logger('warning', 'Failed to notify config reload', [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * 通知插件更新
     */
    private function notifyPluginUpdate(string $file): void
    {
        try {
            $this->redis->publish('system_events', json_encode([
                'type' => 'plugin_update',
                'file' => $file,
                'timestamp' => time()
            ]));
        } catch (\Exception $e) {
            logger('warning', 'Failed to notify plugin update', [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * 获取 Redis 客户端
     */
    private function getRedisClient(): RedisClient
    {
        return new RedisClient([
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'port' => env('REDIS_PORT', 6379),
            'password' => env('REDIS_PASSWORD', ''),
            'database' => env('REDIS_DATABASE', 0),
        ]);
    }
}
