<?php

use Webman\Route;

// WAF 管理路由
Route::group('/admin', function () {
    // 仪表板
    Route::get('/dashboard', [app\admin\controller\WafController::class, 'dashboard']);
    Route::get('/performance', [app\admin\controller\WafController::class, 'performance']);
    Route::get('/security', [app\admin\controller\WafController::class, 'security']);
    Route::get('/export', [app\admin\controller\WafController::class, 'export']);
    Route::get('/status', [app\admin\controller\WafController::class, 'status']);
    Route::get('/health', [app\admin\controller\WafController::class, 'health']);
    
    // 规则管理
    Route::group('/rules', function () {
        Route::get('/', [app\admin\controller\RuleController::class, 'index']);
        Route::post('/', [app\admin\controller\RuleController::class, 'create']);
        Route::put('/{id}', [app\admin\controller\RuleController::class, 'update']);
        Route::delete('/{id}', [app\admin\controller\RuleController::class, 'delete']);
        Route::patch('/{id}/toggle', [app\admin\controller\RuleController::class, 'toggle']);
    });
    
    // 日志管理
    Route::group('/logs', function () {
        Route::get('/', [app\admin\controller\LogController::class, 'index']);
        Route::get('/security', [app\admin\controller\LogController::class, 'security']);
        Route::get('/export', [app\admin\controller\LogController::class, 'export']);
        Route::delete('/clean', [app\admin\controller\LogController::class, 'clean']);
    });
});
