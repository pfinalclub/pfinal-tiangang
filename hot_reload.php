<?php

require_once __DIR__ . '/vendor/autoload.php';

use Tiangang\Waf\Config\HotReloadListener;
use Tiangang\Waf\Config\RuleConfigManager;

echo "天罡 WAF 热更新管理\n";
echo "================\n\n";

// 检查命令行参数
$action = $argv[1] ?? 'help';

switch ($action) {
    case 'start':
        startHotReload();
        break;
    case 'reload':
        reloadRules();
        break;
    case 'status':
        showStatus();
        break;
    case 'test':
        testRules();
        break;
    case 'help':
    default:
        showHelp();
        break;
}

/**
 * 启动热更新监听
 */
function startHotReload(): void
{
    echo "启动热更新监听器...\n";
    
    $listener = new HotReloadListener();
    
    // 设置信号处理
    if (function_exists('pcntl_signal')) {
        pcntl_signal(SIGTERM, function() use ($listener) {
            echo "\n收到终止信号，停止热更新监听器...\n";
            $listener->stop();
            exit(0);
        });
        
        pcntl_signal(SIGINT, function() use ($listener) {
            echo "\n收到中断信号，停止热更新监听器...\n";
            $listener->stop();
            exit(0);
        });
    }
    
    echo "热更新监听器已启动，按 Ctrl+C 停止\n";
    echo "监听文件变化中...\n\n";
    
    $listener->start();
}

/**
 * 重新加载规则
 */
function reloadRules(): void
{
    echo "重新加载规则配置...\n";
    
    $ruleConfigManager = new RuleConfigManager();
    $reloaded = $ruleConfigManager->hotReloadRules();
    
    if (empty($reloaded)) {
        echo "没有规则需要重新加载\n";
    } else {
        echo "已重新加载规则: " . implode(', ', $reloaded) . "\n";
    }
}

/**
 * 显示状态
 */
function showStatus(): void
{
    echo "WAF 规则状态\n";
    echo "============\n\n";
    
    $ruleConfigManager = new RuleConfigManager();
    $stats = $ruleConfigManager->getRuleStats();
    
    echo "总规则数: {$stats['total_rules']}\n";
    echo "启用规则: {$stats['enabled_rules']}\n";
    echo "禁用规则: {$stats['disabled_rules']}\n\n";
    
    echo "规则详情:\n";
    echo "--------\n";
    foreach ($stats['rules'] as $rule) {
        $status = $rule['enabled'] ? '✅ 启用' : '❌ 禁用';
        echo "- {$rule['name']}: {$status} (优先级: {$rule['priority']})\n";
    }
    
    echo "\n";
}

/**
 * 测试规则
 */
function testRules(): void
{
    echo "测试规则配置...\n";
    
    $ruleConfigManager = new RuleConfigManager();
    $allConfigs = $ruleConfigManager->getAllRuleConfigs();
    
    $passed = 0;
    $total = 0;
    
    foreach ($allConfigs as $ruleName => $config) {
        $total++;
        echo "测试规则: {$ruleName}... ";
        
        if ($config && isset($config['enabled']) && isset($config['priority'])) {
            echo "✅ 通过\n";
            $passed++;
        } else {
            echo "❌ 失败\n";
            if (!$config) {
                echo "  错误: 配置文件不存在或格式错误\n";
            } elseif (!isset($config['enabled'])) {
                echo "  错误: 缺少 enabled 字段\n";
            } elseif (!isset($config['priority'])) {
                echo "  错误: 缺少 priority 字段\n";
            }
        }
    }
    
    echo "\n测试结果: {$passed}/{$total} 通过\n";
}

/**
 * 显示帮助信息
 */
function showHelp(): void
{
    echo "天罡 WAF 热更新管理工具\n";
    echo "======================\n\n";
    echo "用法: php hot_reload.php <命令>\n\n";
    echo "可用命令:\n";
    echo "  start   - 启动热更新监听器\n";
    echo "  reload  - 重新加载规则配置\n";
    echo "  status  - 显示规则状态\n";
    echo "  test    - 测试规则配置\n";
    echo "  help    - 显示此帮助信息\n\n";
    echo "示例:\n";
    echo "  php hot_reload.php start    # 启动热更新监听\n";
    echo "  php hot_reload.php reload   # 手动重新加载规则\n";
    echo "  php hot_reload.php status   # 查看规则状态\n";
    echo "  php hot_reload.php test     # 测试规则配置\n\n";
}
