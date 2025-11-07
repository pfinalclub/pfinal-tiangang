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
];

