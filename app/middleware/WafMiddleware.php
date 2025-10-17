<?php

namespace Tiangang\Waf\Middleware;

use Workerman\Protocols\Http\Request;
use Tiangang\Waf\Detectors\QuickDetector;
use Tiangang\Waf\Detectors\AsyncDetector;
use Tiangang\Waf\Core\DecisionEngine;
use Tiangang\Waf\Core\WafResult;
use Tiangang\Waf\Config\ConfigManager;
use PfinalClub\Asyncio\{create_task, gather, wait_for, sleep};

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
     * 异步处理请求
     */
    public function process(Request $request): \Generator
    {
        // 提取请求数据
        $requestData = $this->extractRequestData($request);

        // 并发执行快速检测和异步检测
        $results = yield \PfinalClub\Asyncio\gather([
            $this->quickDetector->checkAsync($requestData),
            $this->asyncDetector->check($requestData)
        ]);

        // 检查快速检测结果
        $quickResult = $results[0];
        if ($quickResult instanceof WafResult && $quickResult->isBlocked()) {
            return $quickResult;
        }

        // 检查异步检测结果
        $asyncResults = $results[1];
        if (!empty($asyncResults)) {
            return $this->decisionEngine->evaluate($asyncResults);
        }

        // 没有检测到威胁，放行
        return WafResult::allow();
    }

    /**
     * 同步提取请求数据
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
            'user_agent' => $request->header('User-Agent', ''),
            'referer' => $request->header('Referer', ''),
            'timestamp' => time(),
        ];
    }

    
    /**
     * 异步提取请求数据
     */
    private function asyncExtractRequestData(Request $request): \Generator
    {
        // 模拟异步数据提取过程
        yield sleep(0.001);
        
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
     * 异步快速检测
     */
    private function asyncQuickDetect(array $requestData): \Generator
    {
        // 模拟异步快速检测
        yield sleep(0.005);
        
        $result = $this->quickDetector->check($requestData);
        
        // 如果是 Generator，运行它
        if ($result instanceof \Generator) {
            $result = yield $result;
        }
        
        return $result;
    }
    
    /**
     * 异步深度检测
     */
    private function asyncDetect(array $requestData): \Generator
    {
        // 模拟异步深度检测
        yield sleep(0.01);
        
        $results = $this->asyncDetector->check($requestData);
        
        // 如果是 Generator，运行它
        if ($results instanceof \Generator) {
            $results = yield $results;
        }
        
        return $results;
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
