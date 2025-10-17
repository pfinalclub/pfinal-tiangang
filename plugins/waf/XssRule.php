<?php

namespace Tiangang\Waf\Plugins\Waf;

use Tiangang\Waf\Plugins\WafPluginInterface;

/**
 * XSS 攻击检测规则插件
 */
class XssRule implements WafPluginInterface
{
    private array $patterns;
    private array $config;
    
    public function __construct()
    {
        $this->patterns = [
            '/(<script[^>]*>.*?<\/script>)/is' => [
                'description' => 'Script 标签注入',
                'severity' => 'high'
            ],
            '/(javascript\s*:)/i' => [
                'description' => 'JavaScript 协议',
                'severity' => 'high'
            ],
            '/(on\w+\s*=)/i' => [
                'description' => '事件处理器',
                'severity' => 'high'
            ],
            '/(<iframe[^>]*>)/i' => [
                'description' => 'iframe 标签注入',
                'severity' => 'medium'
            ],
            '/(<object[^>]*>)/i' => [
                'description' => 'object 标签注入',
                'severity' => 'medium'
            ],
            '/(<embed[^>]*>)/i' => [
                'description' => 'embed 标签注入',
                'severity' => 'medium'
            ],
            '/(<link[^>]*>)/i' => [
                'description' => 'link 标签注入',
                'severity' => 'low'
            ],
            '/(<meta[^>]*>)/i' => [
                'description' => 'meta 标签注入',
                'severity' => 'low'
            ],
        ];
        
        $this->config = [
            'enabled' => true,
            'priority' => 70,
            'fields' => ['query', 'post', 'headers', 'cookies'],
            'whitelist' => ['headers.user-agent', 'headers.accept', 'headers.accept-language']
        ];
    }
    
    public function getName(): string
    {
        return 'xss';
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
        return 'XSS 跨站脚本攻击检测规则';
    }
    
    public function getConfig(): array
    {
        return $this->config;
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
