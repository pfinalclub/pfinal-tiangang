<?php

namespace Tiangang\Waf\Testing;

use Tiangang\Waf\Config\RuleConfigManager;
use Tiangang\Waf\Plugins\PluginManager;
use Tiangang\Waf\Core\DecisionEngine;

/**
 * 规则测试框架
 * 
 * 提供规则测试、验证和性能分析功能
 */
class RuleTestFramework
{
    private RuleConfigManager $ruleConfigManager;
    private PluginManager $pluginManager;
    private DecisionEngine $decisionEngine;
    private array $testResults = [];
    
    public function __construct()
    {
        $this->ruleConfigManager = new RuleConfigManager();
        $this->pluginManager = new PluginManager();
        $this->decisionEngine = new DecisionEngine();
    }
    
    /**
     * 运行所有规则测试
     */
    public function runAllTests(): array
    {
        $results = [
            'total_tests' => 0,
            'passed_tests' => 0,
            'failed_tests' => 0,
            'test_results' => []
        ];
        
        // 获取所有规则
        $rules = $this->ruleConfigManager->getAllRuleConfigs();
        
        foreach ($rules as $ruleName => $config) {
            if ($config && ($config['enabled'] ?? false)) {
                $testResult = $this->testRule($ruleName);
                $results['test_results'][$ruleName] = $testResult;
                $results['total_tests']++;
                
                if ($testResult['passed']) {
                    $results['passed_tests']++;
                } else {
                    $results['failed_tests']++;
                }
            }
        }
        
        return $results;
    }
    
    /**
     * 测试单个规则
     */
    public function testRule(string $ruleName): array
    {
        $startTime = microtime(true);
        
        try {
            $plugin = $this->pluginManager->getPlugin($ruleName);
            if (!$plugin) {
                return [
                    'rule' => $ruleName,
                    'passed' => false,
                    'error' => 'Plugin not found',
                    'execution_time' => 0
                ];
            }
            
            // 获取测试用例
            $testCases = $this->getTestCases($ruleName);
            $testResults = [];
            
            foreach ($testCases as $testCase) {
                $result = $this->runTestCase($plugin, $testCase);
                $testResults[] = $result;
            }
            
            $passed = array_reduce($testResults, function ($carry, $result) {
                return $carry && $result['passed'];
            }, true);
            
            $executionTime = microtime(true) - $startTime;
            
            return [
                'rule' => $ruleName,
                'passed' => $passed,
                'test_cases' => $testResults,
                'execution_time' => $executionTime,
                'total_cases' => count($testCases)
            ];
            
        } catch (\Exception $e) {
            return [
                'rule' => $ruleName,
                'passed' => false,
                'error' => $e->getMessage(),
                'execution_time' => microtime(true) - $startTime
            ];
        }
    }
    
    /**
     * 运行测试用例
     */
    private function runTestCase($plugin, array $testCase): array
    {
        $startTime = microtime(true);
        
        try {
            // 运行插件检测
            $result = $plugin->detect($testCase['request_data']);
            
            // 如果是 Generator，需要运行它
            if ($result instanceof \Generator) {
                $result = \PfinalClub\Asyncio\run($result);
            }
            
            // 验证结果
            $expected = $testCase['expected'];
            $actual = is_array($result) ? $result : [];
            
            $passed = $this->validateResult($expected, $actual);
            
            return [
                'name' => $testCase['name'],
                'passed' => $passed,
                'expected' => $expected,
                'actual' => $actual,
                'execution_time' => microtime(true) - $startTime,
                'description' => $testCase['description'] ?? ''
            ];
            
        } catch (\Exception $e) {
            return [
                'name' => $testCase['name'],
                'passed' => false,
                'error' => $e->getMessage(),
                'execution_time' => microtime(true) - $startTime
            ];
        }
    }
    
    /**
     * 验证测试结果
     */
    private function validateResult(array $expected, array $actual): bool
    {
        // 检查是否匹配
        if (isset($expected['matched'])) {
            if (($expected['matched'] && !($actual['matched'] ?? false)) ||
                (!$expected['matched'] && ($actual['matched'] ?? false))) {
                return false;
            }
        }
        
        // 检查规则名称
        if (isset($expected['rule'])) {
            if (($actual['rule'] ?? '') !== $expected['rule']) {
                return false;
            }
        }
        
        // 检查严重程度
        if (isset($expected['severity'])) {
            if (($actual['severity'] ?? '') !== $expected['severity']) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * 获取规则测试用例
     */
    private function getTestCases(string $ruleName): array
    {
        $testFile = __DIR__ . "/../../tests/rules/{$ruleName}_test.json";
        
        if (file_exists($testFile)) {
            $content = file_get_contents($testFile);
            $testData = json_decode($content, true);
            return $testData['test_cases'] ?? [];
        }
        
        // 返回默认测试用例
        return $this->getDefaultTestCases($ruleName);
    }
    
    /**
     * 获取默认测试用例
     */
    private function getDefaultTestCases(string $ruleName): array
    {
        switch ($ruleName) {
            case 'sql_injection':
                return $this->getSqlInjectionTestCases();
            case 'xss':
                return $this->getXssTestCases();
            case 'rate_limit':
                return $this->getRateLimitTestCases();
            case 'ip_blacklist':
                return $this->getIpBlacklistTestCases();
            default:
                return [];
        }
    }
    
    /**
     * SQL 注入测试用例
     */
    private function getSqlInjectionTestCases(): array
    {
        return [
            [
                'name' => 'UNION SELECT 攻击',
                'description' => '检测 UNION SELECT 攻击',
                'request_data' => [
                    'ip' => '127.0.0.1',
                    'uri' => '/test',
                    'method' => 'GET',
                    'query' => ['id' => "1' UNION SELECT * FROM users--"],
                    'post' => [],
                    'headers' => [],
                    'cookies' => [],
                    'user_agent' => 'Mozilla/5.0',
                    'referer' => '',
                    'timestamp' => time()
                ],
                'expected' => [
                    'matched' => true,
                    'rule' => 'sql_injection',
                    'severity' => 'high'
                ]
            ],
            [
                'name' => '正常请求',
                'description' => '正常请求应该通过',
                'request_data' => [
                    'ip' => '127.0.0.1',
                    'uri' => '/test',
                    'method' => 'GET',
                    'query' => ['id' => '123'],
                    'post' => [],
                    'headers' => [],
                    'cookies' => [],
                    'user_agent' => 'Mozilla/5.0',
                    'referer' => '',
                    'timestamp' => time()
                ],
                'expected' => [
                    'matched' => false
                ]
            ]
        ];
    }
    
    /**
     * XSS 测试用例
     */
    private function getXssTestCases(): array
    {
        return [
            [
                'name' => 'Script 标签注入',
                'description' => '检测 script 标签注入',
                'request_data' => [
                    'ip' => '127.0.0.1',
                    'uri' => '/test',
                    'method' => 'GET',
                    'query' => ['name' => '<script>alert("xss")</script>'],
                    'post' => [],
                    'headers' => [],
                    'cookies' => [],
                    'user_agent' => 'Mozilla/5.0',
                    'referer' => '',
                    'timestamp' => time()
                ],
                'expected' => [
                    'matched' => true,
                    'rule' => 'xss',
                    'severity' => 'high'
                ]
            ],
            [
                'name' => '正常请求',
                'description' => '正常请求应该通过',
                'request_data' => [
                    'ip' => '127.0.0.1',
                    'uri' => '/test',
                    'method' => 'GET',
                    'query' => ['name' => 'John Doe'],
                    'post' => [],
                    'headers' => [],
                    'cookies' => [],
                    'user_agent' => 'Mozilla/5.0',
                    'referer' => '',
                    'timestamp' => time()
                ],
                'expected' => [
                    'matched' => false
                ]
            ]
        ];
    }
    
    /**
     * 频率限制测试用例
     */
    private function getRateLimitTestCases(): array
    {
        return [
            [
                'name' => '正常频率请求',
                'description' => '正常频率请求应该通过',
                'request_data' => [
                    'ip' => '127.0.0.1',
                    'uri' => '/test',
                    'method' => 'GET',
                    'query' => [],
                    'post' => [],
                    'headers' => [],
                    'cookies' => [],
                    'user_agent' => 'Mozilla/5.0',
                    'referer' => '',
                    'timestamp' => time()
                ],
                'expected' => [
                    'matched' => false
                ]
            ]
        ];
    }
    
    /**
     * IP 黑名单测试用例
     */
    private function getIpBlacklistTestCases(): array
    {
        return [
            [
                'name' => '黑名单 IP',
                'description' => '黑名单 IP 应该被拦截',
                'request_data' => [
                    'ip' => '192.168.1.100',
                    'uri' => '/test',
                    'method' => 'GET',
                    'query' => [],
                    'post' => [],
                    'headers' => [],
                    'cookies' => [],
                    'user_agent' => 'Mozilla/5.0',
                    'referer' => '',
                    'timestamp' => time()
                ],
                'expected' => [
                    'matched' => true,
                    'rule' => 'ip_blacklist',
                    'severity' => 'critical'
                ]
            ],
            [
                'name' => '白名单 IP',
                'description' => '白名单 IP 应该通过',
                'request_data' => [
                    'ip' => '127.0.0.1',
                    'uri' => '/test',
                    'method' => 'GET',
                    'query' => [],
                    'post' => [],
                    'headers' => [],
                    'cookies' => [],
                    'user_agent' => 'Mozilla/5.0',
                    'referer' => '',
                    'timestamp' => time()
                ],
                'expected' => [
                    'matched' => false
                ]
            ]
        ];
    }
    
    /**
     * 性能测试
     */
    public function performanceTest(string $ruleName, int $iterations = 1000): array
    {
        $plugin = $this->pluginManager->getPlugin($ruleName);
        if (!$plugin) {
            return ['error' => 'Plugin not found'];
        }
        
        $testData = [
            'ip' => '127.0.0.1',
            'uri' => '/test',
            'method' => 'GET',
            'query' => ['id' => "1' OR '1'='1"],
            'post' => [],
            'headers' => [],
            'cookies' => [],
            'user_agent' => 'Mozilla/5.0',
            'referer' => '',
            'timestamp' => time()
        ];
        
        $startTime = microtime(true);
        $startMemory = memory_get_usage();
        
        for ($i = 0; $i < $iterations; $i++) {
            try {
                $plugin->detect($testData);
            } catch (\Exception $e) {
                // 忽略错误，继续测试
            }
        }
        
        $endTime = microtime(true);
        $endMemory = memory_get_usage();
        
        return [
            'rule' => $ruleName,
            'iterations' => $iterations,
            'total_time' => $endTime - $startTime,
            'avg_time' => ($endTime - $startTime) / $iterations,
            'memory_used' => $endMemory - $startMemory,
            'requests_per_second' => $iterations / ($endTime - $startTime)
        ];
    }
    
    /**
     * 生成测试报告
     */
    public function generateTestReport(array $testResults): string
    {
        $report = "# WAF 规则测试报告\n\n";
        $report .= "生成时间: " . date('Y-m-d H:i:s') . "\n\n";
        
        $report .= "## 测试概览\n\n";
        $report .= "- 总测试数: {$testResults['total_tests']}\n";
        $report .= "- 通过测试: {$testResults['passed_tests']}\n";
        $report .= "- 失败测试: {$testResults['failed_tests']}\n";
        $report .= "- 通过率: " . round(($testResults['passed_tests'] / $testResults['total_tests']) * 100, 2) . "%\n\n";
        
        $report .= "## 详细结果\n\n";
        
        foreach ($testResults['test_results'] as $ruleName => $result) {
            $report .= "### {$ruleName}\n\n";
            $report .= "- 状态: " . ($result['passed'] ? '✅ 通过' : '❌ 失败') . "\n";
            $report .= "- 执行时间: " . round($result['execution_time'], 4) . "s\n";
            $report .= "- 测试用例数: " . ($result['total_cases'] ?? 0) . "\n";
            
            if (isset($result['error'])) {
                $report .= "- 错误: {$result['error']}\n";
            }
            
            $report .= "\n";
        }
        
        return $report;
    }
}
