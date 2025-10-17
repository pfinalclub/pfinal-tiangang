<?php

require_once __DIR__ . '/../Unit/AsyncTestFramework.php';
require_once __DIR__ . '/../../app/logging/AsyncLogger.php';

use Tiangang\Waf\Logging\AsyncLogger;
use PfinalClub\Asyncio\{create_task, gather, wait_for, sleep, run};

/**
 * 异步日志记录器单元测试
 */
class AsyncLoggerTest extends AsyncTestFramework
{
    private AsyncLogger $logger;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->logger = new AsyncLogger();
    }
    
    /**
     * 测试日志记录
     */
    public function testLog(): \Generator
    {
        $result = yield $this->logger->log('info', 'Test log message', ['user_id' => 123]);
        
        $this->assertTrue($result);
    }
    
    /**
     * 测试不同日志级别
     */
    public function testLogLevels(): \Generator
    {
        $levels = ['debug', 'info', 'warning', 'error', 'critical'];
        
        foreach ($levels as $level) {
            $result = yield $this->logger->log($level, "Test {$level} message");
            $this->assertTrue($result);
        }
    }
    
    /**
     * 测试日志上下文
     */
    public function testLogContext(): \Generator
    {
        $context = [
            'user_id' => 123,
            'ip' => '192.168.1.100',
            'action' => 'login',
            'timestamp' => time()
        ];
        
        $result = yield $this->logger->log('info', 'User action', $context);
        
        $this->assertTrue($result);
    }
    
    /**
     * 测试批量日志记录
     */
    public function testBatchLogging(): \Generator
    {
        $logs = [
            ['level' => 'info', 'message' => 'Log 1', 'context' => []],
            ['level' => 'warning', 'message' => 'Log 2', 'context' => []],
            ['level' => 'error', 'message' => 'Log 3', 'context' => []]
        ];
        
        $tasks = [];
        foreach ($logs as $log) {
            $tasks[] = create_task($this->logger->log($log['level'], $log['message'], $log['context']));
        }
        
        $results = yield gather(...$tasks);
        
        $this->assertCount(3, $results);
        $this->assertTrue($results[0]);
        $this->assertTrue($results[1]);
        $this->assertTrue($results[2]);
    }
    
    /**
     * 测试异步日志刷新
     */
    public function testAsyncFlush(): \Generator
    {
        // 记录一些日志
        yield $this->logger->log('info', 'Test message 1');
        yield $this->logger->log('warning', 'Test message 2');
        
        // 异步刷新
        $result = yield $this->logger->asyncFlush();
        
        $this->assertTrue($result);
    }
    
    /**
     * 测试日志性能
     */
    public function testLogPerformance(): \Generator
    {
        $startTime = microtime(true);
        
        $tasks = [];
        for ($i = 0; $i < 100; $i++) {
            $tasks[] = create_task($this->logger->log('info', "Performance test {$i}"));
        }
        
        $results = yield gather(...$tasks);
        
        $endTime = microtime(true);
        $duration = $endTime - $startTime;
        
        $this->assertLessThan(1.0, $duration, '日志记录应该很快');
        $this->assertCount(100, $results);
    }
    
    /**
     * 测试日志错误处理
     */
    public function testLogErrorHandling(): \Generator
    {
        // 测试无效日志级别
        $result = yield $this->logger->log('invalid_level', 'Test message');
        $this->assertTrue($result); // 应该降级为默认级别
        
        // 测试空消息
        $result = yield $this->logger->log('info', '');
        $this->assertTrue($result);
    }
    
    /**
     * 测试日志格式化
     */
    public function testLogFormatting(): \Generator
    {
        $message = 'User {user} performed {action}';
        $context = ['user' => 'admin', 'action' => 'login'];
        
        $result = yield $this->logger->log('info', $message, $context);
        
        $this->assertTrue($result);
    }
    
    /**
     * 测试日志轮转
     */
    public function testLogRotation(): \Generator
    {
        // 记录大量日志
        for ($i = 0; $i < 1000; $i++) {
            yield $this->logger->log('info', "Rotation test {$i}");
        }
        
        // 触发日志轮转
        $result = yield $this->logger->asyncFlush();
        
        $this->assertTrue($result);
    }
    
    /**
     * 测试日志过滤
     */
    public function testLogFiltering(): \Generator
    {
        $sensitiveData = [
            'password' => 'secret123',
            'token' => 'abc123',
            'credit_card' => '4111-1111-1111-1111'
        ];
        
        $result = yield $this->logger->log('info', 'Sensitive data', $sensitiveData);
        
        $this->assertTrue($result);
    }
}
