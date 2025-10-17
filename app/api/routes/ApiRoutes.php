<?php

namespace Tiangang\Waf\Api\Routes;

use Tiangang\Waf\Api\Controllers\RuleController;
use Tiangang\Waf\Api\Controllers\ConfigController;
use Tiangang\Waf\Api\Controllers\PluginController;
use Tiangang\Waf\Web\Controllers\DashboardController;
use PfinalClub\Asyncio\{create_task, gather, wait_for, sleep};

/**
 * API 路由定义
 * 
 * 负责定义所有 REST API 路由
 */
class ApiRoutes
{
    private RuleController $ruleController;
    private ConfigController $configController;
    private PluginController $pluginController;
    private DashboardController $dashboardController;

    public function __construct()
    {
        $this->ruleController = new RuleController();
        $this->configController = new ConfigController();
        $this->pluginController = new PluginController();
        $this->dashboardController = new DashboardController();
    }

    /**
     * 异步处理 API 请求
     */
    public function asyncHandleRequest(string $method, string $path, array $data = []): \Generator
    {
        // 模拟异步请求处理
        yield sleep(0.001);
        
        $route = $this->parseRoute($path);
        
        return match($route['controller']) {
            'rules' => yield $this->asyncHandleRuleRequest($method, $route, $data),
            'config' => yield $this->asyncHandleConfigRequest($method, $route, $data),
            'plugins' => yield $this->asyncHandlePluginRequest($method, $route, $data),
            'dashboard' => yield $this->asyncHandleDashboardRequest($method, $route, $data),
            default => [
                'success' => false,
                'error' => 'Controller not found',
                'code' => 404
            ]
        };
    }

    /**
     * 异步处理规则请求
     */
    private function asyncHandleRuleRequest(string $method, array $route, array $data): \Generator
    {
        yield sleep(0.002);
        
        return match($method) {
            'GET' => match($route['action']) {
                'list' => yield $this->ruleController->asyncGetRules(),
                'get' => yield $this->ruleController->asyncGetRule($route['id']),
                default => ['success' => false, 'error' => 'Action not found', 'code' => 404]
            },
            'POST' => match($route['action']) {
                'create' => yield $this->ruleController->asyncCreateRule($data),
                'test' => yield $this->ruleController->asyncTestRule($route['id'], $data),
                default => ['success' => false, 'error' => 'Action not found', 'code' => 404]
            },
            'PUT' => match($route['action']) {
                'update' => yield $this->ruleController->asyncUpdateRule($route['id'], $data),
                default => ['success' => false, 'error' => 'Action not found', 'code' => 404]
            },
            'DELETE' => match($route['action']) {
                'delete' => yield $this->ruleController->asyncDeleteRule($route['id']),
                default => ['success' => false, 'error' => 'Action not found', 'code' => 404]
            },
            default => ['success' => false, 'error' => 'Method not allowed', 'code' => 405]
        };
    }

    /**
     * 异步处理配置请求
     */
    private function asyncHandleConfigRequest(string $method, array $route, array $data): \Generator
    {
        yield sleep(0.002);
        
        return match($method) {
            'GET' => match($route['action']) {
                'list' => yield $this->configController->asyncGetConfigs(),
                'get' => yield $this->configController->asyncGetConfig($route['id']),
                'history' => yield $this->configController->asyncGetConfigHistory($route['id']),
                default => ['success' => false, 'error' => 'Action not found', 'code' => 404]
            },
            'POST' => match($route['action']) {
                'batch_update' => yield $this->configController->asyncBatchUpdateConfigs($data),
                'reload' => yield $this->configController->asyncReloadConfigs(),
                default => ['success' => false, 'error' => 'Action not found', 'code' => 404]
            },
            'PUT' => match($route['action']) {
                'update' => yield $this->configController->asyncUpdateConfig($route['id'], $data['value']),
                default => ['success' => false, 'error' => 'Action not found', 'code' => 404]
            },
            'DELETE' => match($route['action']) {
                'reset' => yield $this->configController->asyncResetConfig($route['id']),
                default => ['success' => false, 'error' => 'Action not found', 'code' => 404]
            },
            default => ['success' => false, 'error' => 'Method not allowed', 'code' => 405]
        };
    }

    /**
     * 异步处理插件请求
     */
    private function asyncHandlePluginRequest(string $method, array $route, array $data): \Generator
    {
        yield sleep(0.002);
        
        return match($method) {
            'GET' => match($route['action']) {
                'list' => yield $this->pluginController->asyncGetPlugins(),
                'get' => yield $this->pluginController->asyncGetPlugin($route['id']),
                default => ['success' => false, 'error' => 'Action not found', 'code' => 404]
            },
            'POST' => match($route['action']) {
                'install' => yield $this->pluginController->asyncInstallPlugin($data),
                'reload' => yield $this->pluginController->asyncReloadPlugins(),
                default => ['success' => false, 'error' => 'Action not found', 'code' => 404]
            },
            'PUT' => match($route['action']) {
                'update' => yield $this->pluginController->asyncUpdatePlugin($route['id'], $data),
                'enable' => yield $this->pluginController->asyncEnablePlugin($route['id']),
                'disable' => yield $this->pluginController->asyncDisablePlugin($route['id']),
                default => ['success' => false, 'error' => 'Action not found', 'code' => 404]
            },
            'DELETE' => match($route['action']) {
                'uninstall' => yield $this->pluginController->asyncUninstallPlugin($route['id']),
                default => ['success' => false, 'error' => 'Action not found', 'code' => 404]
            },
            default => ['success' => false, 'error' => 'Method not allowed', 'code' => 405]
        };
    }

    /**
     * 异步处理仪表板请求
     */
    private function asyncHandleDashboardRequest(string $method, array $route, array $data): \Generator
    {
        yield sleep(0.002);
        
        return match($method) {
            'GET' => match($route['action']) {
                'dashboard' => yield $this->dashboardController->asyncGetDashboardData(),
                'performance' => yield $this->dashboardController->asyncGetPerformanceReport($data['period'] ?? '1h'),
                'security' => yield $this->dashboardController->asyncGetSecurityReport($data['period'] ?? '1d'),
                'export' => yield $this->dashboardController->asyncExportData($data['type'] ?? 'dashboard', $data['format'] ?? 'json'),
                default => ['success' => false, 'error' => 'Action not found', 'code' => 404]
            },
            default => ['success' => false, 'error' => 'Method not allowed', 'code' => 405]
        };
    }

    /**
     * 解析路由
     */
    private function parseRoute(string $path): array
    {
        $segments = explode('/', trim($path, '/'));
        
        $controller = $segments[0] ?? 'dashboard';
        $action = $segments[1] ?? 'list';
        $id = $segments[2] ?? null;
        
        return [
            'controller' => $controller,
            'action' => $action,
            'id' => $id
        ];
    }

    /**
     * 异步获取 API 文档
     */
    public function asyncGetApiDocumentation(): \Generator
    {
        // 模拟异步获取 API 文档
        yield sleep(0.005);
        
        return [
            'success' => true,
            'data' => [
                'title' => '天罡 WAF API 文档',
                'version' => '1.0.0',
                'base_url' => '/api/v1',
                'endpoints' => [
                    'rules' => [
                        'GET /api/v1/rules' => '获取所有规则',
                        'GET /api/v1/rules/{id}' => '获取单个规则',
                        'POST /api/v1/rules' => '创建规则',
                        'PUT /api/v1/rules/{id}' => '更新规则',
                        'DELETE /api/v1/rules/{id}' => '删除规则',
                        'POST /api/v1/rules/{id}/test' => '测试规则'
                    ],
                    'config' => [
                        'GET /api/v1/config' => '获取所有配置',
                        'GET /api/v1/config/{key}' => '获取单个配置',
                        'PUT /api/v1/config/{key}' => '更新配置',
                        'POST /api/v1/config/batch' => '批量更新配置',
                        'DELETE /api/v1/config/{key}/reset' => '重置配置',
                        'POST /api/v1/config/reload' => '重新加载配置'
                    ],
                    'plugins' => [
                        'GET /api/v1/plugins' => '获取所有插件',
                        'GET /api/v1/plugins/{id}' => '获取单个插件',
                        'POST /api/v1/plugins' => '安装插件',
                        'PUT /api/v1/plugins/{id}' => '更新插件',
                        'PUT /api/v1/plugins/{id}/enable' => '启用插件',
                        'PUT /api/v1/plugins/{id}/disable' => '禁用插件',
                        'DELETE /api/v1/plugins/{id}' => '卸载插件',
                        'POST /api/v1/plugins/reload' => '重新加载插件'
                    ],
                    'dashboard' => [
                        'GET /api/v1/dashboard' => '获取仪表板数据',
                        'GET /api/v1/dashboard/performance' => '获取性能报告',
                        'GET /api/v1/dashboard/security' => '获取安全报告',
                        'GET /api/v1/dashboard/export' => '导出数据'
                    ]
                ],
                'authentication' => [
                    'type' => 'Bearer Token',
                    'header' => 'Authorization: Bearer {token}'
                ],
                'rate_limits' => [
                    'requests_per_minute' => 100,
                    'requests_per_hour' => 1000
                ]
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * 异步健康检查
     */
    public function asyncHealthCheck(): \Generator
    {
        // 模拟异步健康检查
        yield sleep(0.001);
        
        return [
            'success' => true,
            'data' => [
                'status' => 'healthy',
                'version' => '1.0.0',
                'uptime' => '7 days, 12 hours',
                'components' => [
                    'api' => 'healthy',
                    'database' => 'healthy',
                    'cache' => 'healthy',
                    'monitoring' => 'healthy'
                ],
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ];
    }
}
