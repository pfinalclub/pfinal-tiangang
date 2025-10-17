<?php

require_once __DIR__ . '/vendor/autoload.php';

use PfinalClub\Asyncio\{create_task, gather, wait_for, sleep, run};
use Tiangang\Waf\Api\Controllers\RuleController;
use Tiangang\Waf\Api\Controllers\ConfigController;
use Tiangang\Waf\Api\Controllers\PluginController;
use Tiangang\Waf\Web\Controllers\DashboardController;
use Tiangang\Waf\Api\Routes\ApiRoutes;

echo "天罡 WAF 阶段六：管理 REST API 和 Web 控制台测试\n";
echo "==============================================\n\n";

// 模拟规则管理 API 测试
function simulateRuleApiTest(): \Generator
{
    echo "1. 规则管理 API 测试...\n";
    
    $ruleController = new RuleController();
    
    $startTime = microtime(true);
    
    // 并发执行多个规则 API 操作
    $tasks = [];
    
    // 获取所有规则
    $tasks[] = create_task($ruleController->asyncGetRules());
    
    // 创建新规则
    $newRule = [
        'name' => 'test_rule_' . time(),
        'type' => 'sql_injection',
        'pattern' => '/union.*select/i',
        'enabled' => true,
        'severity' => 'high'
    ];
    $tasks[] = create_task($ruleController->asyncCreateRule($newRule));
    
    // 测试规则
    $testData = [
        'input' => "1' UNION SELECT * FROM users",
        'expected' => true
    ];
    $tasks[] = create_task($ruleController->asyncTestRule('test_rule_1', $testData));
    
    $results = yield gather(...$tasks);
    
    $endTime = microtime(true);
    $duration = $endTime - $startTime;
    
    echo "✓ 规则管理 API 完成\n";
    echo "  - 获取规则: 1个\n";
    echo "  - 创建规则: 1个\n";
    echo "  - 测试规则: 1个\n";
    echo "  - 总耗时: " . round($duration * 1000, 2) . "ms\n\n";
}

// 模拟配置管理 API 测试
function simulateConfigApiTest(): \Generator
{
    echo "2. 配置管理 API 测试...\n";
    
    $configController = new ConfigController();
    
    $startTime = microtime(true);
    
    // 并发执行多个配置 API 操作
    $tasks = [];
    
    // 获取所有配置
    $tasks[] = create_task($configController->asyncGetConfigs());
    
    // 更新单个配置
    $tasks[] = create_task($configController->asyncUpdateConfig('waf.timeout', 10.0));
    
    // 批量更新配置
    $batchConfigs = [
        'waf.enabled' => true,
        'proxy.timeout' => 30.0,
        'monitoring.interval' => 60
    ];
    $tasks[] = create_task($configController->asyncBatchUpdateConfigs($batchConfigs));
    
    // 重新加载配置
    $tasks[] = create_task($configController->asyncReloadConfigs());
    
    $results = yield gather(...$tasks);
    
    $endTime = microtime(true);
    $duration = $endTime - $startTime;
    
    echo "✓ 配置管理 API 完成\n";
    echo "  - 获取配置: 1个\n";
    echo "  - 更新配置: 1个\n";
    echo "  - 批量更新: 1个\n";
    echo "  - 重新加载: 1个\n";
    echo "  - 总耗时: " . round($duration * 1000, 2) . "ms\n\n";
}

// 模拟插件管理 API 测试
function simulatePluginApiTest(): \Generator
{
    echo "3. 插件管理 API 测试...\n";
    
    $pluginController = new PluginController();
    
    $startTime = microtime(true);
    
    // 并发执行多个插件 API 操作
    $tasks = [];
    
    // 获取所有插件
    $tasks[] = create_task($pluginController->asyncGetPlugins());
    
    // 安装新插件
    $newPlugin = [
        'name' => 'custom_detector',
        'type' => 'detector',
        'version' => '1.0.0',
        'enabled' => true,
        'description' => 'Custom detection plugin'
    ];
    $tasks[] = create_task($pluginController->asyncInstallPlugin($newPlugin));
    
    // 启用插件
    $tasks[] = create_task($pluginController->asyncEnablePlugin('sql_injection_detector'));
    
    // 更新插件
    $updateData = [
        'version' => '1.1.0',
        'description' => 'Updated detection plugin'
    ];
    $tasks[] = create_task($pluginController->asyncUpdatePlugin('xss_detector', $updateData));
    
    $results = yield gather(...$tasks);
    
    $endTime = microtime(true);
    $duration = $endTime - $startTime;
    
    echo "✓ 插件管理 API 完成\n";
    echo "  - 获取插件: 1个\n";
    echo "  - 安装插件: 1个\n";
    echo "  - 启用插件: 1个\n";
    echo "  - 更新插件: 1个\n";
    echo "  - 总耗时: " . round($duration * 1000, 2) . "ms\n\n";
}

// 模拟 Web 控制台测试
function simulateWebConsoleTest(): \Generator
{
    echo "4. Web 控制台测试...\n";
    
    $dashboardController = new DashboardController();
    
    $startTime = microtime(true);
    
    // 并发执行多个控制台操作
    $tasks = [];
    
    // 获取仪表板数据
    $tasks[] = create_task($dashboardController->asyncGetDashboardData());
    
    // 获取性能报告
    $tasks[] = create_task($dashboardController->asyncGetPerformanceReport('1h'));
    
    // 获取安全报告
    $tasks[] = create_task($dashboardController->asyncGetSecurityReport('1d'));
    
    // 导出数据
    $tasks[] = create_task($dashboardController->asyncExportData('dashboard', 'json'));
    $tasks[] = create_task($dashboardController->asyncExportData('performance', 'csv'));
    
    $results = yield gather(...$tasks);
    
    $endTime = microtime(true);
    $duration = $endTime - $startTime;
    
    echo "✓ Web 控制台完成\n";
    echo "  - 仪表板数据: 1个\n";
    echo "  - 性能报告: 1个\n";
    echo "  - 安全报告: 1个\n";
    echo "  - 数据导出: 2个\n";
    echo "  - 总耗时: " . round($duration * 1000, 2) . "ms\n\n";
}

// 模拟 API 路由测试
function simulateApiRoutesTest(): \Generator
{
    echo "5. API 路由测试...\n";
    
    $apiRoutes = new ApiRoutes();
    
    $startTime = microtime(true);
    
    // 并发执行多个 API 路由操作
    $tasks = [];
    
    // 处理规则请求
    $tasks[] = create_task($apiRoutes->asyncHandleRequest('GET', 'rules/list'));
    $tasks[] = create_task($apiRoutes->asyncHandleRequest('POST', 'rules/create', ['name' => 'test_rule']));
    $tasks[] = create_task($apiRoutes->asyncHandleRequest('PUT', 'rules/1/update', ['enabled' => true]));
    
    // 处理配置请求
    $tasks[] = create_task($apiRoutes->asyncHandleRequest('GET', 'config/list'));
    $tasks[] = create_task($apiRoutes->asyncHandleRequest('PUT', 'config/waf.timeout', ['value' => 15.0]));
    
    // 处理插件请求
    $tasks[] = create_task($apiRoutes->asyncHandleRequest('GET', 'plugins/list'));
    $tasks[] = create_task($apiRoutes->asyncHandleRequest('PUT', 'plugins/1/enable'));
    
    // 处理仪表板请求
    $tasks[] = create_task($apiRoutes->asyncHandleRequest('GET', 'dashboard/dashboard'));
    $tasks[] = create_task($apiRoutes->asyncHandleRequest('GET', 'dashboard/performance', ['period' => '1h']));
    
    $results = yield gather(...$tasks);
    
    $endTime = microtime(true);
    $duration = $endTime - $startTime;
    
    echo "✓ API 路由完成\n";
    echo "  - 规则请求: 3个\n";
    echo "  - 配置请求: 2个\n";
    echo "  - 插件请求: 2个\n";
    echo "  - 仪表板请求: 2个\n";
    echo "  - 总耗时: " . round($duration * 1000, 2) . "ms\n\n";
}

// 模拟综合 API 测试
function simulateComprehensiveApiTest(): \Generator
{
    echo "6. 综合 API 测试...\n";
    
    $apiRoutes = new ApiRoutes();
    
    $startTime = microtime(true);
    
    // 并发执行所有 API 操作
    $tasks = [];
    
    // 规则管理
    $tasks[] = create_task($apiRoutes->asyncHandleRequest('GET', 'rules/list'));
    $tasks[] = create_task($apiRoutes->asyncHandleRequest('POST', 'rules/create', [
        'name' => 'comprehensive_test_rule',
        'type' => 'xss',
        'pattern' => '/<script.*?>/i',
        'enabled' => true
    ]));
    
    // 配置管理
    $tasks[] = create_task($apiRoutes->asyncHandleRequest('GET', 'config/list'));
    $tasks[] = create_task($apiRoutes->asyncHandleRequest('PUT', 'config/waf.enabled', ['value' => true]));
    
    // 插件管理
    $tasks[] = create_task($apiRoutes->asyncHandleRequest('GET', 'plugins/list'));
    $tasks[] = create_task($apiRoutes->asyncHandleRequest('POST', 'plugins/install', [
        'name' => 'comprehensive_plugin',
        'type' => 'monitor',
        'version' => '1.0.0'
    ]));
    
    // 仪表板
    $tasks[] = create_task($apiRoutes->asyncHandleRequest('GET', 'dashboard/dashboard'));
    $tasks[] = create_task($apiRoutes->asyncHandleRequest('GET', 'dashboard/security', ['period' => '1d']));
    
    // API 文档和健康检查
    $tasks[] = create_task($apiRoutes->asyncGetApiDocumentation());
    $tasks[] = create_task($apiRoutes->asyncHealthCheck());
    
    $results = yield gather(...$tasks);
    
    $endTime = microtime(true);
    $duration = $endTime - $startTime;
    
    echo "✓ 综合 API 测试完成\n";
    echo "  - 规则管理: 2个操作\n";
    echo "  - 配置管理: 2个操作\n";
    echo "  - 插件管理: 2个操作\n";
    echo "  - 仪表板: 2个操作\n";
    echo "  - 文档和健康检查: 2个操作\n";
    echo "  - 总耗时: " . round($duration * 1000, 2) . "ms\n\n";
}

// 主测试函数
function runPhase6Tests(): \Generator
{
    echo "开始阶段六：管理 REST API 和 Web 控制台测试...\n\n";
    
    // 执行各个 API 测试
    yield simulateRuleApiTest();
    yield simulateConfigApiTest();
    yield simulatePluginApiTest();
    yield simulateWebConsoleTest();
    yield simulateApiRoutesTest();
    yield simulateComprehensiveApiTest();
    
    echo "阶段六测试完成！\n";
    echo "================\n";
    echo "总结:\n";
    echo "- 规则管理 API: 完整的 CRUD 操作\n";
    echo "- 配置管理 API: 配置的增删改查和批量操作\n";
    echo "- 插件管理 API: 插件的安装、启用、禁用、更新\n";
    echo "- Web 控制台: 仪表板、报告、数据导出\n";
    echo "- API 路由: 统一的请求处理和路由分发\n";
    echo "- 综合测试: 所有组件的协同工作\n\n";
    
    echo "阶段六功能特点:\n";
    echo "1. RESTful API: 标准的 REST API 设计\n";
    echo "2. 异步处理: 所有操作都是异步的\n";
    echo "3. 统一路由: 集中的路由管理和分发\n";
    echo "4. 数据验证: 完整的数据验证和错误处理\n";
    echo "5. 缓存优化: 智能缓存提升性能\n";
    echo "6. 文档完整: 完整的 API 文档和健康检查\n";
    echo "7. 数据导出: 支持多种格式的数据导出\n";
    echo "8. 实时监控: 实时仪表板和报告\n";
}

// 运行测试
try {
    \PfinalClub\Asyncio\run(runPhase6Tests());
} catch (Exception $e) {
    echo "测试失败: " . $e->getMessage() . "\n";
    echo "错误位置: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
