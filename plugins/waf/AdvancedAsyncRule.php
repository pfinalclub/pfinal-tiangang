<?php

namespace Tiangang\Waf\Plugins\Waf;

use Tiangang\Waf\Plugins\WafPluginInterface;
use PfinalClub\Asyncio\{create_task, gather, wait_for, sleep, run};

/**
 * 高级异步检测规则插件
 * 
 * 演示如何使用 pfinal-asyncio 进行复杂的异步检测
 */
class AdvancedAsyncRule implements WafPluginInterface
{
    private array $config;
    
    public function __construct()
    {
        $this->config = [
            'enabled' => true,
            'priority' => 60,
            'timeout' => 5.0, // 5秒超时
            'concurrent_checks' => 3, // 并发检查数量
        ];
    }
    
    public function getName(): string
    {
        return 'advanced_async';
    }
    
    public function getVersion(): string
    {
        return '1.0.0';
    }
    
    public function getPriority(): int
    {
        return $this->config['priority'];
    }
    
    public function isEnabled(): bool
    {
        return $this->config['enabled'];
    }
    
    public function detect(array $requestData): \Generator
    {
        // 使用 pfinal-asyncio 进行异步检测
        return yield $this->asyncDetection($requestData);
    }
    
    /**
     * 异步检测主逻辑
     */
    private function asyncDetection(array $requestData): \Generator
    {
        // 创建多个并发检测任务
        $tasks = [
            create_task($this->checkMaliciousPatterns($requestData)),
            create_task($this->checkBehavioralPatterns($requestData)),
            create_task($this->checkNetworkPatterns($requestData)),
        ];
        
        try {
            // 并发执行所有检测任务，带超时控制
            $results = yield wait_for(gather(...$tasks), $this->config['timeout']);
            
            // 分析结果
            return $this->analyzeResults($results);
            
        } catch (\PfinalClub\Asyncio\TimeoutException $e) {
            // 超时处理
            return [
                'matched' => false,
                'timeout' => true,
                'message' => 'Advanced async detection timeout'
            ];
        }
    }
    
    /**
     * 检测恶意模式
     */
    private function checkMaliciousPatterns(array $requestData): \Generator
    {
        // 模拟异步 I/O 操作（如数据库查询、API 调用等）
        yield sleep(0.1); // 模拟 100ms 的 I/O 操作
        
        $content = json_encode($requestData);
        $patterns = [
            '/(eval\s*\()/i' => 'eval 函数调用',
            '/(system\s*\()/i' => 'system 函数调用',
            '/(exec\s*\()/i' => 'exec 函数调用',
            '/(shell_exec\s*\()/i' => 'shell_exec 函数调用',
        ];
        
        foreach ($patterns as $pattern => $description) {
            if (preg_match($pattern, $content)) {
                return [
                    'type' => 'malicious_patterns',
                    'matched' => true,
                    'pattern' => $pattern,
                    'description' => $description,
                    'severity' => 'high'
                ];
            }
        }
        
        return ['type' => 'malicious_patterns', 'matched' => false];
    }
    
    /**
     * 检测行为模式
     */
    private function checkBehavioralPatterns(array $requestData): \Generator
    {
        // 模拟异步行为分析
        yield sleep(0.2); // 模拟 200ms 的行为分析
        
        $userAgent = $requestData['user_agent'] ?? '';
        $referer = $requestData['referer'] ?? '';
        
        // 检测可疑的 User-Agent
        if (empty($userAgent) || strlen($userAgent) < 10) {
            return [
                'type' => 'behavioral_patterns',
                'matched' => true,
                'reason' => 'suspicious_user_agent',
                'severity' => 'medium'
            ];
        }
        
        // 检测可疑的 Referer
        if (!empty($referer) && $this->isSuspiciousReferer($referer)) {
            return [
                'type' => 'behavioral_patterns',
                'matched' => true,
                'reason' => 'suspicious_referer',
                'severity' => 'medium'
            ];
        }
        
        return ['type' => 'behavioral_patterns', 'matched' => false];
    }
    
    /**
     * 检测网络模式
     */
    private function checkNetworkPatterns(array $requestData): \Generator
    {
        // 模拟异步网络分析
        yield sleep(0.15); // 模拟 150ms 的网络分析
        
        $ip = $requestData['ip'] ?? '';
        $headers = $requestData['headers'] ?? [];
        
        // 检测代理模式
        if ($this->isProxyRequest($headers)) {
            return [
                'type' => 'network_patterns',
                'matched' => true,
                'reason' => 'proxy_detected',
                'severity' => 'low'
            ];
        }
        
        // 检测 Tor 网络
        if ($this->isTorNetwork($ip)) {
            return [
                'type' => 'network_patterns',
                'matched' => true,
                'reason' => 'tor_network',
                'severity' => 'medium'
            ];
        }
        
        return ['type' => 'network_patterns', 'matched' => false];
    }
    
    /**
     * 分析检测结果
     */
    private function analyzeResults(array $results): array
    {
        $matchedResults = array_filter($results, function ($result) {
            return $result['matched'] ?? false;
        });
        
        if (empty($matchedResults)) {
            return ['matched' => false];
        }
        
        // 计算综合风险评分
        $riskScore = 0;
        $reasons = [];
        
        foreach ($matchedResults as $result) {
            $severity = $result['severity'] ?? 'low';
            $score = $this->getSeverityScore($severity);
            $riskScore += $score;
            $reasons[] = $result['reason'] ?? $result['description'] ?? 'unknown';
        }
        
        return [
            'matched' => true,
            'rule' => $this->getName(),
            'severity' => $this->getSeverityFromScore($riskScore),
            'description' => 'Advanced async detection triggered',
            'risk_score' => $riskScore,
            'reasons' => $reasons,
            'details' => $matchedResults
        ];
    }
    
    /**
     * 检查可疑的 Referer
     */
    private function isSuspiciousReferer(string $referer): bool
    {
        $suspiciousPatterns = [
            '/javascript:/i',
            '/data:/i',
            '/vbscript:/i',
        ];
        
        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $referer)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * 检查代理请求
     */
    private function isProxyRequest(array $headers): bool
    {
        $proxyHeaders = [
            'HTTP_VIA',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
        ];
        
        foreach ($proxyHeaders as $header) {
            if (!empty($_SERVER[$header])) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * 检查 Tor 网络
     */
    private function isTorNetwork(string $ip): bool
    {
        // 这里应该调用真实的 Tor 出口节点数据库
        // 这里只是示例
        return false;
    }
    
    /**
     * 获取严重程度分数
     */
    private function getSeverityScore(string $severity): int
    {
        return match($severity) {
            'critical' => 10,
            'high' => 7,
            'medium' => 4,
            'low' => 1,
            default => 0
        };
    }
    
    /**
     * 根据分数获取严重程度
     */
    private function getSeverityFromScore(int $score): string
    {
        if ($score >= 15) return 'critical';
        if ($score >= 10) return 'high';
        if ($score >= 5) return 'medium';
        return 'low';
    }
    
    public function getDescription(): string
    {
        return '高级异步检测规则 - 演示 pfinal-asyncio 的使用';
    }
    
    public function getConfig(): array
    {
        return $this->config;
    }
}
