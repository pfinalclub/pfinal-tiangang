<?php

namespace app\admin\controller;

use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;
use app\admin\Base\BaseController;
use app\waf\plugins\PluginManager;
use app\license\manager\PluginLicenseManager;

/**
 * 插件市场控制器
 * 
 * 负责插件市场浏览、插件详情查看、许可证管理等功能
 */
class PluginMarketController extends BaseController
{
    private PluginManager $pluginManager;
    private PluginLicenseManager $licenseManager;
    
    public function __construct()
    {
        $this->pluginManager = new PluginManager();
        $this->licenseManager = $this->pluginManager->getLicenseManager();
    }
    
    /**
     * 插件市场首页
     */
    public function index(Request $request): Response
    {
        // 获取可用插件列表（包括未安装的）
        $availablePlugins = $this->getAvailablePlugins();
        
        return json([
            'code' => 0,
            'msg' => 'success',
            'data' => [
                'plugins' => $availablePlugins,
                'total' => count($availablePlugins),
                'categories' => ['安全检测', '频率限制', '行为分析', '威胁情报']
            ]
        ]);
    }
    
    /**
     * 插件详情
     */
    public function detail(Request $request): Response
    {
        $pluginName = $request->get('plugin_name');
        
        if (empty($pluginName)) {
            return json([
                'code' => 1,
                'msg' => '插件名称不能为空'
            ]);
        }
        
        $plugin = $this->pluginManager->getPlugin($pluginName);
        if (!$plugin) {
            return json([
                'code' => 1,
                'msg' => '插件不存在'
            ]);
        }
        
        $pluginInfo = $this->pluginManager->getPluginInfo($pluginName);
        
        return json([
            'code' => 0,
            'msg' => 'success',
            'data' => [
                'name' => $pluginName,
                'version' => $plugin->getVersion(),
                'description' => $plugin->getDescription(),
                'enabled' => $plugin->isEnabled(),
                'priority' => $plugin->getPriority(),
                'requires_license' => $plugin->requiresLicense(),
                'license_valid' => $pluginInfo['license_valid'] ?? false,
                'supports_quick_detection' => $plugin->supportsQuickDetection(),
                'config' => $plugin->getConfig(),
                'features' => $this->getPluginFeatures($pluginName),
                'price' => $this->getPluginPrice($pluginName),
                'author' => 'Tiangang WAF Team',
                'homepage' => 'https://tiangang-waf.com',
            ]
        ]);
    }
    
    /**
     * 许可证状态总览
     */
    public function licenseStatus(Request $request): Response
    {
        $allPlugins = $this->pluginManager->getAllPluginInfo();
        $licenseStatus = [];
        
        foreach ($allPlugins as $name => $info) {
            if ($info['requires_license']) {
                $licenseStatus[] = [
                    'plugin' => $name,
                    'requires_license' => true,
                    'license_valid' => $info['license_valid'] ?? false,
                    'license_message' => $info['license_message'] ?? '',
                ];
            }
        }
        
        return json([
            'code' => 0,
            'msg' => 'success',
            'data' => [
                'licenses' => $licenseStatus,
                'total' => count($licenseStatus),
                'valid_count' => count(array_filter($licenseStatus, fn($item) => $item['license_valid'])),
            ]
        ]);
    }
    
    /**
     * 验证许可证
     */
    public function validateLicense(Request $request): Response
    {
        $pluginName = $request->post('plugin_name');
        $licenseKey = $request->post('license_key');
        
        if (empty($pluginName) || empty($licenseKey)) {
            return json([
                'code' => 1,
                'msg' => '插件名称和许可证密钥不能为空'
            ]);
        }
        
        $result = $this->licenseManager->validatePluginLicense($pluginName, $licenseKey);
        
        return json([
            'code' => $result['valid'] ? 0 : 1,
            'msg' => $result['message'] ?? '',
            'data' => [
                'valid' => $result['valid'],
                'expires_at' => $result['expires_at'] ?? null,
                'features' => $result['features'] ?? []
            ]
        ]);
    }
    
    /**
     * 续费许可证
     */
    public function renewLicense(Request $request): Response
    {
        $pluginName = $request->post('plugin_name');
        
        if (empty($pluginName)) {
            return json([
                'code' => 1,
                'msg' => '插件名称不能为空'
            ]);
        }
        
        // TODO: 实现许可证续费逻辑（对接支付系统）
        return json([
            'code' => 0,
            'msg' => '请联系客服进行许可证续费',
            'data' => [
                'contact' => 'support@tiangang-waf.com',
                'wechat' => 'TiangangWAF'
            ]
        ]);
    }
    
    /**
     * 获取可用插件列表
     */
    private function getAvailablePlugins(): array
    {
        $installed = $this->pluginManager->getAllPluginInfo();
        $plugins = [];
        
        foreach ($installed as $name => $info) {
            $plugin = $this->pluginManager->getPlugin($name);
            if ($plugin) {
                $plugins[] = [
                    'name' => $name,
                    'version' => $plugin->getVersion(),
                    'description' => $plugin->getDescription(),
                    'requires_license' => $plugin->requiresLicense(),
                    'license_valid' => $info['license_valid'] ?? false,
                    'price' => $this->getPluginPrice($name),
                    'category' => $this->getPluginCategory($name),
                    'downloads' => rand(100, 10000), // 模拟下载量
                    'rating' => round(rand(40, 50) / 10, 1), // 模拟评分
                ];
            }
        }
        
        return $plugins;
    }
    
    /**
     * 获取插件特性
     */
    private function getPluginFeatures(string $pluginName): array
    {
        $features = [
            'rate_limit' => [
                '高级频率限制',
                '分布式限流支持',
                '动态限流策略',
                'Redis 支持'
            ],
            'ip_blacklist' => [
                '威胁情报集成',
                '动态黑名单',
                'CIDR 支持',
                '地理位置过滤'
            ],
            'sql_injection' => [
                '智能 SQL 注入检测',
                '误报率低',
                '性能优化',
                '实时更新规则'
            ],
            'xss' => [
                '全面 XSS 防护',
                'DOM XSS 检测',
                '内容安全策略',
                '自动转义'
            ],
            'advanced_async' => [
                '异步检测',
                '行为分析',
                '威胁评分',
                '机器学习'
            ],
        ];
        
        return $features[$pluginName] ?? ['基础功能'];
    }
    
    /**
     * 获取插件价格
     */
    private function getPluginPrice(string $pluginName): string
    {
        $prices = [
            'rate_limit' => '¥99/月',
            'ip_blacklist' => '¥149/月',
            'sql_injection' => '免费',
            'xss' => '免费',
            'advanced_async' => '¥299/月',
        ];
        
        return $prices[$pluginName] ?? '免费';
    }
    
    /**
     * 获取插件分类
     */
    private function getPluginCategory(string $pluginName): string
    {
        $categories = [
            'rate_limit' => '频率限制',
            'ip_blacklist' => '威胁情报',
            'sql_injection' => '安全检测',
            'xss' => '安全检测',
            'advanced_async' => '行为分析',
        ];
        
        return $categories[$pluginName] ?? '其他';
    }
}

