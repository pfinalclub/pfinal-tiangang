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
        
        // 确保响应是有效的 Response 对象
        if (!($response instanceof Response)) {
            throw new \RuntimeException('Invalid response from gateway');
        }
        
        // 发送响应
        $connection->send($response);
        
    } catch (\Throwable $e) {
        // 捕获所有类型的错误（包括 Error 和 Exception）
        // 错误处理（修复：区分生产/开发环境，防止信息泄露）
        $isDebug = env('APP_DEBUG', false) && env('APP_ENV', 'production') !== 'production';
        
        // 记录详细错误到日志（不返回给客户端）
        error_log(sprintf(
            'WAF Error [%s]: %s in %s:%d',
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        ));
        
        // 生成请求ID用于追踪
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
