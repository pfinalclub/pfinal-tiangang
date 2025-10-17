<?php

namespace app\admin\middleware;

use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;
use app\admin\controller\AuthController;

/**
 * 认证中间件
 * 
 * 检查用户是否已登录，未登录则重定向到登录页面
 */
class AuthMiddleware
{
    /**
     * 处理请求
     */
    public function process(Request $request, callable $next): Response
    {
        // 不需要认证的路径
        $publicPaths = [
            '/admin/login',
            '/admin/auth/login',
            '/admin/auth/logout',
            '/health',
            '/api/health'
        ];

        $path = $request->path();
        
        // 如果是公开路径，直接放行
        if (in_array($path, $publicPaths)) {
            return $next($request);
        }

        // 检查是否为管理界面请求
        if ($this->isAdminRequest($request)) {
            // 检查是否已登录
            if (!$this->isAuthenticated($request)) {
                return $this->redirectToLogin($request);
            }
        }

        return $next($request);
    }

    /**
     * 检查是否为管理界面请求
     */
    private function isAdminRequest(Request $request): bool
    {
        $path = $request->path();
        return str_starts_with($path, '/admin');
    }

    /**
     * 检查用户是否已认证
     */
    private function isAuthenticated(Request $request): bool
    {
        $authController = new AuthController();
        return $authController->isLoggedIn($request);
    }

    /**
     * 重定向到登录页面
     */
    private function redirectToLogin(Request $request): Response
    {
        // 如果是AJAX请求，返回JSON响应
        if ($request->header('X-Requested-With') === 'XMLHttpRequest' || 
            $request->header('Accept') === 'application/json') {
            return new Response(401, ['Content-Type' => 'application/json'], json_encode([
                'code' => 401,
                'msg' => '未登录或会话已过期',
                'data' => ['redirect' => '/admin/login']
            ]));
        }

        // 普通请求重定向到登录页面
        return new Response(302, ['Location' => '/admin/login'], '');
    }
}
