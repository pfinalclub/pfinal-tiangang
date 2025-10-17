<?php

namespace Tiangang\Waf\Gateway;

use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;
use Tiangang\Waf\Middleware\WafMiddleware;
use Tiangang\Waf\Config\ConfigManager;
use Tiangang\Waf\Logging\LogCollector;

/**
 * 天罡 WAF 核心网关
 * 
 * 负责接收请求、WAF 检测、代理转发等核心功能
 */
class TiangangGateway
{
    private ConfigManager $configManager;
    private WafMiddleware $wafMiddleware;
    private LogCollector $logCollector;
    private array $config;
    
    public function __construct()
    {
        $this->configManager = new ConfigManager();
        $this->wafMiddleware = new WafMiddleware();
        $this->logCollector = new LogCollector();
        $this->config = $this->configManager->get('waf');
    }
    
    /**
     * 处理 HTTP 请求
     */
    public function handle(Request $request): Response
    {
        $startTime = microtime(true);
        
        try {
            // 检查 WAF 是否启用
            if (!$this->config['enabled']) {
                return $this->createPassThroughResponse($request);
            }
            
            // WAF 检测
            $wafResult = $this->wafMiddleware->process($request);
            
            // 记录日志
            $this->logCollector->log($request, $wafResult, microtime(true) - $startTime);
            
            // 根据检测结果处理
            if ($wafResult->isBlocked()) {
                return $this->createBlockResponse($wafResult);
            }
            
            // 放行请求
            return $this->createPassThroughResponse($request);
            
        } catch (\Exception $e) {
            // 记录错误日志
            $this->logCollector->logError($request, $e);
            
            // 返回错误响应
            return $this->createErrorResponse($e);
        }
    }
    
    /**
     * 创建放行响应
     */
    private function createPassThroughResponse(Request $request): Response
    {
        // TODO: 实现代理转发逻辑
        return new Response(200, [
            'Content-Type' => 'text/plain',
        ], 'Request passed through WAF');
    }
    
    /**
     * 创建拦截响应
     */
    private function createBlockResponse($wafResult): Response
    {
        $statusCode = $wafResult->getStatusCode();
        $message = $wafResult->getMessage();
        
        return new Response($statusCode, [
            'Content-Type' => 'application/json',
        ], json_encode([
            'error' => 'Request Blocked by WAF',
            'message' => $message,
            'rule' => $wafResult->getRule(),
            'timestamp' => time(),
        ]));
    }
    
    /**
     * 创建错误响应
     */
    private function createErrorResponse(\Exception $e): Response
    {
        return new Response(500, [
            'Content-Type' => 'application/json',
        ], json_encode([
            'error' => 'Internal Server Error',
            'message' => $e->getMessage(),
            'timestamp' => time(),
        ]));
    }
}
