<?php

namespace app\waf\logging;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RedisHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\JsonFormatter;
use Monolog\Formatter\LineFormatter;
use Predis\Client as RedisClient;
use PfinalClub\Asyncio\{create_task, gather, sleep};

/**
 * 异步日志系统
 * 
 * 基于 pfinal-asyncio 的高性能异步日志记录
 */
class AsyncLogger
{
    private Logger $logger;
    private RedisClient $redis;
    private ?array $config;
    private array $logQueue = [];
    private bool $isRunning = false;
    
    public function __construct()
    {
        $this->config = config('waf.logging', []);
        $this->logger = $this->createLogger();
        $this->redis = $this->getRedisClient();
    }
    
    /**
     * 异步记录日志
     */
    public function log(string $level, string $message, array $context = []): \Generator
    {
        if (!$this->config['enabled'] ?? true) {
            return;
        }
        
        $logEntry = [
            'timestamp' => microtime(true),
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true)
        ];
        
        // 异步写入日志
        yield $this->asyncWriteLog($logEntry);
    }
    
    /**
     * 异步写入日志
     */
    private function asyncWriteLog(array $logEntry): \Generator
    {
        // 并发写入多个目标
        yield \PfinalClub\Asyncio\gather([
            $this->writeToFile($logEntry),
            $this->writeToRedis($logEntry),
            $this->writeToDatabase($logEntry)
        ]);
    }

    /**
     * 异步写入文件
     */
    private function writeToFile(array $logEntry): \Generator
    {
        yield \PfinalClub\Asyncio\sleep(0.001); // 模拟异步文件写入
        $this->logger->log($logEntry['level'], $logEntry['message'], $logEntry['context']);
    }

    /**
     * 异步写入Redis
     */
    private function writeToRedis(array $logEntry): \Generator
    {
        yield \PfinalClub\Asyncio\sleep(0.001); // 模拟异步Redis写入
        $this->redis->lpush('waf_logs', json_encode($logEntry));
    }

    /**
     * 异步写入数据库
     */
    private function writeToDatabase(array $logEntry): \Generator
    {
        yield \PfinalClub\Asyncio\sleep(0.001); // 模拟异步数据库写入
        // 这里可以添加数据库写入逻辑
    }

    /**
     * 记录请求日志
     */
    public function logRequest(array $requestData, array $responseData, float $duration): \Generator
    {
        yield $this->log('info', 'Request processed', [
            'request' => $requestData,
            'response' => $responseData,
            'duration' => $duration,
            'type' => 'request'
        ]);
    }
    
    /**
     * 记录安全事件
     */
    public function logSecurityEvent(string $event, array $data): void
    {
        $this->log('warning', 'Security event detected', [
            'event' => $event,
            'data' => $data,
            'type' => 'security'
        ]);
    }
    
    /**
     * 记录性能指标
     */
    public function logPerformance(string $metric, float $value, array $tags = []): void
    {
        $this->log('info', 'Performance metric', [
            'metric' => $metric,
            'value' => $value,
            'tags' => $tags,
            'type' => 'performance'
        ]);
    }
    
    /**
     * 记录错误
     */
    public function logError(\Throwable $exception, array $context = []): void
    {
        $this->log('error', 'Exception occurred', [
            'exception' => [
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString()
            ],
            'context' => $context,
            'type' => 'error'
        ]);
    }
    
    /**
     * 异步刷新日志
     */
    private function asyncFlush(): \Generator
    {
        if ($this->isFlushing || empty($this->logQueue)) {
            return;
        }
        
        $this->isFlushing = true;
        
        try {
            $logs = $this->logQueue;
            $this->logQueue = [];
            
            // 并发写入多个目标
            $tasks = [];
            
            if (in_array('file', $this->config['channels'] ?? ['file'])) {
                $tasks[] = create_task($this->asyncWriteToFile($logs));
            }
            
            if (in_array('redis', $this->config['channels'] ?? ['file'])) {
                $tasks[] = create_task($this->asyncWriteToRedis($logs));
            }
            
            if (in_array('database', $this->config['channels'] ?? ['file'])) {
                $tasks[] = create_task($this->asyncWriteToDatabase($logs));
            }
            
            if (!empty($tasks)) {
                yield gather(...$tasks);
            }
        } finally {
            $this->isFlushing = false;
        }
    }
    
    /**
     * 批量刷新日志（同步版本，保留兼容性）
     */
    public function flushLogs(): void
    {
        if (empty($this->logQueue)) {
            return;
        }
        
        $logs = $this->logQueue;
        $this->logQueue = [];
        
        // 异步写入日志
        \PfinalClub\Asyncio\run($this->asyncWriteLogs($logs));
    }
    
    /**
     * 异步写入日志
     */
    private function asyncWriteLogs(array $logs): \Generator
    {
        $tasks = [];
        
        // 创建多个写入任务
        foreach ($logs as $log) {
            $tasks[] = create_task($this->writeLogEntry($log));
        }
        
        // 并发执行所有写入任务
        yield gather(...$tasks);
    }
    
    /**
     * 写入单个日志条目
     */
    private function writeLogEntry(array $log): \Generator
    {
        try {
            // 写入文件
            yield create_task($this->writeToFile($log));
            
            // 写入 Redis
            yield create_task($this->writeToRedis($log));
            
            // 写入其他处理器
            yield create_task($this->writeToHandlers($log));
            
        } catch (\Exception $e) {
            // 记录日志写入错误
            error_log("Failed to write log: " . $e->getMessage());
        }
    }
    
    /**
     * 异步写入文件
     */
    private function asyncWriteToFile(array $logs): \Generator
    {
        // 模拟异步文件写入
        yield sleep(0.005);
        
        foreach ($logs as $log) {
            $this->logger->log($log['level'], $log['message'], $log['context']);
        }
    }
    
    /**
     * 异步写入 Redis
     */
    private function asyncWriteToRedis(array $logs): \Generator
    {
        // 模拟异步 Redis 写入
        yield sleep(0.003);
        
        try {
            foreach ($logs as $log) {
                $key = 'waf:logs:' . date('Y-m-d-H');
                $this->redis->lpush($key, json_encode($log));
                $this->redis->expire($key, 86400 * 7); // 保留 7 天
            }
        } catch (\Exception $e) {
            // Redis 写入失败，记录错误但不中断流程
            error_log("Redis log write failed: " . $e->getMessage());
        }
    }
    
    /**
     * 异步写入数据库
     */
    private function asyncWriteToDatabase(array $logs): \Generator
    {
        // 模拟异步数据库写入
        yield sleep(0.008);
        
        // TODO: 实现数据库写入逻辑
        logger('info', 'Database log write', ['count' => count($logs)]);
    }
    
    /**
     * 写入文件（同步版本，保留兼容性）
     */
    private function writeToFile(array $log): \Generator
    {
        if (!in_array('file', $this->config['channels'] ?? ['file'])) {
            return;
        }
        
        // 模拟文件写入延迟
        yield sleep(0.001);
        
        $this->logger->log($log['level'], $log['message'], $log['context']);
    }
    
    /**
     * 写入 Redis
     */
    private function writeToRedis(array $log): \Generator
    {
        if (!in_array('redis', $this->config['channels'] ?? ['file'])) {
            return;
        }
        
        try {
            // 模拟 Redis 写入延迟
            yield sleep(0.002);
            
            $key = 'waf:logs:' . date('Y-m-d-H');
            $this->redis->lpush($key, json_encode($log));
            $this->redis->expire($key, 86400 * 7); // 保留 7 天
            
        } catch (\Exception $e) {
            // Redis 写入失败，记录错误但不中断流程
            error_log("Redis log write failed: " . $e->getMessage());
        }
    }
    
    /**
     * 写入其他处理器
     */
    private function writeToHandlers(array $log): \Generator
    {
        // 模拟其他处理器的写入延迟
        yield sleep(0.001);
        
        // 这里可以添加其他日志处理器，如：
        // - 发送到外部日志服务
        // - 写入数据库
        // - 发送到消息队列
    }
    
    /**
     * 创建日志记录器
     */
    private function createLogger(): Logger
    {
        $logger = new Logger('tiangang-waf');
        
        // 文件处理器
        $fileHandler = new RotatingFileHandler(
            runtime_path('logs/waf.log'),
            30, // 保留 30 天
            Logger::INFO
        );
        $fileHandler->setFormatter(new LineFormatter());
        $logger->pushHandler($fileHandler);
        
        // 错误日志处理器
        $errorHandler = new RotatingFileHandler(
            runtime_path('logs/error.log'),
            30,
            Logger::ERROR
        );
        $errorHandler->setFormatter(new JsonFormatter());
        $logger->pushHandler($errorHandler);
        
        // 安全事件处理器
        $securityHandler = new RotatingFileHandler(
            runtime_path('logs/security.log'),
            90, // 保留 90 天
            Logger::WARNING
        );
        $securityHandler->setFormatter(new JsonFormatter());
        $logger->pushHandler($securityHandler);
        
        return $logger;
    }
    
    /**
     * 获取 Redis 客户端
     */
    private function getRedisClient(): RedisClient
    {
        $config = config('database.redis');
        return new RedisClient([
            'host' => $config['host'],
            'port' => $config['port'],
            'password' => $config['password'],
            'database' => $config['database'],
        ]);
    }
    
    /**
     * 启动日志服务
     */
    public function start(): void
    {
        if ($this->isRunning) {
            return;
        }
        
        $this->isRunning = true;
        
        // 启动定期刷新任务
        \PfinalClub\Asyncio\run($this->periodicFlush());
    }
    
    /**
     * 定期刷新日志
     */
    private function periodicFlush(): \Generator
    {
        while ($this->isRunning) {
            yield sleep(5); // 每 5 秒刷新一次
            
            if (!empty($this->logQueue)) {
                $this->flushLogs();
            }
        }
    }
    
    /**
     * 停止日志服务
     */
    public function stop(): void
    {
        $this->isRunning = false;
        
        // 刷新剩余的日志
        $this->flushLogs();
    }
    
    /**
     * 获取日志统计
     */
    public function getStats(): array
    {
        return [
            'queue_size' => count($this->logQueue),
            'is_running' => $this->isRunning,
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true)
        ];
    }
}
