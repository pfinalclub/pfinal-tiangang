<?php

/**
 * Web 路由配置
 * 
 * 定义管理界面的页面路由
 */
return [
    // 认证路由
    '/admin/login' => [\app\admin\controller\AuthController::class, 'login'],
    '/admin/auth/login' => [\app\admin\controller\AuthController::class, 'doLogin'],
    '/admin/auth/logout' => [\app\admin\controller\AuthController::class, 'logout'],
    
    // 仪表板路由
    '/admin' => [\app\admin\controller\DashboardController::class, 'generateDashboardHtml'],
    '/admin/' => [\app\admin\controller\DashboardController::class, 'generateDashboardHtml'],
    '/admin/dashboard' => [\app\admin\controller\DashboardController::class, 'generateDashboardHtml'],
    
    // 配置管理路由
    '/admin/config' => [\app\admin\controller\ConfigController::class, 'configPage'],
    '/admin/config/mapping-form' => [\app\admin\controller\ConfigController::class, 'mappingForm'],
    '/admin/config/domain-form' => [\app\admin\controller\ConfigController::class, 'domainForm'],
    '/admin/config/backend-form' => [\app\admin\controller\ConfigController::class, 'backendForm'],
    
    // 插件管理路由
    '/admin/plugins' => [\app\admin\controller\PluginController::class, 'index'],
    '/admin/plugins/upload' => [\app\admin\controller\PluginController::class, 'upload'],
    '/admin/plugins/install' => [\app\admin\controller\PluginController::class, 'install'],
    '/admin/plugins/uninstall' => [\app\admin\controller\PluginController::class, 'uninstall'],
    '/admin/plugins/activate' => [\app\admin\controller\PluginController::class, 'activate'],
    '/admin/plugins/deactivate' => [\app\admin\controller\PluginController::class, 'deactivate'],
    '/admin/plugins/config' => [\app\admin\controller\PluginController::class, 'config'],
    
    // 插件市场路由
    '/admin/plugins/market' => [\app\admin\controller\PluginMarketController::class, 'index'],
    '/admin/plugins/market/detail' => [\app\admin\controller\PluginMarketController::class, 'detail'],
    '/admin/plugins/license' => [\app\admin\controller\PluginMarketController::class, 'licenseStatus'],
    '/admin/plugins/license/validate' => [\app\admin\controller\PluginMarketController::class, 'validateLicense'],
    '/admin/plugins/license/renew' => [\app\admin\controller\PluginMarketController::class, 'renewLicense'],
];

