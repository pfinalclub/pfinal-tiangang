<?php

namespace Tiangang\Waf\Core;

use Tiangang\Waf\Config\ConfigManager;

/**
 * 决策引擎
 * 
 * 负责综合多个检测结果，做出最终决策
 */
class DecisionEngine
{
    private ConfigManager $configManager;
    private array $config;
    
    public function __construct()
    {
        $this->configManager = new ConfigManager();
        $this->config = $this->configManager->get('waf');
    }
    
    /**
     * 评估检测结果
     */
    public function evaluate(array $results): WafResult
    {
        $totalScore = 0;
        $matchedRules = [];
        $details = [];
        
        foreach ($results as $result) {
            if ($result['matched'] ?? false) {
                $rule = $result['rule'] ?? 'unknown';
                $severity = $result['severity'] ?? 'medium';
                $weight = $this->getRuleWeight($rule);
                $severityScore = $this->getSeverityScore($severity);
                
                $score = $weight * $severityScore;
                $totalScore += $score;
                $matchedRules[] = $rule;
                $details[] = [
                    'rule' => $rule,
                    'severity' => $severity,
                    'score' => $score,
                    'details' => $result['details'] ?? [],
                ];
            }
        }
        
        $threshold = $this->config['decision']['threshold'] ?? 100;
        $isBlocked = $totalScore >= $threshold;
        
        if ($isBlocked) {
            $primaryRule = $this->getPrimaryRule($details);
            $message = $this->generateBlockMessage($matchedRules, $totalScore);
            
            return WafResult::block(
                $primaryRule,
                $message,
                403,
                [
                    'total_score' => $totalScore,
                    'threshold' => $threshold,
                    'matched_rules' => $matchedRules,
                    'details' => $details,
                ]
            );
        }
        
        return WafResult::allow();
    }
    
    /**
     * 获取规则权重
     */
    private function getRuleWeight(string $rule): int
    {
        $weights = $this->config['rules']['priority'] ?? [];
        return $weights[$rule] ?? 50;
    }
    
    /**
     * 获取严重程度分数
     */
    private function getSeverityScore(string $severity): int
    {
        $weights = $this->config['decision']['weights'] ?? [];
        return $weights[$severity] ?? 50;
    }
    
    /**
     * 获取主要规则
     */
    private function getPrimaryRule(array $details): string
    {
        if (empty($details)) {
            return 'unknown';
        }
        
        // 按分数排序，返回分数最高的规则
        usort($details, function ($a, $b) {
            return $b['score'] <=> $a['score'];
        });
        
        return $details[0]['rule'];
    }
    
    /**
     * 生成拦截消息
     */
    private function generateBlockMessage(array $rules, int $score): string
    {
        if (count($rules) === 1) {
            return "Request blocked by rule: {$rules[0]}";
        }
        
        return sprintf(
            'Request blocked by multiple rules: %s (score: %d)',
            implode(', ', $rules),
            $score
        );
    }
}
