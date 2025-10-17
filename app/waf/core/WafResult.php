<?php

namespace app\waf\core;

/**
 * WAF 检测结果
 */
class WafResult
{
    private bool $blocked;
    private string $rule;
    private string $message;
    private int $statusCode;
    private array $details;
    private float $responseTime;
    
    public function __construct(
        bool $blocked = false,
        string $rule = '',
        string $message = '',
        int $statusCode = 200,
        array $details = [],
        float $responseTime = 0.0
    ) {
        $this->blocked = $blocked;
        $this->rule = $rule;
        $this->message = $message;
        $this->statusCode = $statusCode;
        $this->details = $details;
        $this->responseTime = $responseTime;
    }
    
    /**
     * 创建放行结果
     */
    public static function allow(): self
    {
        return new self(false, '', 'Request allowed', 200);
    }
    
    /**
     * 创建拦截结果
     */
    public static function block(string $rule, string $message, int $statusCode = 403, array $details = []): self
    {
        return new self(true, $rule, $message, $statusCode, $details);
    }
    
    /**
     * 是否被拦截
     */
    public function isBlocked(): bool
    {
        return $this->blocked;
    }
    
    /**
     * 获取规则名称
     */
    public function getRule(): string
    {
        return $this->rule;
    }
    
    /**
     * 获取消息
     */
    public function getMessage(): string
    {
        return $this->message;
    }
    
    /**
     * 获取状态码
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
    
    /**
     * 获取详细信息
     */
    public function getDetails(): array
    {
        return $this->details;
    }
    
    /**
     * 获取响应时间
     */
    public function getResponseTime(): float
    {
        return $this->responseTime;
    }
    
    /**
     * 设置响应时间
     */
    public function setResponseTime(float $responseTime): void
    {
        $this->responseTime = $responseTime;
    }
    
    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        return [
            'blocked' => $this->blocked,
            'rule' => $this->rule,
            'message' => $this->message,
            'status_code' => $this->statusCode,
            'details' => $this->details,
            'response_time' => $this->responseTime,
        ];
    }
}
