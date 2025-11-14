<?php

namespace app\waf\plugins;

use app\waf\plugins\WafPluginInterface;

/**
 * SQL 注入检测规则插件
 */
class SqlInjectionRule implements WafPluginInterface
{
    private array $patterns;
    private array $config;
    
    public function __construct()
    {
        $this->patterns = [
            '/(union\s+select)/i' => [
                'description' => 'UNION SELECT 攻击',
                'severity' => 'high'
            ],
            '/(select\s+.*\s+from)/i' => [
                'description' => 'SELECT 查询攻击',
                'severity' => 'high'
            ],
            '/(insert\s+into)/i' => [
                'description' => 'INSERT 注入攻击',
                'severity' => 'high'
            ],
            '/(delete\s+from)/i' => [
                'description' => 'DELETE 注入攻击',
                'severity' => 'high'
            ],
            '/(drop\s+table)/i' => [
                'description' => 'DROP TABLE 攻击',
                'severity' => 'critical'
            ],
            '/(sleep\s*\()/i' => [
                'description' => 'SLEEP 时间盲注',
                'severity' => 'high'
            ],
            '/(benchmark\s*\()/i' => [
                'description' => 'BENCHMARK 时间盲注',
                'severity' => 'high'
            ],
            '/(or\s+1\s*=\s*1)/i' => [
                'description' => 'OR 1=1 逻辑攻击',
                'severity' => 'high'
            ],
            '/(and\s+1\s*=\s*1)/i' => [
                'description' => 'AND 1=1 逻辑攻击',
                'severity' => 'medium'
            ],
        ];
        
        $this->config = [
            'enabled' => true,
            'priority' => 80,
            'fields' => ['query', 'post', 'headers', 'cookies'],
            'whitelist' => ['headers.user-agent', 'headers.accept', 'headers.accept-language']
        ];
    }
    
    public function getName(): string
    {
        return 'sql_injection';
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
    
    public function detect(array $requestData): array
    {
        $results = [];
        $content = $this->extractContent($requestData);
        
        foreach ($this->patterns as $pattern => $info) {
            if (preg_match($pattern, $content)) {
                $results[] = [
                    'matched' => true,
                    'rule' => $this->getName(),
                    'severity' => $info['severity'],
                    'description' => $info['description'],
                    'pattern' => $pattern,
                    'details' => [
                        'matched_content' => $this->getMatchedContent($pattern, $content),
                        'field' => $this->getMatchedField($pattern, $requestData),
                    ]
                ];
            }
        }
        
        if (empty($results)) {
            return ['matched' => false];
        }
        
        // 返回最严重的匹配结果
        usort($results, function ($a, $b) {
            $severityOrder = ['critical' => 4, 'high' => 3, 'medium' => 2, 'low' => 1];
            return $severityOrder[$b['severity']] <=> $severityOrder[$a['severity']];
        });
        
        return $results[0];
    }
    
    public function getDescription(): string
    {
        return 'SQL 注入攻击检测规则';
    }
    
    public function getConfig(): array
    {
        return $this->config;
    }
    
    public function supportsQuickDetection(): bool
    {
        // SQL注入检测适合快速检测（正则匹配）
        return true;
    }
    
    public function requiresLicense(): bool
    {
        // 基础SQL注入检测免费
        return false;
    }
    
    /**
     * 提取检测内容
     */
    private function extractContent(array $requestData): string
    {
        $content = [];
        
        foreach ($this->config['fields'] as $field) {
            if (isset($requestData[$field])) {
                if (is_array($requestData[$field])) {
                    $content[] = json_encode($requestData[$field]);
                } else {
                    $content[] = $requestData[$field];
                }
            }
        }
        
        return implode(' ', $content);
    }
    
    /**
     * 获取匹配的内容
     */
    private function getMatchedContent(string $pattern, string $content): string
    {
        if (preg_match($pattern, $content, $matches)) {
            return $matches[0] ?? '';
        }
        return '';
    }
    
    /**
     * 获取匹配的字段
     */
    private function getMatchedField(string $pattern, array $requestData): string
    {
        foreach ($this->config['fields'] as $field) {
            if (isset($requestData[$field])) {
                $fieldContent = is_array($requestData[$field]) 
                    ? json_encode($requestData[$field]) 
                    : $requestData[$field];
                
                if (preg_match($pattern, $fieldContent)) {
                    return $field;
                }
            }
        }
        return 'unknown';
    }
}
