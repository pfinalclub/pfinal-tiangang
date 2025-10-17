<?php

require_once __DIR__ . '/../Unit/AsyncTestFramework.php';
require_once __DIR__ . '/../../app/config/ConfigManager.php';

use Tiangang\Waf\Config\ConfigManager;
use PfinalClub\Asyncio\{create_task, gather, wait_for, sleep, run};

/**
 * 配置管理器单元测试
 */
class ConfigManagerTest extends AsyncTestFramework
{
    private ConfigManager $configManager;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->configManager = new ConfigManager();
    }
    
    /**
     * 测试同步配置获取
     */
    public function testGetConfig(): void
    {
        $this->assertTrue($this->configManager->get('waf.enabled'));
        $this->assertEquals(5.0, $this->configManager->get('waf.timeout'));
        $this->assertNull($this->configManager->get('nonexistent.key'));
    }
    
    /**
     * 测试同步配置设置
     */
    public function testSetConfig(): void
    {
        $this->configManager->set('test.key', 'test_value');
        $this->assertEquals('test_value', $this->configManager->get('test.key'));
        
        $this->configManager->set('test.number', 123);
        $this->assertEquals(123, $this->configManager->get('test.number'));
    }
    
    /**
     * 测试异步配置加载
     */
    public function testAsyncLoadConfig(): \Generator
    {
        $result = yield $this->configManager->asyncLoadConfig();
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('configs', $result['data']);
    }
    
    /**
     * 测试异步配置重载
     */
    public function testAsyncReload(): \Generator
    {
        $result = yield $this->configManager->asyncReload();
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('reloaded_configs', $result['data']);
    }
    
    /**
     * 测试配置验证
     */
    public function testValidateConfig(): void
    {
        $validConfigs = [
            'waf.enabled' => true,
            'waf.timeout' => 5.0,
            'proxy.timeout' => 30.0
        ];
        
        foreach ($validConfigs as $key => $value) {
            $this->assertTrue($this->configManager->validate($key, $value));
        }
        
        $invalidConfigs = [
            'waf.timeout' => -1,
            'proxy.timeout' => 'invalid'
        ];
        
        foreach ($invalidConfigs as $key => $value) {
            $this->assertFalse($this->configManager->validate($key, $value));
        }
    }
    
    /**
     * 测试配置热更新
     */
    public function testHotReload(): \Generator
    {
        // 设置初始配置
        $this->configManager->set('test.hot_reload', 'initial');
        
        // 模拟配置文件更新
        $this->configManager->set('test.hot_reload', 'updated');
        
        // 验证配置已更新
        $this->assertEquals('updated', $this->configManager->get('test.hot_reload'));
    }
    
    /**
     * 测试配置性能
     */
    public function testConfigPerformance(): \Generator
    {
        $startTime = microtime(true);
        
        // 批量设置配置
        for ($i = 0; $i < 100; $i++) {
            $this->configManager->set("test.{$i}", "value_{$i}");
        }
        
        $endTime = microtime(true);
        $duration = $endTime - $startTime;
        
        $this->assertLessThan(0.1, $duration, '配置设置应该很快');
    }
    
    /**
     * 测试配置错误处理
     */
    public function testConfigErrorHandling(): void
    {
        // 测试无效键名
        $this->expectException(InvalidArgumentException::class);
        $this->configManager->set('', 'value');
    }
}
