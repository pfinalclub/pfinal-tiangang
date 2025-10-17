<?php

use Workerman\Worker;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;
use Tiangang\Waf\Gateway\TiangangGateway;

require_once __DIR__ . '/vendor/autoload.php';

// 加载环境变量
if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

// 创建 HTTP 服务器
$worker = new Worker('http://' . env('SERVER_HOST', '0.0.0.0') . ':' . env('SERVER_PORT', 8787));
$worker->count = env('SERVER_WORKERS', 4);
$worker->name = 'Tiangang WAF';

// 设置进程标题
if (function_exists('cli_set_process_title')) {
    cli_set_process_title('tiangang-waf');
}

// 请求处理回调
$worker->onMessage = function ($connection, Request $request) {
    try {
        // 创建网关实例
        $gateway = new TiangangGateway();
        
        // 同步处理请求（稳定可靠）
        $response = $gateway->handle($request);
        
        // 发送响应
        $connection->send($response);
        
    } catch (Exception $e) {
        // 错误处理
        $errorResponse = new Response(500, [
            'Content-Type' => 'application/json',
        ], json_encode([
            'error' => 'Internal Server Error',
            'message' => $e->getMessage(),
            'timestamp' => time(),
        ]));
        
        $connection->send($errorResponse);
    }
};

// 启动服务器
Worker::runAll();
