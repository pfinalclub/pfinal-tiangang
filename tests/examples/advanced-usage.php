<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Tiangang\Waf\Api\Controllers\RuleController;
use Tiangang\Waf\Api\Controllers\ConfigController;
use Tiangang\Waf\Api\Controllers\PluginController;
use Tiangang\Waf\Web\Controllers\DashboardController;
use Tiangang\Waf\Performance\PerformanceAnalyzer;
use Tiangang\Waf\Optimization\PerformanceOptimizer;
use PfinalClub\Asyncio\{create_task, gather, wait_for, sleep, run};

/**
 * 天罡 WAF 高级使用示例
 * 
 * 演示如何使用天罡 WAF 系统的高级功能
 */

echo "天罡 WAF 高级使用示例\n";
echo "==================\n\n";

// 示例 1: API 管理
function example1_apiManagement(): \Generator
{
    echo "示例 1: API 管理\n";
    echo "----------------\n";
    
    $ruleController = new RuleController();
    $configController = new ConfigController();
    $pluginController = new PluginController();
    
    // 获取所有规则
    echo "获取所有规则...\n";
    $rules = yield $ruleController->asyncGetRules();
    echo "规则数量: " . ($rules['data']['total_count'] ?? 0) . "\n";
    
    // 创建新规则
    echo "创建新规则...\n";
    $newRule = [
        'name' => 'Advanced SQL Injection Detection',
        'type' => 'sql_injection',
        'pattern' => '/(union|select|insert|update|delete|drop|create|alter)\\s+.*?\\s+(from|into|where|set|values)/i',
        'enabled' => true,
        'severity' => 'critical',
        'description' => 'Advanced SQL injection pattern detection'
    ];
    $createResult = yield $ruleController->asyncCreateRule($newRule);
    echo "创建结果: " . ($createResult['success'] ? '成功' : '失败') . "\n";
    
    // 测试规则
    echo "测试规则...\n";
    $testData = [
        'input' => "1' UNION SELECT username, password FROM users WHERE '1'='1",
        'expected' => true
    ];
    $testResult = yield $ruleController->asyncTestRule($createResult['data']['rule_id'] ?? 'test_rule', $testData);
    echo "测试结果: " . ($testResult['success'] ? '通过' : '失败') . "\n\n";
}

// 示例 2: 配置管理
function example2_configManagement(): \Generator
{
    echo "示例 2: 配置管理\n";
    echo "----------------\n";
    
    $configController = new ConfigController();
    
    // 获取所有配置
    echo "获取所有配置...\n";
    $configs = yield $configController->asyncGetConfigs();
    echo "配置数量: " . ($configs['data']['total_count'] ?? 0) . "\n";
    
    // 更新单个配置
    echo "更新 WAF 超时配置...\n";
    $updateResult = yield $configController->asyncUpdateConfig('waf.timeout', 15.0);
    echo "更新结果: " . ($updateResult['success'] ? '成功' : '失败') . "\n";
    
    // 批量更新配置
    echo "批量更新配置...\n";
    $batchConfigs = [
        'waf.enabled' => true,
        'proxy.timeout' => 60.0,
        'monitoring.interval' => 30,
        'logging.level' => 'info'
    ];
    $batchResult = yield $configController->asyncBatchUpdateConfigs($batchConfigs);
    echo "批量更新结果: " . ($batchResult['success'] ? '成功' : '失败') . "\n";
    echo "更新成功: " . ($batchResult['data']['total_updated'] ?? 0) . " 个\n\n";
}

// 示例 3: 插件管理
function example3_pluginManagement(): \Generator
{
    echo "示例 3: 插件管理\n";
    echo "----------------\n";
    
    $pluginController = new PluginController();
    
    // 获取所有插件
    echo "获取所有插件...\n";
    $plugins = yield $pluginController->asyncGetPlugins();
    echo "插件数量: " . ($plugins['data']['total_count'] ?? 0) . "\n";
    
    // 安装新插件
    echo "安装新插件...\n";
    $newPlugin = [
        'name' => 'Custom Threat Detector',
        'type' => 'detector',
        'version' => '1.0.0',
        'enabled' => true,
        'description' => 'Custom threat detection plugin',
        'author' => 'Tiangang Team',
        'license' => 'MIT'
    ];
    $installResult = yield $pluginController->asyncInstallPlugin($newPlugin);
    echo "安装结果: " . ($installResult['success'] ? '成功' : '失败') . "\n";
    
    // 启用插件
    echo "启用插件...\n";
    $enableResult = yield $pluginController->asyncEnablePlugin($installResult['data']['plugin_id'] ?? 'test_plugin');
    echo "启用结果: " . ($enableResult['success'] ? '成功' : '失败') . "\n\n";
}

// 示例 4: 仪表板监控
function example4_dashboardMonitoring(): \Generator
{
    echo "示例 4: 仪表板监控\n";
    echo "------------------\n";
    
    $dashboardController = new DashboardController();
    
    // 获取仪表板数据
    echo "获取仪表板数据...\n";
    $dashboardData = yield $dashboardController->asyncGetDashboardData();
    echo "仪表板数据获取: " . ($dashboardData['success'] ? '成功' : '失败') . "\n";
    
    // 获取性能报告
    echo "获取性能报告...\n";
    $performanceReport = yield $dashboardController->asyncGetPerformanceReport('1h');
    echo "性能报告获取: " . ($performanceReport['success'] ? '成功' : '失败') . "\n";
    
    // 获取安全报告
    echo "获取安全报告...\n";
    $securityReport = yield $dashboardController->asyncGetSecurityReport('1d');
    echo "安全报告获取: " . ($securityReport['success'] ? '成功' : '失败') . "\n";
    
    // 导出数据
    echo "导出仪表板数据...\n";
    $exportResult = yield $dashboardController->asyncExportData('dashboard', 'json');
    echo "数据导出: " . ($exportResult['success'] ? '成功' : '失败') . "\n";
    echo "导出大小: " . ($exportResult['data']['size'] ?? 0) . " bytes\n\n";
}

// 示例 5: 性能分析
function example5_performanceAnalysis(): \Generator
{
    echo "示例 5: 性能分析\n";
    echo "----------------\n";
    
    $performanceAnalyzer = new PerformanceAnalyzer();
    
    // 开始性能分析
    echo "开始性能分析...\n";
    $analysisId = yield $performanceAnalyzer->asyncStartAnalysis('advanced_example', [
        'component' => 'waf_middleware',
        'operation' => 'threat_detection',
        'user_id' => 'admin'
    ]);
    echo "分析 ID: {$analysisId}\n";
    
    // 模拟一些操作
    yield sleep(0.01);
    
    // 结束性能分析
    echo "结束性能分析...\n";
    $analysisResult = yield $performanceAnalyzer->asyncEndAnalysis($analysisId, [
        'rules_checked' => 15,
        'threats_detected' => 2,
        'processing_time' => 0.01
    ]);
    echo "分析结果: " . json_encode($analysisResult, JSON_PRETTY_PRINT) . "\n";
    
    // 获取性能报告
    echo "获取性能报告...\n";
    $performanceReport = yield $performanceAnalyzer->asyncGetPerformanceReport('1h');
    echo "性能报告获取: " . ($performanceReport['success'] ?? false ? '成功' : '失败') . "\n\n";
}

// 示例 6: 性能优化
function example6_performanceOptimization(): \Generator
{
    echo "示例 6: 性能优化\n";
    echo "----------------\n";
    
    $optimizer = new PerformanceOptimizer();
    
    // 分析系统性能
    echo "分析系统性能...\n";
    $performanceData = yield $optimizer->asyncAnalyzePerformance();
    echo "性能分析完成\n";
    echo "响应时间状态: " . ($performanceData['response_time']['status'] ?? 'unknown') . "\n";
    echo "内存使用状态: " . ($performanceData['memory_usage']['status'] ?? 'unknown') . "\n";
    
    // 生成优化建议
    echo "生成优化建议...\n";
    $suggestions = yield $optimizer->asyncGenerateOptimizationSuggestions();
    echo "优化建议数量: " . count($suggestions) . "\n";
    
    foreach ($suggestions as $suggestion) {
        echo "- {$suggestion['title']} (优先级: {$suggestion['priority']})\n";
    }
    
    // 应用缓存优化
    echo "应用缓存优化...\n";
    $cacheOptimization = yield $optimizer->asyncApplyOptimization('cache_optimization', [
        'ttl' => 7200,
        'max_size' => 2000
    ]);
    echo "缓存优化结果: " . ($cacheOptimization['status'] ?? 'unknown') . "\n";
    
    // 应用数据库优化
    echo "应用数据库优化...\n";
    $dbOptimization = yield $optimizer->asyncApplyOptimization('database_optimization', [
        'max_connections' => 200,
        'timeout' => 60
    ]);
    echo "数据库优化结果: " . ($dbOptimization['status'] ?? 'unknown') . "\n\n";
}

// 示例 7: 并发 API 操作
function example7_concurrentApiOperations(): \Generator
{
    echo "示例 7: 并发 API 操作\n";
    echo "--------------------\n";
    
    $ruleController = new RuleController();
    $configController = new ConfigController();
    $pluginController = new PluginController();
    $dashboardController = new DashboardController();
    
    echo "并发执行多个 API 操作...\n";
    
    $startTime = microtime(true);
    
    // 并发执行多个 API 操作
    $tasks = [
        create_task($ruleController->asyncGetRules()),
        create_task($configController->asyncGetConfigs()),
        create_task($pluginController->asyncGetPlugins()),
        create_task($dashboardController->asyncGetDashboardData())
    ];
    
    $results = yield gather(...$tasks);
    
    $endTime = microtime(true);
    $duration = $endTime - $startTime;
    
    echo "并发操作完成\n";
    echo "总耗时: " . round($duration * 1000, 2) . "ms\n";
    echo "规则数量: " . ($results[0]['data']['total_count'] ?? 0) . "\n";
    echo "配置数量: " . ($results[1]['data']['total_count'] ?? 0) . "\n";
    echo "插件数量: " . ($results[2]['data']['total_count'] ?? 0) . "\n";
    echo "仪表板数据: " . ($results[3]['success'] ? '成功' : '失败') . "\n\n";
}

// 示例 8: 错误处理和恢复
function example8_errorHandlingAndRecovery(): \Generator
{
    echo "示例 8: 错误处理和恢复\n";
    echo "----------------------\n";
    
    $ruleController = new RuleController();
    
    // 测试无效规则创建
    echo "测试无效规则创建...\n";
    $invalidRule = [
        'name' => '', // 空名称
        'type' => 'invalid_type', // 无效类型
        'pattern' => 'invalid[regex', // 无效正则
        'enabled' => true
    ];
    
    try {
        $result = yield $ruleController->asyncCreateRule($invalidRule);
        echo "创建结果: " . ($result['success'] ? '成功' : '失败') . "\n";
        if (!$result['success']) {
            echo "错误信息: " . ($result['error'] ?? '未知错误') . "\n";
        }
    } catch (\Exception $e) {
        echo "捕获异常: " . $e->getMessage() . "\n";
    }
    
    // 测试不存在的规则操作
    echo "测试不存在的规则操作...\n";
    try {
        $result = yield $ruleController->asyncGetRule('nonexistent_rule');
        echo "获取结果: " . ($result['success'] ? '成功' : '失败') . "\n";
        if (!$result['success']) {
            echo "错误信息: " . ($result['error'] ?? '未知错误') . "\n";
        }
    } catch (\Exception $e) {
        echo "捕获异常: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}

// 主函数
function runAdvancedExamples(): \Generator
{
    echo "开始运行高级使用示例...\n\n";
    
    // 运行所有示例
    yield example1_apiManagement();
    yield example2_configManagement();
    yield example3_pluginManagement();
    yield example4_dashboardMonitoring();
    yield example5_performanceAnalysis();
    yield example6_performanceOptimization();
    yield example7_concurrentApiOperations();
    yield example8_errorHandlingAndRecovery();
    
    echo "所有高级示例运行完成！\n";
    echo "====================\n";
    echo "示例总结:\n";
    echo "1. API 管理: 演示如何使用 REST API 管理规则\n";
    echo "2. 配置管理: 演示如何管理系统配置\n";
    echo "3. 插件管理: 演示如何管理插件\n";
    echo "4. 仪表板监控: 演示如何使用监控功能\n";
    echo "5. 性能分析: 演示如何进行性能分析\n";
    echo "6. 性能优化: 演示如何进行性能优化\n";
    echo "7. 并发 API 操作: 演示如何并发执行 API 操作\n";
    echo "8. 错误处理和恢复: 演示如何处理各种错误\n\n";
    
    echo "高级功能特点:\n";
    echo "- 完整的 REST API 支持\n";
    echo "- 异步并发处理\n";
    echo "- 实时性能监控\n";
    echo "- 自动性能优化\n";
    echo "- 插件化架构\n";
    echo "- 错误恢复机制\n";
    echo "- 数据导出功能\n";
    echo "- 配置热更新\n\n";
    
    echo "最佳实践建议:\n";
    echo "1. 使用异步 API 提高性能\n";
    echo "2. 定期进行性能分析和优化\n";
    echo "3. 监控系统指标和日志\n";
    echo "4. 使用插件扩展功能\n";
    echo "5. 实施错误处理和恢复机制\n";
    echo "6. 定期备份配置和数据\n";
    echo "7. 使用监控工具跟踪系统状态\n";
    echo "8. 根据实际需求调整配置\n";
}

// 运行示例
try {
    \PfinalClub\Asyncio\run(runAdvancedExamples());
} catch (Exception $e) {
    echo "示例运行失败: " . $e->getMessage() . "\n";
    echo "错误位置: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
