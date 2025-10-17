<?php

namespace app\waf\cache;

use Predis\Client as RedisClient;
use PfinalClub\Asyncio\{create_task, gather, wait_for, sleep};
use app\waf\config\ConfigManager;

/**
 * 异步缓存管理器
 * 
 * 负责异步缓存操作，包括读取、写入、删除等
 */
class AsyncCacheManager
{
    private ?RedisClient $redis;
    private ConfigManager $configManager;
    private ?array $config;
    private array $localCache = [];
    private int $localCacheSize = 1000;
    private int $localCacheTtl = 300; // 5分钟

    public function __construct()
    {
        $this->configManager = new ConfigManager();
        $this->config = $this->configManager->get('cache') ?? [
            'enabled' => true,
            'driver' => 'redis',
            'ttl' => 3600,
            'prefix' => 'waf_cache:',
            'local_cache' => [
                'enabled' => true,
                'size' => 1000,
                'ttl' => 300
            ]
        ];
        
        try {
            $redisConfig = $this->configManager->get('database.redis');
            $this->redis = new RedisClient([
                'host' => $redisConfig['host'] ?? '127.0.0.1',
                'port' => $redisConfig['port'] ?? 6379,
                'password' => $redisConfig['password'] ?? '',
                'database' => $redisConfig['database'] ?? 0,
            ]);
            $this->redis->connect();
        } catch (\Exception $e) {
            logger('warning', 'Redis connection failed for AsyncCacheManager, using local cache only.', ['error' => $e->getMessage()]);
            $this->redis = null;
        }
    }

    /**
     * 异步获取缓存
     */
    public function asyncGet(string $key, mixed $default = null): \Generator
    {
        // 模拟异步缓存读取
        yield sleep(0.001);
        
        // 先检查本地缓存
        if (isset($this->localCache[$key])) {
            $cached = $this->localCache[$key];
            if ($cached['expires'] > time()) {
                return $cached['value'];
            } else {
                unset($this->localCache[$key]);
            }
        }
        
        // 检查 Redis 缓存
        if ($this->redis) {
            try {
                $value = $this->redis->get($key);
                if ($value !== null) {
                    $decoded = json_decode($value, true);
                    if ($decoded !== null) {
                        // 存储到本地缓存
                        $this->localCache[$key] = [
                            'value' => $decoded,
                            'expires' => time() + $this->localCacheTtl
                        ];
                        return $decoded;
                    }
                }
            } catch (\Exception $e) {
                logger('warning', 'Redis get failed', ['key' => $key, 'error' => $e->getMessage()]);
            }
        }
        
        return $default;
    }

    /**
     * 异步设置缓存
     */
    public function asyncSet(string $key, mixed $value, int $ttl = 3600): \Generator
    {
        // 模拟异步缓存写入
        yield sleep(0.002);
        
        // 存储到本地缓存
        $this->localCache[$key] = [
            'value' => $value,
            'expires' => time() + min($ttl, $this->localCacheTtl)
        ];
        
        // 清理本地缓存大小
        if (count($this->localCache) > $this->localCacheSize) {
            $this->cleanupLocalCache();
        }
        
        // 存储到 Redis
        if ($this->redis) {
            try {
                $serialized = json_encode($value);
                $this->redis->setex($key, $ttl, $serialized);
            } catch (\Exception $e) {
                logger('warning', 'Redis set failed', ['key' => $key, 'error' => $e->getMessage()]);
            }
        }
    }

    /**
     * 异步删除缓存
     */
    public function asyncDelete(string $key): \Generator
    {
        // 模拟异步缓存删除
        yield sleep(0.001);
        
        // 从本地缓存删除
        unset($this->localCache[$key]);
        
        // 从 Redis 删除
        if ($this->redis) {
            try {
                $this->redis->del($key);
            } catch (\Exception $e) {
                logger('warning', 'Redis delete failed', ['key' => $key, 'error' => $e->getMessage()]);
            }
        }
    }

    /**
     * 异步批量获取缓存
     */
    public function asyncGetMultiple(array $keys): \Generator
    {
        // 模拟异步批量缓存读取
        yield sleep(0.003);
        
        $results = [];
        $missingKeys = [];
        
        // 先检查本地缓存
        foreach ($keys as $key) {
            if (isset($this->localCache[$key])) {
                $cached = $this->localCache[$key];
                if ($cached['expires'] > time()) {
                    $results[$key] = $cached['value'];
                } else {
                    unset($this->localCache[$key]);
                    $missingKeys[] = $key;
                }
            } else {
                $missingKeys[] = $key;
            }
        }
        
        // 从 Redis 获取缺失的键
        if ($this->redis && !empty($missingKeys)) {
            try {
                $redisValues = $this->redis->mget($missingKeys);
                foreach ($missingKeys as $index => $key) {
                    if ($redisValues[$index] !== null) {
                        $decoded = json_decode($redisValues[$index], true);
                        if ($decoded !== null) {
                            $results[$key] = $decoded;
                            // 存储到本地缓存
                            $this->localCache[$key] = [
                                'value' => $decoded,
                                'expires' => time() + $this->localCacheTtl
                            ];
                        }
                    }
                }
            } catch (\Exception $e) {
                logger('warning', 'Redis mget failed', ['keys' => $missingKeys, 'error' => $e->getMessage()]);
            }
        }
        
        return $results;
    }

    /**
     * 异步批量设置缓存
     */
    public function asyncSetMultiple(array $data, int $ttl = 3600): \Generator
    {
        // 模拟异步批量缓存写入
        yield sleep(0.005);
        
        // 存储到本地缓存
        foreach ($data as $key => $value) {
            $this->localCache[$key] = [
                'value' => $value,
                'expires' => time() + min($ttl, $this->localCacheTtl)
            ];
        }
        
        // 存储到 Redis
        if ($this->redis) {
            try {
                $pipeline = $this->redis->pipeline();
                foreach ($data as $key => $value) {
                    $serialized = json_encode($value);
                    $pipeline->setex($key, $ttl, $serialized);
                }
                $pipeline->execute();
            } catch (\Exception $e) {
                logger('warning', 'Redis mset failed', ['error' => $e->getMessage()]);
            }
        }
    }

    /**
     * 异步检查缓存是否存在
     */
    public function asyncExists(string $key): \Generator
    {
        // 模拟异步缓存存在检查
        yield sleep(0.001);
        
        // 检查本地缓存
        if (isset($this->localCache[$key])) {
            $cached = $this->localCache[$key];
            if ($cached['expires'] > time()) {
                return true;
            } else {
                unset($this->localCache[$key]);
            }
        }
        
        // 检查 Redis
        if ($this->redis) {
            try {
                return $this->redis->exists($key) > 0;
            } catch (\Exception $e) {
                logger('warning', 'Redis exists failed', ['key' => $key, 'error' => $e->getMessage()]);
            }
        }
        
        return false;
    }

    /**
     * 异步增加计数器
     */
    public function asyncIncrement(string $key, int $value = 1, int $ttl = 3600): \Generator
    {
        // 模拟异步计数器增加
        yield sleep(0.001);
        
        if ($this->redis) {
            try {
                $result = $this->redis->incrby($key, $value);
                $this->redis->expire($key, $ttl);
                return $result;
            } catch (\Exception $e) {
                logger('warning', 'Redis increment failed', ['key' => $key, 'error' => $e->getMessage()]);
            }
        }
        
        return 0;
    }

    /**
     * 异步设置过期时间
     */
    public function asyncExpire(string $key, int $ttl): \Generator
    {
        // 模拟异步过期时间设置
        yield sleep(0.001);
        
        if ($this->redis) {
            try {
                return $this->redis->expire($key, $ttl);
            } catch (\Exception $e) {
                logger('warning', 'Redis expire failed', ['key' => $key, 'error' => $e->getMessage()]);
            }
        }
        
        return false;
    }

    /**
     * 异步清理过期缓存
     */
    public function asyncCleanup(): \Generator
    {
        // 模拟异步缓存清理
        yield sleep(0.01);
        
        $cleaned = 0;
        $currentTime = time();
        
        // 清理本地缓存
        foreach ($this->localCache as $key => $cached) {
            if ($cached['expires'] <= $currentTime) {
                unset($this->localCache[$key]);
                $cleaned++;
            }
        }
        
        return [
            'local_cache_cleaned' => $cleaned,
            'local_cache_size' => count($this->localCache),
            'redis_available' => $this->redis !== null
        ];
    }

    /**
     * 异步获取缓存统计信息
     */
    public function asyncGetStats(): \Generator
    {
        // 模拟异步缓存统计
        yield sleep(0.002);
        
        $stats = [
            'local_cache_size' => count($this->localCache),
            'local_cache_ttl' => $this->localCacheTtl,
            'redis_available' => $this->redis !== null,
        ];
        
        if ($this->redis) {
            try {
                $info = $this->redis->info();
                $stats['redis_memory_used'] = $info['used_memory_human'] ?? 'unknown';
                $stats['redis_connected_clients'] = $info['connected_clients'] ?? 'unknown';
            } catch (\Exception $e) {
                $stats['redis_error'] = $e->getMessage();
            }
        }
        
        return $stats;
    }

    /**
     * 清理本地缓存
     */
    private function cleanupLocalCache(): void
    {
        if (count($this->localCache) <= $this->localCacheSize) {
            return;
        }
        
        // 按过期时间排序，删除最旧的
        uasort($this->localCache, fn($a, $b) => $a['expires'] <=> $b['expires']);
        
        $toRemove = count($this->localCache) - $this->localCacheSize;
        $keys = array_slice(array_keys($this->localCache), 0, $toRemove);
        
        foreach ($keys as $key) {
            unset($this->localCache[$key]);
        }
    }
}
