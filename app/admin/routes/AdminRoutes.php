<?php

namespace app\admin\routes;

use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;

/**
 * 统一的管理路由
 * 
 * 负责处理所有管理界面的路由
 * 使用配置文件管理路由，符合现代 MVC 架构
 */
class AdminRoutes
{
    private array $webRoutes;
    private array $apiRoutes;

    public function __construct()
    {
        // 加载路由配置
        $this->webRoutes = require __DIR__ . '/web.php';
        $this->apiRoutes = require __DIR__ . '/api.php';
    }

    /**
     * 处理管理请求
     */
    public function handleRequest(Request $request): Response
    {
        $path = $request->path();
        
        // 直接分发路由（中间件已在 TiangangGateway 中应用）
        return $this->dispatch($request, $path);
    }
    
    /**
     * 路由分发
     */
    private function dispatch(Request $request, string $path): Response
    {
        // 处理静态资源请求（返回 404，不触发代理）
        $staticPaths = [
            '/favicon.ico',
            '/robots.txt',
            '/.well-known',
        ];
        
        foreach ($staticPaths as $staticPath) {
            if ($path === $staticPath || str_starts_with($path, $staticPath . '/')) {
                return $this->handleNotFound($request);
            }
        }
        
        // 合并路由
        $routes = array_merge($this->webRoutes, $this->apiRoutes);
        
        // 查找路由
        if (isset($routes[$path])) {
            $route = $routes[$path];
            
            if (is_array($route) && count($route) === 2) {
                [$controllerClass, $method] = $route;
                
                if (!class_exists($controllerClass)) {
                    $this->logError("Controller class not found: {$controllerClass}", $path);
                    return $this->handleNotFound($request);
                }
                
                if (!method_exists($controllerClass, $method)) {
                    $this->logError("Method not found: {$controllerClass}::{$method}", $path);
                    return $this->handleNotFound($request);
                }
                
                try {
                    $controller = new $controllerClass();
                    return $controller->$method($request);
                } catch (\Throwable $e) {
                    $this->logError("Controller error: " . $e->getMessage(), $path, $e);
                    return $this->handleError($request, $e);
                }
            }
        }
        
        // 处理根路径和 dashboard 路径（向后兼容）
        if ($path === '/' || $path === '/dashboard') {
            return $this->handleLegacyRoute($request, $path);
        }
        
        return $this->handleNotFound($request);
    }
    
    /**
     * 记录错误日志
     */
    private function logError(string $message, string $path, ?\Throwable $exception = null): void
    {
        if (function_exists('logger')) {
            logger('error', $message, [
                'path' => $path,
                'exception' => $exception ? $exception->getMessage() : null,
                'trace' => $exception ? $exception->getTraceAsString() : null
            ]);
        }
    }
    
    /**
     * 处理错误
     */
    private function handleError(Request $request, \Throwable $e): Response
    {
        $isApiRequest = str_starts_with($request->path(), '/api') || str_starts_with($request->path(), '/admin/api');
        
        if ($isApiRequest) {
            return new Response(500, [
                'Content-Type' => 'application/json; charset=utf-8'
            ], json_encode([
                'code' => 500,
                'msg' => 'Internal Server Error',
                'data' => null,
                'timestamp' => time()
            ], JSON_UNESCAPED_UNICODE));
        }
        
        return new Response(500, [
            'Content-Type' => 'text/html; charset=utf-8'
        ], '<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>500 - 服务器错误</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            background: #F5F6FA;
        }
        .error-container {
            text-align: center;
        }
        h1 { font-size: 72px; color: #364A63; margin: 0; }
        p { font-size: 18px; color: #666; margin: 20px 0; }
        a { color: #5FB878; text-decoration: none; }
    </style>
</head>
<body>
    <div class="error-container">
        <h1>500</h1>
        <p>服务器内部错误</p>
        <a href="/admin">返回首页</a>
    </div>
</body>
</html>');
    }
    
    /**
     * 处理遗留路由（向后兼容）
     */
    private function handleLegacyRoute(Request $request, string $path): Response
    {
        // 重定向到新的路由
        if ($path === '/' || $path === '/dashboard') {
            return new Response(302, ['Location' => '/admin/dashboard'], '');
        }
        
        return $this->handleNotFound($request);
    }

    /**
     * 处理404错误
     */
    private function handleNotFound(Request $request): Response
    {
        $isApiRequest = str_starts_with($request->path(), '/api') || str_starts_with($request->path(), '/admin/api');
        
        if ($isApiRequest) {
            return new Response(404, [
                'Content-Type' => 'application/json; charset=utf-8'
            ], json_encode([
                'code' => 404,
                'msg' => 'Not Found',
                'data' => null,
            'timestamp' => time()
            ], JSON_UNESCAPED_UNICODE));
        }
        
        // Web 请求返回 HTML 404 页面
        return new Response(404, [
            'Content-Type' => 'text/html; charset=utf-8'
        ], '<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - 页面未找到</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            background: #F5F6FA;
        }
        .error-container {
            text-align: center;
        }
        h1 { font-size: 72px; color: #364A63; margin: 0; }
        p { font-size: 18px; color: #666; margin: 20px 0; }
        a { color: #5FB878; text-decoration: none; }
    </style>
</head>
<body>
    <div class="error-container">
        <h1>404</h1>
        <p>页面未找到</p>
        <a href="/admin">返回首页</a>
    </div>
</body>
</html>');
    }
}

