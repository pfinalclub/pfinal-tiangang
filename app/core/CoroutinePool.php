<?php

namespace Tiangang\Waf\Core;

use PfinalClub\Asyncio\{create_task, sleep};

/**
 * 协程池管理器
 * 
 * 用于管理异步任务的并发执行，避免资源耗尽
 */
class CoroutinePool
{
    private array $pool = [];
    private int $maxSize;
    private int $currentSize = 0;
    private array $waitingQueue = [];
    
    public function __construct(int $maxSize = 100)
    {
        $this->maxSize = $maxSize;
    }
    
    /**
     * 执行异步任务
     */
    public function execute(\Generator $generator): \Generator
    {
        // 如果池已满，等待空闲协程
        if ($this->currentSize >= $this->maxSize) {
            yield $this->waitForAvailable();
        }
        
        // 创建协程任务
        $task = new CoroutineTask($generator);
        $this->pool[] = $task;
        $this->currentSize++;
        
        try {
            // 执行协程
            $result = yield $task->run();
            
            // 从池中移除
            $this->removeFromPool($task);
            
            return $result;
            
        } catch (\Exception $e) {
            // 发生错误也要移除
            $this->removeFromPool($task);
            throw $e;
        }
    }
    
    /**
     * 等待可用协程
     */
    private function waitForAvailable(): \Generator
    {
        while ($this->currentSize >= $this->maxSize) {
            yield sleep(0.001);
        }
    }
    
    /**
     * 从池中移除协程
     */
    private function removeFromPool(CoroutineTask $task): void
    {
        $this->pool = array_filter($this->pool, function($t) use ($task) {
            return $t !== $task;
        });
        $this->currentSize--;
    }
    
    /**
     * 获取池状态
     */
    public function getStatus(): array
    {
        return [
            'current_size' => $this->currentSize,
            'max_size' => $this->maxSize,
            'utilization' => $this->currentSize / $this->maxSize,
            'waiting_count' => count($this->waitingQueue)
        ];
    }
}

/**
 * 协程任务包装器
 */
class CoroutineTask
{
    private \Generator $generator;
    private bool $completed = false;
    private mixed $result = null;
    private ?\Exception $exception = null;
    
    public function __construct(\Generator $generator)
    {
        $this->generator = $generator;
    }
    
    /**
     * 运行协程
     */
    public function run(): \Generator
    {
        try {
            $this->result = yield $this->generator;
            $this->completed = true;
            return $this->result;
        } catch (\Exception $e) {
            $this->exception = $e;
            $this->completed = true;
            throw $e;
        }
    }
    
    /**
     * 检查是否完成
     */
    public function isCompleted(): bool
    {
        return $this->completed;
    }
    
    /**
     * 获取结果
     */
    public function getResult(): mixed
    {
        return $this->result;
    }
    
    /**
     * 获取异常
     */
    public function getException(): ?\Exception
    {
        return $this->exception;
    }
}
