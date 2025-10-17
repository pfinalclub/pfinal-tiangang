<?php

require_once __DIR__ . '/vendor/autoload.php';

use Tiangang\Waf\Config\ConfigManager;
use Tiangang\Waf\Plugins\PluginManager;
use Tiangang\Waf\Detectors\QuickDetector;
use Tiangang\Waf\Detectors\AsyncDetector;
use Tiangang\Waf\Core\DecisionEngine;

echo "天罡 WAF 系统测试\n";
echo "================\n\n";

// 测试配置管理器
echo "1. 测试配置管理器...\n";
try {
    $configManager = new ConfigManager();
    $wafConfig = $configManager->get('waf');
    echo "✓ 配置管理器加载成功\n";
    echo "  - WAF 启用状态: " . ($wafConfig['enabled'] ? '是' : '否') . "\n";
    echo "  - 检测模式: " . ($wafConfig['detection']['quick_enabled'] ? '快速检测' : '异步检测') . "\n";
} catch (Exception $e) {
    echo "✗ 配置管理器加载失败: " . $e->getMessage() . "\n";
}

echo "\n";

// 测试插件管理器
echo "2. 测试插件管理器...\n";
try {
    $pluginManager = new PluginManager();
    $plugins = $pluginManager->getAllPlugins();
    echo "✓ 插件管理器加载成功\n";
    echo "  - 已加载插件数量: " . count($plugins) . "\n";
    
    foreach ($plugins as $name => $plugin) {
        echo "  - 插件: {$name} (版本: {$plugin->getVersion()}, 优先级: {$plugin->getPriority()})\n";
    }
} catch (Exception $e) {
    echo "✗ 插件管理器加载失败: " . $e->getMessage() . "\n";
}

echo "\n";

// 测试快速检测器
echo "3. 测试快速检测器...\n";
try {
    $quickDetector = new QuickDetector();
    
    // 模拟正常请求
    $normalRequest = [
        'ip' => '127.0.0.1',
        'uri' => '/test',
        'method' => 'GET',
        'headers' => ['User-Agent' => 'Mozilla/5.0'],
        'query' => ['id' => '123'],
        'post' => [],
        'cookies' => [],
        'user_agent' => 'Mozilla/5.0',
        'referer' => '',
        'timestamp' => time(),
    ];
    
    $result = $quickDetector->check($normalRequest);
    echo "✓ 快速检测器测试成功\n";
    echo "  - 正常请求结果: " . ($result->isBlocked() ? '拦截' : '放行') . "\n";
    
    // 模拟恶意请求
    $maliciousRequest = $normalRequest;
    $maliciousRequest['query'] = ['id' => "1' OR '1'='1"];
    
    $result = $quickDetector->check($maliciousRequest);
    echo "  - 恶意请求结果: " . ($result->isBlocked() ? '拦截' : '放行') . "\n";
    if ($result->isBlocked()) {
        echo "  - 拦截原因: " . $result->getMessage() . "\n";
    }
    
} catch (Exception $e) {
    echo "✗ 快速检测器测试失败: " . $e->getMessage() . "\n";
}

echo "\n";

// 测试异步检测器
echo "4. 测试异步检测器...\n";
try {
    $asyncDetector = new AsyncDetector();
    
    // 模拟正常请求
    $normalRequest = [
        'ip' => '127.0.0.1',
        'uri' => '/test',
        'method' => 'GET',
        'headers' => ['User-Agent' => 'Mozilla/5.0'],
        'query' => ['id' => '123'],
        'post' => [],
        'cookies' => [],
        'user_agent' => 'Mozilla/5.0',
        'referer' => '',
        'timestamp' => time(),
    ];
    
    $startTime = microtime(true);
    $results = $asyncDetector->check($normalRequest);
    $endTime = microtime(true);
    
    echo "✓ 异步检测器测试成功\n";
    echo "  - 检测耗时: " . round(($endTime - $startTime) * 1000, 2) . "ms\n";
    echo "  - 检测结果数量: " . count((array)$results) . "\n";
    
    foreach ($results as $result) {
        if ($result['matched'] ?? false) {
            echo "  - 匹配规则: " . ($result['rule'] ?? 'unknown') . "\n";
            echo "  - 严重程度: " . ($result['severity'] ?? 'unknown') . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "✗ 异步检测器测试失败: " . $e->getMessage() . "\n";
}

echo "\n";

// 测试决策引擎
echo "5. 测试决策引擎...\n";
try {
    $decisionEngine = new DecisionEngine();
    
    // 模拟检测结果
    $results = [
        [
            'matched' => true,
            'rule' => 'sql_injection',
            'severity' => 'high',
            'description' => 'SQL 注入攻击',
        ],
        [
            'matched' => true,
            'rule' => 'xss',
            'severity' => 'medium',
            'description' => 'XSS 攻击',
        ]
    ];
    
    $decision = $decisionEngine->evaluate($results);
    echo "✓ 决策引擎测试成功\n";
    echo "  - 决策结果: " . ($decision->isBlocked() ? '拦截' : '放行') . "\n";
    if ($decision->isBlocked()) {
        echo "  - 拦截原因: " . $decision->getMessage() . "\n";
        echo "  - 状态码: " . $decision->getStatusCode() . "\n";
    }
    
} catch (Exception $e) {
    echo "✗ 决策引擎测试失败: " . $e->getMessage() . "\n";
}

echo "\n";

echo "测试完成！\n";
echo "==========\n";
