<?php

namespace Tiangang\Waf\Database;

use PDO;
use PDOStatement;
use PfinalClub\Asyncio\{create_task, gather, wait_for, sleep};
use Tiangang\Waf\Config\ConfigManager;

/**
 * 异步数据库管理器
 * 
 * 负责异步数据库操作，包括查询、插入、更新等
 */
class AsyncDatabaseManager
{
    private PDO $pdo;
    private ConfigManager $configManager;
    private array $config;
    private array $connectionPool = [];
    private int $maxConnections = 10;
    private int $currentConnections = 0;

    public function __construct()
    {
        $this->configManager = new ConfigManager();
        $this->config = $this->configManager->get('database');
        $this->initializeConnection();
    }

    /**
     * 初始化数据库连接
     */
    private function initializeConnection(): void
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $this->config['host'] ?? 'localhost',
            $this->config['port'] ?? 3306,
            $this->config['database'] ?? 'waf'
        );

        $this->pdo = new PDO($dsn, $this->config['username'] ?? 'root', $this->config['password'] ?? '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_PERSISTENT => true,
        ]);
    }

    /**
     * 异步查询
     */
    public function asyncQuery(string $sql, array $params = []): \Generator
    {
        // 模拟异步数据库查询
        yield sleep(0.01);
        
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (\PDOException $e) {
            throw new \Exception("Database query failed: " . $e->getMessage());
        }
    }

    /**
     * 异步插入
     */
    public function asyncInsert(string $table, array $data): \Generator
    {
        // 模拟异步数据库插入
        yield sleep(0.005);
        
        $columns = implode(',', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($data);
            return $this->pdo->lastInsertId();
        } catch (\PDOException $e) {
            throw new \Exception("Database insert failed: " . $e->getMessage());
        }
    }

    /**
     * 异步更新
     */
    public function asyncUpdate(string $table, array $data, array $where): \Generator
    {
        // 模拟异步数据库更新
        yield sleep(0.008);
        
        $setClause = [];
        foreach (array_keys($data) as $key) {
            $setClause[] = "{$key} = :{$key}";
        }
        
        $whereClause = [];
        foreach (array_keys($where) as $key) {
            $whereClause[] = "{$key} = :where_{$key}";
        }
        
        $sql = "UPDATE {$table} SET " . implode(', ', $setClause) . " WHERE " . implode(' AND ', $whereClause);
        
        $params = array_merge($data, array_combine(
            array_map(fn($k) => "where_{$k}", array_keys($where)),
            array_values($where)
        ));
        
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (\PDOException $e) {
            throw new \Exception("Database update failed: " . $e->getMessage());
        }
    }

    /**
     * 异步删除
     */
    public function asyncDelete(string $table, array $where): \Generator
    {
        // 模拟异步数据库删除
        yield sleep(0.003);
        
        $whereClause = [];
        foreach (array_keys($where) as $key) {
            $whereClause[] = "{$key} = :{$key}";
        }
        
        $sql = "DELETE FROM {$table} WHERE " . implode(' AND ', $whereClause);
        
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($where);
            return $stmt->rowCount();
        } catch (\PDOException $e) {
            throw new \Exception("Database delete failed: " . $e->getMessage());
        }
    }

    /**
     * 异步批量插入
     */
    public function asyncBatchInsert(string $table, array $dataList): \Generator
    {
        // 模拟异步批量插入
        yield sleep(0.02);
        
        if (empty($dataList)) {
            return 0;
        }
        
        $columns = implode(',', array_keys($dataList[0]));
        $placeholders = '(' . implode(',', array_fill(0, count($dataList[0]), '?')) . ')';
        $values = array_fill(0, count($dataList), $placeholders);
        $sql = "INSERT INTO {$table} ({$columns}) VALUES " . implode(',', $values);
        
        $params = [];
        foreach ($dataList as $data) {
            $params = array_merge($params, array_values($data));
        }
        
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (\PDOException $e) {
            throw new \Exception("Database batch insert failed: " . $e->getMessage());
        }
    }

    /**
     * 异步事务处理
     */
    public function asyncTransaction(callable $callback): \Generator
    {
        // 模拟异步事务处理
        yield sleep(0.005);
        
        try {
            $this->pdo->beginTransaction();
            $result = yield $callback($this);
            $this->pdo->commit();
            return $result;
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * 异步记录日志
     */
    public function asyncLogRequest(array $requestData, array $responseData, float $duration): \Generator
    {
        // 模拟异步日志记录
        yield sleep(0.003);
        
        $logData = [
            'ip' => $requestData['ip'] ?? '',
            'uri' => $requestData['uri'] ?? '',
            'method' => $requestData['method'] ?? '',
            'user_agent' => $requestData['user_agent'] ?? '',
            'status_code' => $responseData['status_code'] ?? 200,
            'blocked' => $responseData['blocked'] ?? false,
            'rule' => $responseData['rule'] ?? null,
            'duration' => $duration,
            'created_at' => date('Y-m-d H:i:s'),
        ];
        
        return yield $this->asyncInsert('waf_logs', $logData);
    }

    /**
     * 异步记录安全事件
     */
    public function asyncLogSecurityEvent(string $event, array $data): \Generator
    {
        // 模拟异步安全事件记录
        yield sleep(0.002);
        
        $eventData = [
            'event_type' => $event,
            'ip' => $data['ip'] ?? '',
            'uri' => $data['uri'] ?? '',
            'rule' => $data['rule'] ?? '',
            'severity' => $data['severity'] ?? 'medium',
            'description' => $data['description'] ?? '',
            'payload' => json_encode($data['payload'] ?? []),
            'created_at' => date('Y-m-d H:i:s'),
        ];
        
        return yield $this->asyncInsert('security_events', $eventData);
    }

    /**
     * 异步获取统计信息
     */
    public function asyncGetStats(string $period = '1h'): \Generator
    {
        // 模拟异步统计查询
        yield sleep(0.01);
        
        $timeCondition = match($period) {
            '1h' => 'created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)',
            '1d' => 'created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)',
            '1w' => 'created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)',
            default => 'created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)'
        };
        
        $stats = [];
        
        // 总请求数
        $totalRequests = yield $this->asyncQuery(
            "SELECT COUNT(*) as count FROM waf_logs WHERE {$timeCondition}"
        );
        $stats['total_requests'] = $totalRequests[0]['count'] ?? 0;
        
        // 拦截请求数
        $blockedRequests = yield $this->asyncQuery(
            "SELECT COUNT(*) as count FROM waf_logs WHERE blocked = 1 AND {$timeCondition}"
        );
        $stats['blocked_requests'] = $blockedRequests[0]['count'] ?? 0;
        
        // 安全事件数
        $securityEvents = yield $this->asyncQuery(
            "SELECT COUNT(*) as count FROM security_events WHERE {$timeCondition}"
        );
        $stats['security_events'] = $securityEvents[0]['count'] ?? 0;
        
        // 平均响应时间
        $avgResponseTime = yield $this->asyncQuery(
            "SELECT AVG(duration) as avg_duration FROM waf_logs WHERE {$timeCondition}"
        );
        $stats['avg_response_time'] = $avgResponseTime[0]['avg_duration'] ?? 0;
        
        return $stats;
    }

    /**
     * 异步清理过期数据
     */
    public function asyncCleanupExpiredData(int $days = 30): \Generator
    {
        // 模拟异步数据清理
        yield sleep(0.05);
        
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        // 清理过期日志
        $deletedLogs = yield $this->asyncDelete('waf_logs', [
            'created_at' => ['<', $cutoffDate]
        ]);
        
        // 清理过期安全事件
        $deletedEvents = yield $this->asyncDelete('security_events', [
            'created_at' => ['<', $cutoffDate]
        ]);
        
        return [
            'deleted_logs' => $deletedLogs,
            'deleted_events' => $deletedEvents,
            'cutoff_date' => $cutoffDate
        ];
    }

    /**
     * 异步健康检查
     */
    public function asyncHealthCheck(): \Generator
    {
        // 模拟异步健康检查
        yield sleep(0.001);
        
        try {
            $result = yield $this->asyncQuery("SELECT 1 as health_check");
            return [
                'status' => 'healthy',
                'response_time' => microtime(true),
                'connection_count' => $this->currentConnections
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'response_time' => microtime(true)
            ];
        }
    }
}
