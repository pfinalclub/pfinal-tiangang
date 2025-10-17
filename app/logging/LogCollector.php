<?php

namespace Tiangang\Waf\Logging;

use Workerman\Protocols\Http\Request;
use Tiangang\Waf\Core\WafResult;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RedisHandler;
use Predis\Client as RedisClient;

/**
 * 日志收集器
 * 
 * 负责收集和记录 WAF 相关日志
 */
class LogCollector
{
    private Logger $logger;
    private RedisClient $redis;
    private array $config;
    
    public function __construct()
    {
        $this->config = config('waf.logging');
        $this->logger = $this->createLogger();
        $this->redis = $this->createRedisClient();
    }
    
    /**
     * 记录请求日志
     */
    public function log(Request $request, WafResult $result, float $responseTime): void
    {
        if (!$this->config['enabled']) {
            return;
        }
        
        $logData = [
            'timestamp' => time(),
            'ip' => $this->getRealIp($request),
            'uri' => $request->path(),
            'method' => $request->method(),
            'user_agent' => $request->header('User-Agent', ''),
            'referer' => $request->header('Referer', ''),
            'blocked' => $result->isBlocked(),
            'rule' => $result->getRule(),
            'message' => $result->getMessage(),
            'status_code' => $result->getStatusCode(),
            'response_time' => $responseTime,
            'details' => $result->getDetails(),
        ];
        
        // 记录到文件
        if (in_array('file', $this->config['channels'])) {
            $this->logToFile($logData);
        }
        
        // 记录到 Redis
        if (in_array('redis', $this->config['channels'])) {
            $this->logToRedis($logData);
        }
    }
    
    /**
     * 记录错误日志
     */
    public function logError(Request $request, \Exception $e): void
    {
        $logData = [
            'timestamp' => time(),
            'ip' => $this->getRealIp($request),
            'uri' => $request->path(),
            'method' => $request->method(),
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ];
        
        $this->logger->error('WAF Error', $logData);
    }
    
    /**
     * 记录到文件
     */
    private function logToFile(array $logData): void
    {
        $level = $logData['blocked'] ? 'warning' : 'info';
        $message = $logData['blocked'] ? 'Request blocked' : 'Request allowed';
        
        $this->logger->log($level, $message, $logData);
    }
    
    /**
     * 记录到 Redis
     */
    private function logToRedis(array $logData): void
    {
        try {
            $key = 'waf:logs:' . date('Y-m-d-H');
            $this->redis->lpush($key, json_encode($logData));
            $this->redis->expire($key, 86400 * 7); // 保留 7 天
        } catch (\Exception $e) {
            error_log("Failed to log to Redis: " . $e->getMessage());
        }
    }
    
    /**
     * 创建日志记录器
     */
    private function createLogger(): Logger
    {
        $logger = new Logger('tiangang-waf');
        
        // 文件处理器
        $fileHandler = new StreamHandler(
            __DIR__ . '/../../runtime/logs/waf.log',
            Logger::INFO
        );
        $logger->pushHandler($fileHandler);
        
        return $logger;
    }
    
    /**
     * 创建 Redis 客户端
     */
    private function createRedisClient(): RedisClient
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
     * 获取真实 IP
     */
    private function getRealIp(Request $request): string
    {
        $headers = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR'
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $request->connection->getRemoteIp();
    }
}
