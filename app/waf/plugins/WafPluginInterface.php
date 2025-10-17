<?php

namespace app\waf\plugins;

/**
 * WAF 插件接口
 * 
 * 所有 WAF 检测插件都必须实现此接口
 */
interface WafPluginInterface
{
    /**
     * 获取插件名称
     */
    public function getName(): string;
    
    /**
     * 获取插件版本
     */
    public function getVersion(): string;
    
    /**
     * 获取插件优先级
     */
    public function getPriority(): int;
    
    /**
     * 是否启用
     */
    public function isEnabled(): bool;
    
    /**
     * 检测请求
     */
    public function detect(array $requestData): \Generator|array;
    
    /**
     * 获取插件描述
     */
    public function getDescription(): string;
    
    /**
     * 获取插件配置
     */
    public function getConfig(): array;
}
