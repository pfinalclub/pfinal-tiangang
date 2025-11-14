<?php

namespace app\waf\middleware;

use Workerman\Protocols\Http\Request;
use app\waf\detectors\QuickDetector;
use app\waf\detectors\AsyncDetector;
use app\waf\core\DecisionEngine;
use app\waf\core\WafResult;
use app\waf\config\ConfigManager;
use app\waf\plugins\PluginManager;
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
    private PluginManager $pluginManager;
    private ?array $config;
    
    public function __construct(ConfigManager $configManager, PluginManager $pluginManager)
    {
        $this->configManager = $configManager;
        $this->pluginManager = $pluginManager;
        $this->config = $configManager->get('waf') ?? [];
        $this->quickDetector = new QuickDetector($configManager, $pluginManager);
        $this->asyncDetector = new AsyncDetector($configManager, $pluginManager);
        $this->decisionEngine = new DecisionEngine($configManager);
    }
    
    /**
     * 同步处理请求（混合架构核心）
     */
    public function processSync(Request $request): WafResult
    {
        // 提取请求数据
        $requestData = $this->extractRequestData($request);
        
        // 获取当前请求启用的 WAF 插件
        $enabledPlugins = $this->getEnabledPluginsForRequest($request);

        // 快速检测（同步），传入启用的插件列表
        $quickResult = $this->quickDetector->check($requestData, $enabledPlugins);
        if ($quickResult->isBlocked()) {
            return $quickResult;
        }

        // 异步检测（同步调用，但内部可能使用异步）
        $asyncResults = $this->asyncDetector->detectSync($requestData);
        if (!empty($asyncResults)) {
            return $this->decisionEngine->evaluate($asyncResults);
        }

        // 没有检测到威胁，放行
        return WafResult::allow();
    }

    /**
     * 异步处理请求（保留用于后台任务）
     */
    public function process(Request $request): WafResult
    {
        // 提取请求数据
        $requestData = $this->extractRequestData($request);
        
        // 获取当前请求启用的 WAF 插件
        $enabledPlugins = $this->getEnabledPluginsForRequest($request);

        // 并发执行快速检测和异步检测
        $results = \PfinalClub\Asyncio\gather(
            $this->quickDetector->checkAsync($requestData, $enabledPlugins),
            $this->asyncDetector->check($requestData)
        );

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
     * 获取当前请求启用的 WAF 插件
     * 
     * 根据域名映射配置中的 waf_plugins 来确定启用的插件
     * 如果没有找到域名映射或未配置 waf_plugins，使用插件管理器获取所有已启用的授权插件
     */
    private function getEnabledPluginsForRequest(Request $request): array
    {
        try {
            // 获取域名映射配置
            $proxyConfig = $this->configManager->get('proxy') ?? [];
            $domainMappings = $proxyConfig['domain_mappings'] ?? [];
            
            // 获取请求的 Host
            $host = $request->header('Host', '');
            if ($host) {
                // 移除端口号（如果有）
                $host = preg_replace('/:\d+$/', '', $host);
                $host = strtolower($host);
                
                // 查找匹配的域名映射
                foreach ($domainMappings as $mapping) {
                    if (!($mapping['enabled'] ?? true)) {
                        continue;
                    }
                    
                    $domain = $mapping['domain'] ?? '';
                    if (empty($domain)) {
                        continue;
                    }
                    
                    $matched = false;
                    
                    // 精确匹配
                    if (strtolower($domain) === $host) {
                        $matched = true;
                    }
                    // 通配符匹配（如 *.api.smm.cn）
                    elseif (strpos($domain, '*') !== false) {
                        $pattern = str_replace(['.', '*'], ['\.', '.*'], $domain);
                        $pattern = '/^' . $pattern . '$/i';
                        if (preg_match($pattern, $host)) {
                            $matched = true;
                        }
                    }
                    
                    if ($matched) {
                        // 找到匹配的域名映射，返回配置的 waf_plugins
                        $wafPlugins = $mapping['waf_plugins'] ?? [];
                        if (is_array($wafPlugins) && !empty($wafPlugins)) {
                            return $wafPlugins;
                        }
                        // 如果配置了但为空数组，表示不启用任何插件
                        if (is_array($wafPlugins)) {
                            return [];
                        }
                    }
                }
            }
            
            // 检查路径映射
            $pathMappings = $proxyConfig['path_mappings'] ?? [];
            $path = $request->path();
            
            foreach ($pathMappings as $mapping) {
                if (!($mapping['enabled'] ?? true)) {
                    continue;
                }
                
                $mappingPath = $mapping['path'] ?? '';
                if (empty($mappingPath)) {
                    continue;
                }
                
                // 精确匹配或前缀匹配
                if ($path === $mappingPath || str_starts_with($path, rtrim($mappingPath, '/') . '/')) {
                    $wafPlugins = $mapping['waf_plugins'] ?? [];
                    if (is_array($wafPlugins) && !empty($wafPlugins)) {
                        return $wafPlugins;
                    }
                    if (is_array($wafPlugins)) {
                        return [];
                    }
                }
            }
        } catch (\Exception $e) {
            // 配置加载失败，记录错误但继续使用全局配置
            error_log('Failed to get enabled plugins for request: ' . $e->getMessage());
        }
        
        // 没有找到匹配的映射，使用插件管理器获取所有已启用的授权插件
        $enabledPlugins = $this->pluginManager->getEnabledPlugins();
        $authorizedPlugins = $this->pluginManager->getAuthorizedPlugins();
        
        // 返回已启用且已授权的插件类名列表
        $result = [];
        foreach ($enabledPlugins as $plugin) {
            $pluginClass = get_class($plugin);
            if (in_array($pluginClass, $authorizedPlugins)) {
                $result[] = $pluginClass;
            }
        }
        
        return $result;
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
    private function asyncExtractRequestData(Request $request): array
    {
        // 模拟异步数据提取过程
        \PfinalClub\Asyncio\sleep(0.001);
        
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
    private function asyncQuickDetect(array $requestData): WafResult
    {
        // 模拟异步快速检测
        \PfinalClub\Asyncio\sleep(0.005);
        
        $result = $this->quickDetector->check($requestData);
        
        return $result;
    }
    
    /**
     * 异步深度检测
     */
    private function asyncDetect(array $requestData): array
    {
        // 模拟异步深度检测
        \PfinalClub\Asyncio\sleep(0.01);
        
        $results = $this->asyncDetector->check($requestData);
        
        return $results;
    }
    
    /**
     * 获取真实 IP（修复：加强验证，防止 IP 伪造）
     */
    private function getRealIp(Request $request): string
    {
        // 1. 获取连接的真实 IP（最可靠）
        $remoteIp = $request->connection->getRemoteIp() ?? '127.0.0.1';
        
        // 2. 验证 IP 格式
        if (!filter_var($remoteIp, FILTER_VALIDATE_IP)) {
            return '127.0.0.1';
        }
        
        // 3. 检查是否为可信代理
        $trustedProxies = $this->config['security']['trusted_proxies'] ?? ['127.0.0.1', '::1'];
        
        // 如果不是可信代理，直接返回连接 IP（防止 IP 伪造）
        if (!in_array($remoteIp, $trustedProxies)) {
            return $remoteIp;
        }
        
        // 4. 如果是可信代理，才信任代理头
        $forwardedFor = $request->header('X-Forwarded-For');
        if ($forwardedFor) {
            // 取最后一个 IP（最靠近客户端的）
            $ips = array_map('trim', explode(',', $forwardedFor));
            $ip = end($ips);
            
            // 验证 IP 格式（允许私有 IP）
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
        
        // 5. 尝试其他代理头（仅当是可信代理时）
        $realIp = $request->header('X-Real-IP');
        if ($realIp && filter_var($realIp, FILTER_VALIDATE_IP)) {
            return $realIp;
        }
        
        // 6. 回退到连接 IP
        return $remoteIp;
    }
}
