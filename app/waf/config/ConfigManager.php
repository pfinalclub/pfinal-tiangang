<?php

namespace app\waf\config;

use Symfony\Component\Yaml\Yaml;
use PfinalClub\Asyncio\{create_task, gather, wait_for, sleep};

/**
 * 配置管理器
 * 
 * 负责加载和管理 WAF 配置
 */
class ConfigManager
{
    private array $config = [];
    private string $configPath;
    
    public function __construct(?string $configPath = null)
    {
        if ($configPath) {
            $this->configPath = $configPath;
        } else {
            // 从 app/waf/config 向上三级到项目根目录，然后进入 config 目录
            $this->configPath = realpath(__DIR__ . '/../../../config') ?: __DIR__ . '/../../../config';
        }
        $this->loadConfig();
    }
    
    /**
     * 异步加载所有配置文件
     */
    public function asyncLoadConfig(): \Generator
    {
        // 并发加载所有配置文件
        $tasks = [
            create_task($this->asyncLoadConfigFile('waf.php')),
            create_task($this->asyncLoadConfigFile('database.php')),
            create_task($this->asyncLoadConfigFile('proxy.php')),
            create_task($this->asyncLoadConfigFile('monitoring.php')),
            create_task($this->asyncLoadRulesConfig()),
        ];
        
        yield gather(...$tasks);
    }
    
    /**
     * 异步加载单个配置文件
     */
    private function asyncLoadConfigFile(string $filename): \Generator
    {
        // 模拟异步文件读取
        yield sleep(0.001);
        
        $filePath = $this->configPath . '/' . $filename;
        if (file_exists($filePath)) {
            $configName = pathinfo($filename, PATHINFO_FILENAME);
            $this->config[$configName] = require $filePath;
        }
    }
    
    /**
     * 异步加载规则配置
     */
    private function asyncLoadRulesConfig(): \Generator
    {
        // 模拟异步规则加载
        yield sleep(0.002);
        
        $rulesPath = $this->configPath . '/rules';
        if (is_dir($rulesPath)) {
            $this->config['rules'] = [];
            
            foreach (glob($rulesPath . '/*.yaml') as $file) {
                $ruleName = pathinfo($file, PATHINFO_FILENAME);
                $this->config['rules'][$ruleName] = Yaml::parseFile($file);
            }
        }
    }
    
    /**
     * 异步重新加载配置
     */
    public function asyncReload(): \Generator
    {
        $this->config = [];
        yield $this->asyncLoadConfig();
    }
    
    /**
     * 加载所有配置文件（同步版本，保留兼容性）
     */
    private function loadConfig(): void
    {
        // 加载基础配置
        $this->loadConfigFile('waf.php');
        $this->loadConfigFile('database.php');
        $this->loadConfigFile('proxy.php');
        $this->loadConfigFile('monitoring.php');
        
        // 加载规则配置
        $this->loadRulesConfig();
    }
    
    /**
     * 加载单个配置文件
     */
    private function loadConfigFile(string $filename): void
    {
        $filePath = $this->configPath . '/' . $filename;
        if (file_exists($filePath)) {
            $configName = pathinfo($filename, PATHINFO_FILENAME);
            $this->config[$configName] = require $filePath;
        }
    }
    
    /**
     * 加载规则配置
     */
    private function loadRulesConfig(): void
    {
        $rulesPath = $this->configPath . '/rules';
        if (is_dir($rulesPath)) {
            $this->config['rules'] = [];
            
            foreach (glob($rulesPath . '/*.yaml') as $file) {
                $ruleName = pathinfo($file, PATHINFO_FILENAME);
                $this->config['rules'][$ruleName] = Yaml::parseFile($file);
            }
        }
    }
    
    /**
     * 获取配置
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);
        $value = $this->config;
        
        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }
        
        return $value;
    }
    
    /**
     * 设置配置
     */
    public function set(string $key, mixed $value): void
    {
        $keys = explode('.', $key);
        $config = &$this->config;
        
        foreach ($keys as $k) {
            if (!isset($config[$k])) {
                $config[$k] = [];
            }
            $config = &$config[$k];
        }
        
        $config = $value;
    }
    
    /**
     * 获取所有配置
     */
    public function all(): array
    {
        return $this->config;
    }
    
    /**
     * 重新加载配置
     */
    public function reload(): void
    {
        $this->config = [];
        $this->loadConfig();
    }
}
