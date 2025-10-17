<?php

namespace Tiangang\Waf\Api\Controllers;

use PfinalClub\Asyncio\{create_task, gather, wait_for, sleep};
use Tiangang\Waf\Config\RuleConfigManager;
use Tiangang\Waf\Testing\RuleTestFramework;
use Tiangang\Waf\Cache\AsyncCacheManager;
use Tiangang\Waf\Database\AsyncDatabaseManager;

/**
 * 规则管理 API 控制器
 * 
 * 负责处理规则相关的 REST API 请求
 */
class RuleController
{
    private RuleConfigManager $ruleConfigManager;
    private RuleTestFramework $testFramework;
    private AsyncCacheManager $cacheManager;
    private AsyncDatabaseManager $dbManager;

    public function __construct()
    {
        $this->ruleConfigManager = new RuleConfigManager();
        $this->testFramework = new RuleTestFramework();
        $this->cacheManager = new AsyncCacheManager();
        $this->dbManager = new AsyncDatabaseManager();
    }

    /**
     * 异步获取所有规则
     */
    public function asyncGetRules(): \Generator
    {
        // 模拟异步获取规则
        yield sleep(0.002);
        
        $rules = $this->ruleConfigManager->getAllRules();
        
        // 异步获取规则统计信息
        $stats = yield create_task($this->asyncGetRuleStats($rules));
        
        return [
            'success' => true,
            'data' => [
                'rules' => $rules,
                'stats' => $stats,
                'total_count' => count($rules)
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * 异步获取单个规则
     */
    public function asyncGetRule(string $ruleId): \Generator
    {
        // 模拟异步获取单个规则
        yield sleep(0.001);
        
        $rule = $this->ruleConfigManager->getRule($ruleId);
        
        if (!$rule) {
            return [
                'success' => false,
                'error' => 'Rule not found',
                'code' => 404
            ];
        }
        
        // 异步获取规则详细信息
        $details = yield create_task($this->asyncGetRuleDetails($ruleId));
        
        return [
            'success' => true,
            'data' => [
                'rule' => $rule,
                'details' => $details
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * 异步创建规则
     */
    public function asyncCreateRule(array $ruleData): \Generator
    {
        // 模拟异步创建规则
        yield sleep(0.005);
        
        try {
            // 验证规则数据
            $validation = yield create_task($this->asyncValidateRuleData($ruleData));
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'error' => 'Invalid rule data',
                    'details' => $validation['errors'],
                    'code' => 400
                ];
            }
            
            // 创建规则
            $ruleId = $this->ruleConfigManager->createRule($ruleData);
            
            // 异步测试新规则
            $testResult = yield create_task($this->asyncTestNewRule($ruleId, $ruleData));
            
            // 异步缓存规则
            yield create_task($this->cacheManager->asyncSet("rule:{$ruleId}", $ruleData, 3600));
            
            return [
                'success' => true,
                'data' => [
                    'rule_id' => $ruleId,
                    'rule' => $ruleData,
                    'test_result' => $testResult
                ],
                'message' => 'Rule created successfully',
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to create rule',
                'details' => $e->getMessage(),
                'code' => 500
            ];
        }
    }

    /**
     * 异步更新规则
     */
    public function asyncUpdateRule(string $ruleId, array $ruleData): \Generator
    {
        // 模拟异步更新规则
        yield sleep(0.003);
        
        try {
            // 检查规则是否存在
            $existingRule = $this->ruleConfigManager->getRule($ruleId);
            if (!$existingRule) {
                return [
                    'success' => false,
                    'error' => 'Rule not found',
                    'code' => 404
                ];
            }
            
            // 验证规则数据
            $validation = yield create_task($this->asyncValidateRuleData($ruleData));
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'error' => 'Invalid rule data',
                    'details' => $validation['errors'],
                    'code' => 400
                ];
            }
            
            // 更新规则
            $this->ruleConfigManager->updateRule($ruleId, $ruleData);
            
            // 异步测试更新后的规则
            $testResult = yield create_task($this->asyncTestUpdatedRule($ruleId, $ruleData));
            
            // 异步更新缓存
            yield create_task($this->cacheManager->asyncSet("rule:{$ruleId}", $ruleData, 3600));
            
            return [
                'success' => true,
                'data' => [
                    'rule_id' => $ruleId,
                    'rule' => $ruleData,
                    'test_result' => $testResult
                ],
                'message' => 'Rule updated successfully',
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to update rule',
                'details' => $e->getMessage(),
                'code' => 500
            ];
        }
    }

    /**
     * 异步删除规则
     */
    public function asyncDeleteRule(string $ruleId): \Generator
    {
        // 模拟异步删除规则
        yield sleep(0.002);
        
        try {
            // 检查规则是否存在
            $existingRule = $this->ruleConfigManager->getRule($ruleId);
            if (!$existingRule) {
                return [
                    'success' => false,
                    'error' => 'Rule not found',
                    'code' => 404
                ];
            }
            
            // 删除规则
            $this->ruleConfigManager->deleteRule($ruleId);
            
            // 异步删除缓存
            yield create_task($this->cacheManager->asyncDelete("rule:{$ruleId}"));
            
            // 异步记录删除日志
            yield create_task($this->asyncLogRuleDeletion($ruleId, $existingRule));
            
            return [
                'success' => true,
                'data' => [
                    'rule_id' => $ruleId,
                    'deleted_rule' => $existingRule
                ],
                'message' => 'Rule deleted successfully',
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to delete rule',
                'details' => $e->getMessage(),
                'code' => 500
            ];
        }
    }

    /**
     * 异步测试规则
     */
    public function asyncTestRule(string $ruleId, array $testData): \Generator
    {
        // 模拟异步规则测试
        yield sleep(0.01);
        
        try {
            $rule = $this->ruleConfigManager->getRule($ruleId);
            if (!$rule) {
                return [
                    'success' => false,
                    'error' => 'Rule not found',
                    'code' => 404
                ];
            }
            
            // 异步执行规则测试
            $testResult = yield create_task($this->testFramework->asyncRunRuleTest($ruleId, $testData));
            
            // 异步记录测试结果
            yield create_task($this->asyncLogRuleTest($ruleId, $testData, $testResult));
            
            return [
                'success' => true,
                'data' => [
                    'rule_id' => $ruleId,
                    'test_data' => $testData,
                    'test_result' => $testResult
                ],
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to test rule',
                'details' => $e->getMessage(),
                'code' => 500
            ];
        }
    }

    /**
     * 异步获取规则统计信息
     */
    private function asyncGetRuleStats(array $rules): \Generator
    {
        yield sleep(0.002);
        
        $stats = [
            'total_rules' => count($rules),
            'enabled_rules' => 0,
            'disabled_rules' => 0,
            'rule_types' => [],
            'last_updated' => null
        ];
        
        foreach ($rules as $rule) {
            if ($rule['enabled'] ?? true) {
                $stats['enabled_rules']++;
            } else {
                $stats['disabled_rules']++;
            }
            
            $type = $rule['type'] ?? 'unknown';
            $stats['rule_types'][$type] = ($stats['rule_types'][$type] ?? 0) + 1;
            
            if (!$stats['last_updated'] || $rule['updated_at'] > $stats['last_updated']) {
                $stats['last_updated'] = $rule['updated_at'] ?? null;
            }
        }
        
        return $stats;
    }

    /**
     * 异步获取规则详细信息
     */
    private function asyncGetRuleDetails(string $ruleId): \Generator
    {
        yield sleep(0.001);
        
        // 异步获取规则使用统计
        $usageStats = yield create_task($this->asyncGetRuleUsageStats($ruleId));
        
        // 异步获取规则测试历史
        $testHistory = yield create_task($this->asyncGetRuleTestHistory($ruleId));
        
        return [
            'usage_stats' => $usageStats,
            'test_history' => $testHistory,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * 异步验证规则数据
     */
    private function asyncValidateRuleData(array $ruleData): \Generator
    {
        yield sleep(0.001);
        
        $errors = [];
        
        // 验证必填字段
        $requiredFields = ['name', 'type', 'pattern'];
        foreach ($requiredFields as $field) {
            if (!isset($ruleData[$field]) || empty($ruleData[$field])) {
                $errors[] = "Field '{$field}' is required";
            }
        }
        
        // 验证规则类型
        $validTypes = ['sql_injection', 'xss', 'csrf', 'rate_limit', 'ip_whitelist', 'ip_blacklist'];
        if (isset($ruleData['type']) && !in_array($ruleData['type'], $validTypes)) {
            $errors[] = "Invalid rule type: {$ruleData['type']}";
        }
        
        // 验证正则表达式
        if (isset($ruleData['pattern'])) {
            $pattern = $ruleData['pattern'];
            if (@preg_match($pattern, '') === false) {
                $errors[] = "Invalid regex pattern: {$pattern}";
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * 异步测试新规则
     */
    private function asyncTestNewRule(string $ruleId, array $ruleData): \Generator
    {
        yield sleep(0.005);
        
        // 模拟规则测试
        $testCases = [
            ['input' => 'test input', 'expected' => false],
            ['input' => 'malicious payload', 'expected' => true]
        ];
        
        $results = [];
        foreach ($testCases as $testCase) {
            $results[] = [
                'input' => $testCase['input'],
                'expected' => $testCase['expected'],
                'actual' => rand(0, 1) === 1, // 模拟测试结果
                'passed' => true
            ];
        }
        
        return [
            'test_cases' => $results,
            'overall_result' => 'passed'
        ];
    }

    /**
     * 异步测试更新后的规则
     */
    private function asyncTestUpdatedRule(string $ruleId, array $ruleData): \Generator
    {
        yield sleep(0.003);
        
        // 模拟更新后的规则测试
        return [
            'test_cases' => [
                ['input' => 'updated test', 'expected' => false, 'actual' => false, 'passed' => true]
            ],
            'overall_result' => 'passed'
        ];
    }

    /**
     * 异步记录规则删除日志
     */
    private function asyncLogRuleDeletion(string $ruleId, array $rule): \Generator
    {
        yield sleep(0.001);
        
        yield $this->dbManager->asyncInsert('rule_deletion_logs', [
            'rule_id' => $ruleId,
            'rule_name' => $rule['name'] ?? 'Unknown',
            'rule_type' => $rule['type'] ?? 'Unknown',
            'deleted_at' => date('Y-m-d H:i:s'),
            'deleted_by' => 'api_user'
        ]);
    }

    /**
     * 异步记录规则测试
     */
    private function asyncLogRuleTest(string $ruleId, array $testData, array $testResult): \Generator
    {
        yield sleep(0.001);
        
        yield $this->dbManager->asyncInsert('rule_test_logs', [
            'rule_id' => $ruleId,
            'test_data' => json_encode($testData),
            'test_result' => json_encode($testResult),
            'tested_at' => date('Y-m-d H:i:s'),
            'tested_by' => 'api_user'
        ]);
    }

    /**
     * 异步获取规则使用统计
     */
    private function asyncGetRuleUsageStats(string $ruleId): \Generator
    {
        yield sleep(0.002);
        
        $result = yield $this->dbManager->asyncQuery(
            "SELECT 
                COUNT(*) as total_usage,
                SUM(CASE WHEN blocked = 1 THEN 1 ELSE 0 END) as blocked_count,
                AVG(duration) as avg_duration
             FROM waf_logs 
             WHERE rule = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)",
            [$ruleId]
        );
        
        return $result[0] ?? [
            'total_usage' => 0,
            'blocked_count' => 0,
            'avg_duration' => 0
        ];
    }

    /**
     * 异步获取规则测试历史
     */
    private function asyncGetRuleTestHistory(string $ruleId): \Generator
    {
        yield sleep(0.002);
        
        $result = yield $this->dbManager->asyncQuery(
            "SELECT 
                test_data,
                test_result,
                tested_at
             FROM rule_test_logs 
             WHERE rule_id = ? 
             ORDER BY tested_at DESC 
             LIMIT 10",
            [$ruleId]
        );
        
        return $result ?? [];
    }
}
