<?php

require_once __DIR__ . '/vendor/autoload.php';

echo "天罡 WAF 功能检查\n";
echo "================\n\n";

// 1. 检查配置管理器
echo "1. 配置管理器...\n";
try {
    $configManager = new \Tiangang\Waf\Config\ConfigManager();
    $wafConfig = $configManager->get('waf');
    echo "✅ 配置管理器正常\n";
    echo "   WAF 启用: " . ($wafConfig['enabled'] ? '是' : '否') . "\n";
} catch (Exception $e) {
    echo "❌ 配置管理器失败: " . $e->getMessage() . "\n";
}

echo "\n";

// 2. 检查插件管理器
echo "2. 插件管理器...\n";
try {
    $pluginManager = new \Tiangang\Waf\Plugins\PluginManager();
    $plugins = $pluginManager->getAllPlugins();
    echo "✅ 插件管理器正常\n";
    echo "   已加载插件: " . count($plugins) . " 个\n";
    foreach ($plugins as $name => $plugin) {
        echo "   - {$name}: " . $plugin->getVersion() . "\n";
    }
} catch (Exception $e) {
    echo "❌ 插件管理器失败: " . $e->getMessage() . "\n";
}

echo "\n";

// 3. 检查异步检测器
echo "3. 异步检测器...\n";
try {
    $asyncDetector = new \Tiangang\Waf\Detectors\AsyncDetector();
    $requestData = [
        'ip' => '127.0.0.1',
        'uri' => '/test',
        'method' => 'GET',
        'headers' => [],
        'query' => [],
        'post' => [],
        'cookies' => [],
        'user_agent' => 'Mozilla/5.0',
        'referer' => '',
        'timestamp' => time(),
    ];
    
    $results = $asyncDetector->check($requestData);
    echo "✅ 异步检测器正常\n";
    echo "   检测结果: " . count($results) . " 个\n";
} catch (Exception $e) {
    echo "❌ 异步检测器失败: " . $e->getMessage() . "\n";
}

echo "\n";

// 4. 检查代理功能
echo "4. 代理功能...\n";
try {
    $proxyHandler = new \Tiangang\Waf\Proxy\ProxyHandler();
    echo "✅ 代理处理器正常\n";
} catch (Exception $e) {
    echo "❌ 代理处理器失败: " . $e->getMessage() . "\n";
}

echo "\n";

// 5. 检查监控功能
echo "5. 监控功能...\n";
try {
    $logger = new \Tiangang\Waf\Logging\AsyncLogger();
    $logger->log('info', 'Test log message');
    echo "✅ 日志系统正常\n";
    
    $metricsCollector = new \Tiangang\Waf\Monitoring\MetricsCollector();
    $metricsCollector->recordRequest(['duration' => 0.1, 'blocked' => false]);
    echo "✅ 指标收集正常\n";
    
    $alertManager = new \Tiangang\Waf\Monitoring\AlertManager();
    echo "✅ 告警系统正常\n";
} catch (Exception $e) {
    echo "❌ 监控功能失败: " . $e->getMessage() . "\n";
}

echo "\n";

echo "功能检查完成！\n";
