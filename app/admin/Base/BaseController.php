<?php

namespace app\admin\Base;

use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;
use app\admin\middleware\CsrfMiddleware;

/**
 * 基础控制器
 * 
 * 提供统一的响应处理和工具方法
 */
abstract class BaseController
{
    /**
     * 返回 JSON 响应
     */
    protected function json(array $data, int $statusCode = 200): Response
    {
        return new Response($statusCode, [
            'Content-Type' => 'application/json; charset=utf-8',
        ], json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
    
    /**
     * 返回成功响应
     */
    protected function success($data = null, string $msg = 'success'): Response
    {
        return $this->json([
            'code' => 0,
            'msg' => $msg,
            'data' => $data,
        ]);
    }
    
    /**
     * 返回错误响应
     */
    protected function error(string $msg, int $code = 400, $data = null): Response
    {
        return $this->json([
            'code' => $code,
            'msg' => $msg,
            'data' => $data,
        ], $code >= 400 ? $code : 200);
    }
    
    /**
     * 返回视图
     */
    protected function view(string $view, array $data = []): Response
    {
        // 将点号替换为目录分隔符，支持嵌套视图
        $viewFile = str_replace('.', '/', $view) . '.php';
        $viewPath = realpath(__DIR__ . '/../view/' . $viewFile) ?: __DIR__ . '/../view/' . $viewFile;
        
        if (!file_exists($viewPath)) {
            $this->log('error', 'View not found', ['view' => $view, 'path' => $viewPath]);
            return $this->error('View not found: ' . $view, 404);
        }
        
        // 提取数据到变量
        extract($data, EXTR_SKIP);
        
        // 渲染视图
        ob_start();
        try {
            include $viewPath;
            $content = ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            $this->log('error', 'View render error', [
                'view' => $view,
                'error' => $e->getMessage()
            ]);
            return $this->error('View render error: ' . $e->getMessage(), 500);
        }
        
        return new Response(200, ['Content-Type' => 'text/html'], $content);
    }
    
    /**
     * 记录日志（用于控制器）
     */
    protected function log(string $level, string $message, array $context = []): void
    {
        if (function_exists('logger')) {
            logger($level, $message, $context);
        }
    }
    
    /**
     * 重定向
     */
    protected function redirect(string $url, int $code = 302): Response
    {
        return new Response($code, ['Location' => $url], '');
    }
    
    /**
     * 验证请求方法
     */
    protected function validateMethod(Request $request, string $method): bool
    {
        return strtoupper($request->method()) === strtoupper($method);
    }
    
    /**
     * 获取请求数据（自动识别 JSON 或表单数据）
     */
    protected function getRequestData(Request $request): array
    {
        $contentType = $request->header('Content-Type', '');
        
        if (strpos($contentType, 'application/json') !== false) {
            $rawBody = $request->rawBody();
            if (empty($rawBody)) {
                return [];
            }
            
            $data = json_decode($rawBody, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \InvalidArgumentException('Invalid JSON: ' . json_last_error_msg());
            }
            
            return $data ?? [];
        }
        
        return array_merge($request->get(), $request->post());
    }
    
    /**
     * 验证必需字段
     */
    protected function validateRequired(array $data, array $required): void
    {
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new \InvalidArgumentException("Field '{$field}' is required");
            }
        }
    }
    
    /**
     * 获取 CSRF Token（用于视图）
     */
    protected function getCsrfToken(Request $request): string
    {
        $csrfMiddleware = new CsrfMiddleware();
        $sessionId = $request->cookie('waf_session');
        
        if (!$sessionId) {
            // 如果没有 session，生成一个临时 session ID
            $ip = $request->getRealIp();
            $ua = $request->header('User-Agent', '');
            $timestamp = time();
            $random = bin2hex(random_bytes(16));
            $sessionId = hash('sha256', $ip . $ua . $timestamp . $random . 'csrf_temp');
        }
        
        return $csrfMiddleware->generateToken($sessionId);
    }
}

