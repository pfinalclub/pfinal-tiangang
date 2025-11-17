<?php

namespace app\admin\controller;

use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;
use app\admin\Base\BaseController;
use app\waf\config\ConfigManager;
use GuzzleHttp\Client as HttpClient;

/**
 * Nginx 配置管理控制器
 * 
 * 提供配置导出接口，供 Nginx Lua 脚本读取
 */
class NginxController extends BaseController
{
    private ConfigManager $configManager;
    
    public function __construct()
    {
        $this->configManager = new ConfigManager();
    }
    
    /**
     * 导出 Nginx Lua 可读的配置（JSON 格式）
     * 
     * GET /admin/api/nginx/config
     */
    public function exportConfig(Request $request): Response
    {
        try {
            $proxyConfig = $this->configManager->get('proxy') ?? [];
            
            // 提取域名映射
            $domainMappings = [];
            foreach ($proxyConfig['domain_mappings'] ?? [] as $mapping) {
                // 跳过未启用的映射
                if (!($mapping['enabled'] ?? true)) {
                    continue;
                }
                
                $domain = strtolower($mapping['domain'] ?? '');
                if (empty($domain)) {
                    continue;
                }
                
                $domainMappings[$domain] = [
                    'backend' => $mapping['backend'] ?? '',
                    'preserve_host' => $mapping['preserve_host'] ?? true,
                    'waf_rules' => $mapping['waf_rules'] ?? [],
                    'enabled' => true,
                ];
            }
            
            // 默认后端
            $defaultBackend = $proxyConfig['default_backend'] ?? 'primary';
            
            // 如果默认后端是名称，解析为 URL
            if (!filter_var($defaultBackend, FILTER_VALIDATE_URL)) {
                $backends = $proxyConfig['backends'] ?? [];
                foreach ($backends as $backend) {
                    if (($backend['name'] ?? '') === $defaultBackend) {
                        $defaultBackend = $backend['url'] ?? 'http://127.0.0.1:8080';
                        break;
                    }
                }
                
                // 如果还是找不到，使用默认值
                if (!filter_var($defaultBackend, FILTER_VALIDATE_URL)) {
                    $defaultBackend = 'http://127.0.0.1:8080';
                }
            }
            
            $config = [
                'version' => time(), // 用于缓存失效判断
                'updated_at' => date('Y-m-d H:i:s'),
                'default_backend' => $defaultBackend,
                'domain_mappings' => $domainMappings,
                'total_mappings' => count($domainMappings),
            ];
            
            // 记录日志
            if (function_exists('logger')) {
                logger('info', 'Nginx config exported', [
                    'total_mappings' => count($domainMappings),
                    'domains' => array_keys($domainMappings)
                ]);
            }
            
            return $this->success($config);
            
        } catch (\Exception $e) {
            if (function_exists('logger')) {
                logger('error', 'Failed to export nginx config', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
            
            return $this->error('导出配置失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 重载 Nginx 配置（清除 Lua 缓存）
     * 
     * POST /admin/api/nginx/reload
     */
    public function reloadConfig(Request $request): Response
    {
        try {
            // 获取后置 Nginx 地址
            $nginxHost = env('NGINX_BACKEND_HOST', '127.0.0.1');
            $nginxPort = env('NGINX_BACKEND_PORT', '8788');
            $reloadUrl = "http://{$nginxHost}:{$nginxPort}/_internal/refresh-config";
            
            // 调用 Nginx 内部接口刷新缓存
            $client = new HttpClient([
                'timeout' => 5,
                'connect_timeout' => 2,
            ]);
            
            try {
                $response = $client->get($reloadUrl);
                $statusCode = $response->getStatusCode();
                $body = (string)$response->getBody();
                
                if ($statusCode === 200) {
                    if (function_exists('logger')) {
                        logger('info', 'Nginx config reloaded successfully');
                    }
                    
                    return $this->success([
                        'message' => 'Nginx 配置已重载',
                        'timestamp' => time(),
                        'response' => $body
                    ]);
                } else {
                    if (function_exists('logger')) {
                        logger('warning', 'Nginx reload returned non-200 status', [
                            'status' => $statusCode,
                            'body' => $body
                        ]);
                    }
                    
                    return $this->error("重载失败，状态码: {$statusCode}");
                }
                
            } catch (\Exception $httpError) {
                // HTTP 请求失败，但不一定是错误（可能 Nginx 还没配置内部接口）
                if (function_exists('logger')) {
                    logger('warning', 'Failed to call nginx reload endpoint', [
                        'error' => $httpError->getMessage(),
                        'url' => $reloadUrl
                    ]);
                }
                
                return $this->success([
                    'message' => '配置已更新，Nginx 将在 60 秒内自动重载',
                    'note' => '如需立即生效，请手动重启 Nginx 或配置内部重载接口'
                ]);
            }
            
        } catch (\Exception $e) {
            if (function_exists('logger')) {
                logger('error', 'Nginx config reload failed', [
                    'error' => $e->getMessage()
                ]);
            }
            
            return $this->error('重载失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 获取 Nginx 配置状态
     * 
     * GET /admin/api/nginx/status
     */
    public function getStatus(Request $request): Response
    {
        try {
            $proxyConfig = $this->configManager->get('proxy') ?? [];
            $domainMappings = $proxyConfig['domain_mappings'] ?? [];
            
            $enabledCount = 0;
            $disabledCount = 0;
            
            foreach ($domainMappings as $mapping) {
                if ($mapping['enabled'] ?? true) {
                    $enabledCount++;
                } else {
                    $disabledCount++;
                }
            }
            
            $status = [
                'nginx_mode' => 'frontend_proxy',  // 前置 Nginx 模式
                'lua_enabled' => true,
                'config_file' => realpath(__DIR__ . '/../../../config/proxy.php'),
                'config_updated' => date('Y-m-d H:i:s', filemtime(realpath(__DIR__ . '/../../../config/proxy.php'))),
                'mappings' => [
                    'total' => count($domainMappings),
                    'enabled' => $enabledCount,
                    'disabled' => $disabledCount,
                ],
                'cache_ttl' => 60,  // 缓存时间（秒）
            ];
            
            return $this->success($status);
            
        } catch (\Exception $e) {
            return $this->error('获取状态失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 测试后端连接
     * 
     * POST /admin/api/nginx/test-backend
     */
    public function testBackend(Request $request): Response
    {
        try {
            $postData = json_decode($request->rawBody(), true);
            $backend = $postData['backend'] ?? '';
            
            if (empty($backend)) {
                return $this->error('后端地址不能为空');
            }
            
            if (!filter_var($backend, FILTER_VALIDATE_URL)) {
                return $this->error('后端地址格式不正确');
            }
            
            // 测试连接
            $client = new HttpClient([
                'timeout' => 5,
                'connect_timeout' => 2,
                'http_errors' => false,
            ]);
            
            $startTime = microtime(true);
            $response = $client->get($backend);
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            
            $statusCode = $response->getStatusCode();
            $success = $statusCode >= 200 && $statusCode < 400;
            
            return $this->success([
                'reachable' => true,
                'status_code' => $statusCode,
                'response_time' => $duration . 'ms',
                'success' => $success,
                'message' => $success ? '连接成功' : "连接失败，状态码: {$statusCode}"
            ]);
            
        } catch (\Exception $e) {
            return $this->success([
                'reachable' => false,
                'error' => $e->getMessage(),
                'message' => '无法连接到后端: ' . $e->getMessage()
            ]);
        }
    }
}

