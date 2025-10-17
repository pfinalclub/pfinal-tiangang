<?php

namespace Tiangang\Waf\Middleware;

use Workerman\Protocols\Http\Request;
use Tiangang\Waf\Detectors\QuickDetector;
use Tiangang\Waf\Detectors\AsyncDetector;
use Tiangang\Waf\Core\DecisionEngine;
use Tiangang\Waf\Core\WafResult;
use Tiangang\Waf\Config\ConfigManager;

/**
 * WAF 中间件
 * 
 * 负责请求拦截、数据提取和 WAF 检测
 */
class WafMiddleware
{
    private ConfigManager $configManager;
    private QuickDetector $quickDetector;
    private AsyncDetector $asyncDetector;
    private DecisionEngine $decisionEngine;
    private array $config;
    
    public function __construct()
    {
        $this->configManager = new ConfigManager();
        $this->quickDetector = new QuickDetector();
        $this->asyncDetector = new AsyncDetector();
        $this->decisionEngine = new DecisionEngine();
        $this->config = $this->configManager->get('waf');
    }
    
    /**
     * 处理请求
     */
    public function process(Request $request): WafResult
    {
        // 提取请求数据
        $requestData = $this->extractRequestData($request);
        
        // 快速检测
        if ($this->config['detection']['quick_enabled']) {
            $quickResult = $this->quickDetector->check($requestData);
            if ($quickResult->isBlocked()) {
                return $quickResult;
            }
        }
        
        // 异步检测
        if ($this->config['detection']['async_enabled']) {
            $asyncResults = $this->asyncDetector->check($requestData);
            return $this->decisionEngine->evaluate($asyncResults);
        }
        
        // 默认放行
        return WafResult::allow();
    }
    
    /**
     * 提取请求数据
     */
    private function extractRequestData(Request $request): array
    {
        return [
            'ip' => $this->getRealIp($request),
            'uri' => $request->path(),
            'method' => $request->method(),
            'headers' => $request->header(),
            'query' => $request->get(),
            'post' => $request->post(),
            'cookies' => $request->cookie(),
            'user_agent' => $request->header('User-Agent', ''),
            'referer' => $request->header('Referer', ''),
            'timestamp' => time(),
        ];
    }
    
    /**
     * 获取真实 IP
     */
    private function getRealIp(Request $request): string
    {
        $headers = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR'
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $request->connection->getRemoteIp();
    }
}
