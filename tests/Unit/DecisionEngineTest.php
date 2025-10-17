<?php

require_once __DIR__ . '/../Unit/AsyncTestFramework.php';
require_once __DIR__ . '/../../app/core/DecisionEngine.php';

use Tiangang\Waf\Core\DecisionEngine;
use PfinalClub\Asyncio\{create_task, gather, wait_for, sleep, run};

/**
 * 决策引擎单元测试
 */
class DecisionEngineTest extends AsyncTestFramework
{
    private DecisionEngine $decisionEngine;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->decisionEngine = new DecisionEngine();
    }
    
    /**
     * 测试决策评估
     */
    public function testEvaluate(): void
    {
        // 测试正常请求
        $normalResults = [];
        $decision = $this->decisionEngine->evaluate($normalResults);
        $this->assertFalse($decision);
        
        // 测试威胁检测
        $threatResults = ['sql_injection', 'xss'];
        $decision = $this->decisionEngine->evaluate($threatResults);
        $this->assertTrue($decision);
    }
    
    /**
     * 测试不同威胁类型
     */
    public function testDifferentThreatTypes(): void
    {
        $threatTypes = [
            'sql_injection' => true,
            'xss' => true,
            'csrf' => true,
            'rate_limit' => false, // 限流不直接拦截
            'ip_blacklist' => true,
            'unknown_threat' => false
        ];
        
        foreach ($threatTypes as $threat => $shouldBlock) {
            $results = [$threat];
            $decision = $this->decisionEngine->evaluate($results);
            $this->assertEquals($shouldBlock, $decision, "威胁类型 {$threat} 的决策不正确");
        }
    }
    
    /**
     * 测试多重威胁
     */
    public function testMultipleThreats(): void
    {
        $multipleThreats = ['sql_injection', 'xss', 'csrf'];
        $decision = $this->decisionEngine->evaluate($multipleThreats);
        $this->assertTrue($decision);
        
        $mixedThreats = ['rate_limit', 'sql_injection'];
        $decision = $this->decisionEngine->evaluate($mixedThreats);
        $this->assertTrue($decision);
    }
    
    /**
     * 测试决策权重
     */
    public function testDecisionWeights(): void
    {
        // 测试高权重威胁
        $highWeightThreats = ['sql_injection', 'critical_vulnerability'];
        $decision = $this->decisionEngine->evaluate($highWeightThreats);
        $this->assertTrue($decision);
        
        // 测试低权重威胁
        $lowWeightThreats = ['rate_limit', 'suspicious_activity'];
        $decision = $this->decisionEngine->evaluate($lowWeightThreats);
        $this->assertFalse($decision);
    }
    
    /**
     * 测试决策阈值
     */
    public function testDecisionThreshold(): void
    {
        // 测试低于阈值
        $belowThreshold = ['suspicious_activity'];
        $decision = $this->decisionEngine->evaluate($belowThreshold);
        $this->assertFalse($decision);
        
        // 测试高于阈值
        $aboveThreshold = ['sql_injection', 'xss', 'csrf'];
        $decision = $this->decisionEngine->evaluate($aboveThreshold);
        $this->assertTrue($decision);
    }
    
    /**
     * 测试决策性能
     */
    public function testDecisionPerformance(): void
    {
        $startTime = microtime(true);
        
        // 执行多次决策
        for ($i = 0; $i < 1000; $i++) {
            $results = $i % 10 === 0 ? ['sql_injection'] : [];
            $this->decisionEngine->evaluate($results);
        }
        
        $endTime = microtime(true);
        $duration = $endTime - $startTime;
        
        $this->assertLessThan(0.1, $duration, '决策评估应该很快');
    }
    
    /**
     * 测试决策日志
     */
    public function testDecisionLogging(): void
    {
        $threatResults = ['sql_injection', 'xss'];
        $decision = $this->decisionEngine->evaluate($threatResults);
        
        $this->assertTrue($decision);
        // 验证决策被记录到日志
    }
    
    /**
     * 测试决策统计
     */
    public function testDecisionStatistics(): void
    {
        // 执行多次决策
        $decisions = [];
        for ($i = 0; $i < 100; $i++) {
            $results = $i % 5 === 0 ? ['sql_injection'] : [];
            $decisions[] = $this->decisionEngine->evaluate($results);
        }
        
        $blockedCount = count(array_filter($decisions));
        $this->assertEquals(20, $blockedCount); // 每5个中有1个被拦截
    }
    
    /**
     * 测试决策配置
     */
    public function testDecisionConfiguration(): void
    {
        // 测试配置更新
        $this->decisionEngine->updateConfiguration([
            'block_threshold' => 2,
            'threat_weights' => [
                'sql_injection' => 10,
                'xss' => 8,
                'rate_limit' => 1
            ]
        ]);
        
        // 测试新配置下的决策
        $results = ['rate_limit', 'rate_limit']; // 两个限流应该被拦截
        $decision = $this->decisionEngine->evaluate($results);
        $this->assertTrue($decision);
    }
    
    /**
     * 测试决策错误处理
     */
    public function testDecisionErrorHandling(): void
    {
        // 测试空结果
        $decision = $this->decisionEngine->evaluate([]);
        $this->assertFalse($decision);
        
        // 测试无效结果
        $decision = $this->decisionEngine->evaluate(null);
        $this->assertFalse($decision);
        
        // 测试异常结果
        $decision = $this->decisionEngine->evaluate(['invalid_threat_type']);
        $this->assertFalse($decision);
    }
    
    /**
     * 测试决策缓存
     */
    public function testDecisionCaching(): void
    {
        $results = ['sql_injection'];
        
        // 第一次决策
        $startTime = microtime(true);
        $decision1 = $this->decisionEngine->evaluate($results);
        $time1 = microtime(true) - $startTime;
        
        // 第二次决策（应该使用缓存）
        $startTime = microtime(true);
        $decision2 = $this->decisionEngine->evaluate($results);
        $time2 = microtime(true) - $startTime;
        
        $this->assertEquals($decision1, $decision2);
        $this->assertLessThan($time1, $time2, '第二次决策应该更快（使用缓存）');
    }
}
