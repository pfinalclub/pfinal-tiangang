<?php

/**
 * API 路由配置
 * 
 * 定义管理界面的 API 接口路由
 */
return [
    // 仪表板 API
    '/api/dashboard' => [\app\admin\controller\DashboardController::class, 'getData'],
    '/admin/api/dashboard' => [\app\admin\controller\DashboardController::class, 'getData'],
    
    // 配置管理 API
    '/admin/api/config' => [\app\admin\controller\ConfigController::class, 'getConfig'],
    '/admin/api/config/backends' => [\app\admin\controller\ConfigController::class, 'getBackends'],
    '/admin/api/config/backend/save' => [\app\admin\controller\ConfigController::class, 'saveBackend'],
    '/admin/api/config/backend/delete' => [\app\admin\controller\ConfigController::class, 'deleteBackend'],
    '/admin/api/config/domain-mappings' => [\app\admin\controller\ConfigController::class, 'getDomainMappings'],
    '/admin/api/config/domain-mapping/save' => [\app\admin\controller\ConfigController::class, 'saveDomainMapping'],
    '/admin/api/config/domain-mapping/delete' => [\app\admin\controller\ConfigController::class, 'deleteDomainMapping'],
    '/admin/api/config/path-mappings' => [\app\admin\controller\ConfigController::class, 'getPathMappings'],
    '/admin/api/config/path-mapping/save' => [\app\admin\controller\ConfigController::class, 'savePathMapping'],
    '/admin/api/config/path-mapping/delete' => [\app\admin\controller\ConfigController::class, 'deletePathMapping'],
    '/admin/api/config/waf-rules' => [\app\admin\controller\ConfigController::class, 'getWafRules'],
    '/admin/api/config/waf-rules/update' => [\app\admin\controller\ConfigController::class, 'updateWafRules'],
    
    // 性能 API
    '/api/performance' => [\app\admin\controller\DashboardController::class, 'getPerformance'],
    '/admin/api/performance' => [\app\admin\controller\DashboardController::class, 'getPerformance'],
    
    // 安全 API
    '/api/security' => [\app\admin\controller\DashboardController::class, 'getSecurity'],
    '/admin/api/security' => [\app\admin\controller\DashboardController::class, 'getSecurity'],
    
    // 导出 API
    '/api/export' => [\app\admin\controller\DashboardController::class, 'export'],
    '/admin/api/export' => [\app\admin\controller\DashboardController::class, 'export'],
    
    // 健康检查
    '/health' => [\app\admin\controller\DashboardController::class, 'health'],
    '/admin/api/health' => [\app\admin\controller\DashboardController::class, 'health'],
];

