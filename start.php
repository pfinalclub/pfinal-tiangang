<?php

use Workerman\Worker;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;
use app\waf\TiangangGateway;
use app\admin\config\Database;

require_once __DIR__ . '/vendor/autoload.php';

// 加载环境变量
if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

// 初始化数据库连接（使用 Illuminate Database ORM）
Database::initialize();

// 获取配置
$serverHost = env('SERVER_HOST', '0.0.0.0');
$adminPort = env('ADMIN_PORT', 8989);  // 管理界面端口
$proxyPort = env('PROXY_PORT', 8787);    // WAF 代理端口
$workerCount = env('SERVER_WORKERS', 4);

// ========================================
// 1. 管理界面 Worker（8989 端口）
// ========================================
$adminWorker = new Worker('http://' . $serverHost . ':' . $adminPort);
$adminWorker->count = 1; // 管理界面只需要 1 个进程
$adminWorker->name = 'Tiangang WAF Admin';

$adminWorker->onMessage = function ($connection, Request $request) {
    try {
        // 创建网关实例
        $gateway = new TiangangGateway();
        
        // 管理界面请求，强制走管理路由
        $response = $gateway->handleAdminRequest($request);
        
        // 确保响应是有效的 Response 对象
        if (!($response instanceof Response)) {
            throw new \RuntimeException('Invalid response from gateway');
        }
        
        // 发送响应
        $connection->send($response);
        
    } catch (\Throwable $e) {
        // 错误处理
        $isDebug = env('APP_DEBUG', false) && env('APP_ENV', 'production') !== 'production';
        
        error_log(sprintf(
            'Admin Error [%s]: %s in %s:%d',
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        ));
        
        $requestId = uniqid('req_', true);
        
        $errorResponse = new Response(500, [
            'Content-Type' => 'application/json; charset=utf-8',
        ], json_encode([
            'error' => 'Internal Server Error',
            'message' => $isDebug 
                ? $e->getMessage() 
                : 'An unexpected error occurred. Please contact support.',
            'request_id' => $requestId,
            'timestamp' => time(),
        ], JSON_UNESCAPED_SLASHES));
        
        $connection->send($errorResponse);
    }
};

// ========================================
// 2. WAF 代理 Worker（8787 端口）
// ========================================
$proxyWorker = new Worker('http://' . $serverHost . ':' . $proxyPort);
$proxyWorker->count = $workerCount;
$proxyWorker->name = 'Tiangang WAF Proxy';

$proxyWorker->onMessage = function ($connection, Request $request) {
    try {
        // 创建网关实例
        $gateway = new TiangangGateway();
        
        // WAF 代理请求，只处理代理逻辑
        $response = $gateway->handleProxyRequest($request);
        
        // 确保响应是有效的 Response 对象
        if (!($response instanceof Response)) {
            throw new \RuntimeException('Invalid response from gateway');
        }
        
        // 发送响应
        $connection->send($response);
        
    } catch (\Throwable $e) {
        // 错误处理
        $isDebug = env('APP_DEBUG', false) && env('APP_ENV', 'production') !== 'production';
        
        error_log(sprintf(
            'Proxy Error [%s]: %s in %s:%d',
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        ));
        
        $requestId = uniqid('req_', true);
        
        $errorResponse = new Response(500, [
            'Content-Type' => 'application/json; charset=utf-8',
        ], json_encode([
            'error' => 'Internal Server Error',
            'message' => $isDebug 
                ? $e->getMessage() 
                : 'An unexpected error occurred. Please contact support.',
            'request_id' => $requestId,
            'timestamp' => time(),
        ], JSON_UNESCAPED_SLASHES));
        
        $connection->send($errorResponse);
    }
};

// 启动服务器
Worker::runAll();
