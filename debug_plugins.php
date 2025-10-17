<?php

require_once __DIR__ . '/vendor/autoload.php';

use Tiangang\Waf\Plugins\PluginManager;

echo "调试插件管理器\n";
echo "==============\n\n";

$pluginManager = new PluginManager();

echo "1. 插件路径检查\n";
echo "==============\n";
$pluginPath = __DIR__ . '/plugins/waf';
echo "插件路径: {$pluginPath}\n";
echo "路径存在: " . (is_dir($pluginPath) ? '是' : '否') . "\n";

if (is_dir($pluginPath)) {
    $files = glob($pluginPath . '/*.php');
    echo "插件文件数量: " . count($files) . "\n";
    echo "插件文件列表:\n";
    foreach ($files as $file) {
        echo "  - " . basename($file) . "\n";
    }
}

echo "\n2. 已加载插件\n";
echo "============\n";
$plugins = $pluginManager->getAllPlugins();
echo "已加载插件数量: " . count($plugins) . "\n";

foreach ($plugins as $name => $plugin) {
    echo "插件: {$name}\n";
    echo "  类名: " . get_class($plugin) . "\n";
    echo "  版本: " . $plugin->getVersion() . "\n";
    echo "  优先级: " . $plugin->getPriority() . "\n";
    echo "  启用状态: " . ($plugin->isEnabled() ? '是' : '否') . "\n";
}

echo "\n3. 测试获取特定插件\n";
echo "==================\n";
$testPlugins = ['sql_injection', 'xss', 'rate_limit', 'ip_blacklist'];

foreach ($testPlugins as $pluginName) {
    $plugin = $pluginManager->getPlugin($pluginName);
    echo "插件 {$pluginName}: " . ($plugin ? '找到' : '未找到') . "\n";
    if ($plugin) {
        echo "  类名: " . get_class($plugin) . "\n";
    }
}

echo "\n调试完成！\n";
