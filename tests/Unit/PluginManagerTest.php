<?php

require_once __DIR__ . '/../Unit/AsyncTestFramework.php';
require_once __DIR__ . '/../../app/plugins/PluginManager.php';

use Tiangang\Waf\Plugins\PluginManager;
use PfinalClub\Asyncio\{create_task, gather, wait_for, sleep, run};

/**
 * 插件管理器单元测试
 */
class PluginManagerTest extends AsyncTestFramework
{
    private PluginManager $pluginManager;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->pluginManager = new PluginManager();
    }
    
    /**
     * 测试插件加载
     */
    public function testLoadPlugin(): \Generator
    {
        $pluginData = [
            'name' => 'Test Plugin',
            'type' => 'detector',
            'version' => '1.0.0',
            'enabled' => true,
            'description' => 'Test plugin for unit testing'
        ];
        
        $result = yield $this->pluginManager->asyncLoadPlugin($pluginData);
        
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('plugin_id', $result['data']);
    }
    
    /**
     * 测试插件启用
     */
    public function testEnablePlugin(): \Generator
    {
        // 先加载插件
        $pluginData = [
            'name' => 'Test Plugin',
            'type' => 'detector',
            'version' => '1.0.0',
            'enabled' => false,
            'description' => 'Test plugin'
        ];
        
        $loadResult = yield $this->pluginManager->asyncLoadPlugin($pluginData);
        $pluginId = $loadResult['data']['plugin_id'];
        
        // 启用插件
        $result = yield $this->pluginManager->asyncEnablePlugin($pluginId);
        
        $this->assertTrue($result['success']);
    }
    
    /**
     * 测试插件禁用
     */
    public function testDisablePlugin(): \Generator
    {
        // 先加载并启用插件
        $pluginData = [
            'name' => 'Test Plugin',
            'type' => 'detector',
            'version' => '1.0.0',
            'enabled' => true,
            'description' => 'Test plugin'
        ];
        
        $loadResult = yield $this->pluginManager->asyncLoadPlugin($pluginData);
        $pluginId = $loadResult['data']['plugin_id'];
        
        // 禁用插件
        $result = yield $this->pluginManager->asyncDisablePlugin($pluginId);
        
        $this->assertTrue($result['success']);
    }
    
    /**
     * 测试插件卸载
     */
    public function testUninstallPlugin(): \Generator
    {
        // 先加载插件
        $pluginData = [
            'name' => 'Test Plugin',
            'type' => 'detector',
            'version' => '1.0.0',
            'enabled' => true,
            'description' => 'Test plugin'
        ];
        
        $loadResult = yield $this->pluginManager->asyncLoadPlugin($pluginData);
        $pluginId = $loadResult['data']['plugin_id'];
        
        // 卸载插件
        $result = yield $this->pluginManager->asyncUninstallPlugin($pluginId);
        
        $this->assertTrue($result['success']);
    }
    
    /**
     * 测试插件更新
     */
    public function testUpdatePlugin(): \Generator
    {
        // 先加载插件
        $pluginData = [
            'name' => 'Test Plugin',
            'type' => 'detector',
            'version' => '1.0.0',
            'enabled' => true,
            'description' => 'Test plugin'
        ];
        
        $loadResult = yield $this->pluginManager->asyncLoadPlugin($pluginData);
        $pluginId = $loadResult['data']['plugin_id'];
        
        // 更新插件
        $updateData = [
            'version' => '1.1.0',
            'description' => 'Updated test plugin'
        ];
        
        $result = yield $this->pluginManager->asyncUpdatePlugin($pluginId, $updateData);
        
        $this->assertTrue($result['success']);
    }
    
    /**
     * 测试插件列表获取
     */
    public function testGetPlugins(): \Generator
    {
        $result = yield $this->pluginManager->asyncGetPlugins();
        
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('plugins', $result['data']);
        $this->assertArrayHasKey('total_count', $result['data']);
    }
    
    /**
     * 测试插件重新加载
     */
    public function testReloadPlugins(): \Generator
    {
        $result = yield $this->pluginManager->asyncReloadPlugins();
        
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('reloaded_plugins', $result['data']);
    }
    
    /**
     * 测试插件验证
     */
    public function testValidatePlugin(): \Generator
    {
        $validPlugin = [
            'name' => 'Valid Plugin',
            'type' => 'detector',
            'version' => '1.0.0',
            'enabled' => true,
            'description' => 'Valid plugin'
        ];
        
        $result = yield $this->pluginManager->asyncValidatePlugin($validPlugin);
        
        $this->assertTrue($result['success']);
        $this->assertTrue($result['data']['valid']);
    }
    
    /**
     * 测试插件错误处理
     */
    public function testPluginErrorHandling(): \Generator
    {
        // 测试无效插件
        $invalidPlugin = [
            'name' => '', // 空名称
            'type' => 'invalid_type', // 无效类型
            'version' => 'invalid_version', // 无效版本
            'enabled' => true
        ];
        
        $result = yield $this->pluginManager->asyncLoadPlugin($invalidPlugin);
        
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
    }
    
    /**
     * 测试插件性能
     */
    public function testPluginPerformance(): \Generator
    {
        $startTime = microtime(true);
        
        // 加载多个插件
        $tasks = [];
        for ($i = 0; $i < 10; $i++) {
            $pluginData = [
                'name' => "Test Plugin {$i}",
                'type' => 'detector',
                'version' => '1.0.0',
                'enabled' => true,
                'description' => "Test plugin {$i}"
            ];
            $tasks[] = create_task($this->pluginManager->asyncLoadPlugin($pluginData));
        }
        
        $results = yield gather(...$tasks);
        
        $endTime = microtime(true);
        $duration = $endTime - $startTime;
        
        $this->assertLessThan(2.0, $duration, '插件加载应该很快');
        $this->assertCount(10, $results);
    }
    
    /**
     * 测试插件依赖
     */
    public function testPluginDependencies(): \Generator
    {
        // 加载依赖插件
        $dependencyPlugin = [
            'name' => 'Dependency Plugin',
            'type' => 'base',
            'version' => '1.0.0',
            'enabled' => true,
            'description' => 'Base plugin'
        ];
        
        $depResult = yield $this->pluginManager->asyncLoadPlugin($dependencyPlugin);
        $depPluginId = $depResult['data']['plugin_id'];
        
        // 加载依赖此插件的插件
        $dependentPlugin = [
            'name' => 'Dependent Plugin',
            'type' => 'detector',
            'version' => '1.0.0',
            'enabled' => true,
            'description' => 'Plugin that depends on base plugin',
            'dependencies' => [$depPluginId]
        ];
        
        $result = yield $this->pluginManager->asyncLoadPlugin($dependentPlugin);
        
        $this->assertTrue($result['success']);
    }
    
    /**
     * 测试插件配置
     */
    public function testPluginConfiguration(): \Generator
    {
        // 加载带配置的插件
        $pluginData = [
            'name' => 'Configurable Plugin',
            'type' => 'detector',
            'version' => '1.0.0',
            'enabled' => true,
            'description' => 'Plugin with configuration',
            'config' => [
                'threshold' => 0.8,
                'enabled_rules' => ['rule1', 'rule2']
            ]
        ];
        
        $result = yield $this->pluginManager->asyncLoadPlugin($pluginData);
        
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('config', $result['data']);
    }
}
