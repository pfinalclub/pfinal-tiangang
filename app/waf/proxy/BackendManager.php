<?php

namespace app\waf\proxy;

use app\waf\config\ConfigManager;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\RequestException;
use Predis\Client as RedisClient;

/**
 * 后端管理器
 * 
 * 负责后端服务的健康检查、负载均衡和故障转移
 */
class BackendManager
{
    private ConfigManager $configManager;
    private HttpClient $httpClient;
    private RedisClient $redis;
    private ?array $config;
    private array $backends = [];
    private array $healthStatus = [];
    
    public function __construct()
    {
        $this->configManager = new ConfigManager();
        $this->config = $this->configManager->get('proxy') ?? [];
        $this->httpClient = $this->createHttpClient();
        $this->redis = $this->getRedisClient();
        $this->loadBackends();
    }
    
    /**
     * 获取可用的后端
     */
    public function getAvailableBackend(): ?array
    {
        $availableBackends = array_filter($this->backends, function ($backend) {
            return $this->isBackendHealthy($backend);
        });
        
        if (empty($availableBackends)) {
            return null;
        }
        
        // 负载均衡策略
        return $this->selectBackend($availableBackends);
    }
    
    /**
     * 健康检查
     */
    public function healthCheck(): array
    {
        $results = [];
        
        foreach ($this->backends as $backend) {
            $startTime = microtime(true);
            $healthy = $this->checkBackendHealth($backend);
            $responseTime = microtime(true) - $startTime;
            
            $results[] = [
                'backend' => $backend['name'],
                'url' => $backend['url'],
                'healthy' => $healthy,
                'response_time' => round($responseTime * 1000, 2),
                'timestamp' => time()
            ];
            
            // 更新健康状态
            $this->updateHealthStatus($backend['name'], $healthy, $responseTime);
        }
        
        return $results;
    }
    
    /**
     * 检查单个后端健康状态
     */
    private function checkBackendHealth(array $backend): bool
    {
        try {
            $healthUrl = $backend['health_url'] ?? $backend['url'] . '/health';
            $timeout = $backend['health_timeout'] ?? 5;
            
            $response = $this->httpClient->get($healthUrl, [
                'timeout' => $timeout,
                'http_errors' => false
            ]);
            
            $statusCode = $response->getStatusCode();
            $isHealthy = $statusCode >= 200 && $statusCode < 400;
            
            // 检查响应内容
            if ($isHealthy && isset($backend['health_check'])) {
                $body = $response->getBody()->getContents();
                $isHealthy = $this->validateHealthResponse($body, $backend['health_check']);
            }
            
            return $isHealthy;
            
        } catch (RequestException $e) {
            logger('warning', 'Backend health check failed', [
                'backend' => $backend['name'],
                'url' => $backend['url'],
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * 验证健康检查响应
     */
    private function validateHealthResponse(string $body, array $check): bool
    {
        if (isset($check['expected_status'])) {
            $status = json_decode($body, true);
            if (!$status || $status['status'] !== $check['expected_status']) {
                return false;
            }
        }
        
        if (isset($check['expected_content'])) {
            if (strpos($body, $check['expected_content']) === false) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * 选择后端（负载均衡）
     */
    private function selectBackend(array $backends): array
    {
        $strategy = $this->config['load_balancer']['strategy'] ?? 'round_robin';
        
        switch ($strategy) {
            case 'round_robin':
                return $this->roundRobinSelection($backends);
            case 'least_connections':
                return $this->leastConnectionsSelection($backends);
            case 'weighted':
                return $this->weightedSelection($backends);
            case 'ip_hash':
                return $this->ipHashSelection($backends);
            default:
                return $this->roundRobinSelection($backends);
        }
    }
    
    /**
     * 轮询选择
     */
    private function roundRobinSelection(array $backends): array
    {
        try {
            $key = 'proxy:round_robin:index';
            $index = $this->redis->incr($key) % count($backends);
            $this->redis->expire($key, 3600);
            
            return array_values($backends)[$index];
        } catch (\Exception $e) {
            // Redis 不可用时，使用简单的轮询
            static $index = 0;
            $index = ($index + 1) % count($backends);
            return array_values($backends)[$index];
        }
    }
    
    /**
     * 最少连接选择
     */
    private function leastConnectionsSelection(array $backends): array
    {
        $minConnections = PHP_INT_MAX;
        $selectedBackend = null;
        
        foreach ($backends as $backend) {
            $connections = $this->getBackendConnections($backend['name']);
            if ($connections < $minConnections) {
                $minConnections = $connections;
                $selectedBackend = $backend;
            }
        }
        
        return $selectedBackend;
    }
    
    /**
     * 权重选择
     */
    private function weightedSelection(array $backends): array
    {
        $totalWeight = array_sum(array_column($backends, 'weight'));
        $random = mt_rand(1, $totalWeight);
        $currentWeight = 0;
        
        foreach ($backends as $backend) {
            $currentWeight += $backend['weight'] ?? 1;
            if ($random <= $currentWeight) {
                return $backend;
            }
        }
        
        return $backends[0];
    }
    
    /**
     * IP 哈希选择
     */
    private function ipHashSelection(array $backends): array
    {
        $clientIp = $this->getClientIp();
        $hash = crc32($clientIp);
        $index = abs($hash) % count($backends);
        
        return array_values($backends)[$index];
    }
    
    /**
     * 检查后端是否健康
     */
    private function isBackendHealthy(array $backend): bool
    {
        $backendName = $backend['name'];
        $status = $this->healthStatus[$backendName] ?? null;
        
        if (!$status) {
            return true; // 未知状态，假设健康
        }
        
        // 检查是否在故障恢复期内
        $lastFailure = $status['last_failure'] ?? 0;
        $recoveryTime = $backend['recovery_time'] ?? 60;
        
        if ($lastFailure > 0 && (time() - $lastFailure) < $recoveryTime) {
            return false;
        }
        
        return $status['healthy'] ?? true;
    }
    
    /**
     * 更新健康状态
     */
    private function updateHealthStatus(string $backendName, bool $healthy, float $responseTime): void
    {
        $this->healthStatus[$backendName] = [
            'healthy' => $healthy,
            'last_check' => time(),
            'response_time' => $responseTime,
            'last_failure' => $healthy ? 0 : time()
        ];
        
        // 缓存到 Redis
        try {
            $this->redis->setex(
                "backend:health:{$backendName}",
                300, // 5分钟过期
                json_encode($this->healthStatus[$backendName])
            );
        } catch (\Exception $e) {
            // Redis 不可用时，忽略
        }
    }
    
    /**
     * 获取后端连接数
     */
    private function getBackendConnections(string $backendName): int
    {
        try {
            $key = "backend:connections:{$backendName}";
            return (int) $this->redis->get($key);
        } catch (\Exception $e) {
            // Redis 不可用时，返回 0
            return 0;
        }
    }
    
    /**
     * 增加后端连接数
     */
    public function incrementConnections(string $backendName): void
    {
        try {
            $key = "backend:connections:{$backendName}";
            $this->redis->incr($key);
            $this->redis->expire($key, 3600);
        } catch (\Exception $e) {
            // Redis 不可用时，忽略
        }
    }
    
    /**
     * 减少后端连接数
     */
    public function decrementConnections(string $backendName): void
    {
        try {
            $key = "backend:connections:{$backendName}";
            $this->redis->decr($key);
        } catch (\Exception $e) {
            // Redis 不可用时，忽略
        }
    }
    
    /**
     * 获取后端统计信息
     */
    public function getBackendStats(): array
    {
        $stats = [
            'total_backends' => count($this->backends),
            'healthy_backends' => 0,
            'unhealthy_backends' => 0,
            'backends' => []
        ];
        
        foreach ($this->backends as $backend) {
            $healthy = $this->isBackendHealthy($backend);
            $stats['healthy_backends'] += $healthy ? 1 : 0;
            $stats['unhealthy_backends'] += $healthy ? 0 : 1;
            
            $stats['backends'][] = [
                'name' => $backend['name'],
                'url' => $backend['url'],
                'healthy' => $healthy,
                'connections' => $this->getBackendConnections($backend['name']),
                'weight' => $backend['weight'] ?? 1
            ];
        }
        
        return $stats;
    }
    
    /**
     * 加载后端配置
     */
    private function loadBackends(): void
    {
        $this->backends = $this->config['backends'] ?? [
            [
                'name' => 'default',
                'url' => 'http://localhost:8080',
                'weight' => 1,
                'health_url' => 'http://localhost:8080/health',
                'health_timeout' => 5,
                'recovery_time' => 60
            ]
        ];
    }
    
    /**
     * 获取客户端 IP
     */
    private function getClientIp(): string
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
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return '127.0.0.1';
    }
    
    /**
     * 创建 HTTP 客户端
     */
    private function createHttpClient(): HttpClient
    {
        return new HttpClient([
            'timeout' => 10,
            'connect_timeout' => 5,
            'verify' => false
        ]);
    }
    
    /**
     * 获取 Redis 客户端
     */
    private function getRedisClient(): RedisClient
    {
        $config = $this->configManager->get('database.redis') ?? [];
        return new RedisClient([
            'host' => $config['host'] ?? '127.0.0.1',
            'port' => $config['port'] ?? 6379,
            'password' => $config['password'] ?? '',
            'database' => $config['database'] ?? 0,
        ]);
    }
}
