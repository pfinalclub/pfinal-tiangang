<?php

namespace app\admin\controller;

use Workerman\Protocols\Http\Request;
use app\admin\Base\BaseController;
use app\admin\service\ConfigService;
use app\waf\config\ConfigManager;

/**
 * 配置管理控制器
 * 
 * 负责管理 WAF 配置、后端映射、保护规则等
 */
class ConfigController extends BaseController
{
    private ConfigService $configService;
    private ConfigManager $configManager;
    
    public function __construct()
    {
        $this->configService = new ConfigService();
        $this->configManager = new ConfigManager();
    }
    
    /**
     * 获取所有配置
     */
    public function getConfig(Request $request)
    {
        try {
            $config = $this->configService->getAllConfig();
            return $this->success($config);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * 获取后端服务列表
     */
    public function getBackends(Request $request)
    {
        try {
            $backends = $this->configService->getBackends();
            $stats = $this->configService->getBackendStats();
            return $this->success([
                'backends' => $backends,
                'stats' => $stats,
            ]);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * 添加或更新后端服务
     */
    public function saveBackend(Request $request)
    {
        if (!$this->validateMethod($request, 'POST')) {
            return $this->error('Method not allowed', 405);
        }
        
        try {
            $data = $this->getRequestData($request);
            $result = $this->configService->saveBackend($data);
            return $this->success($result, 'Backend saved successfully');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 400);
        } catch (\RuntimeException $e) {
            $this->log('error', 'Failed to save backend', ['error' => $e->getMessage()]);
            return $this->error('Save failed: ' . $e->getMessage(), 500);
        } catch (\Exception $e) {
            $this->log('error', 'Unexpected error saving backend', ['error' => $e->getMessage()]);
            return $this->error('Save failed: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * 删除后端服务
     */
    public function deleteBackend(Request $request)
    {
        if (!$this->validateMethod($request, 'POST')) {
            return $this->error('Method not allowed', 405);
        }
        
        try {
            $name = $request->get('name');
            if (empty($name)) {
                return $this->error('Name parameter is required', 400);
            }
            
            $result = $this->configService->deleteBackend($name);
            return $this->success($result, 'Backend deleted successfully');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 400);
        } catch (\RuntimeException $e) {
            $this->log('error', 'Failed to delete backend', ['error' => $e->getMessage()]);
            return $this->error('Delete failed: ' . $e->getMessage(), 500);
        } catch (\Exception $e) {
            $this->log('error', 'Unexpected error deleting backend', ['error' => $e->getMessage()]);
            return $this->error('Delete failed: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * 获取域名映射列表
     */
    public function getDomainMappings(Request $request)
    {
        try {
            $mappings = $this->configService->getDomainMappings();
            return $this->success(['mappings' => $mappings]);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * 添加或更新域名映射
     */
    public function saveDomainMapping(Request $request)
    {
        if (!$this->validateMethod($request, 'POST')) {
            return $this->error('Method not allowed', 405);
        }
        
        try {
            $data = $this->getRequestData($request);
            $result = $this->configService->saveDomainMapping($data);
            return $this->success($result, 'Domain mapping saved successfully');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 400);
        } catch (\RuntimeException $e) {
            $this->log('error', 'Failed to save domain mapping', ['error' => $e->getMessage()]);
            return $this->error('Save failed: ' . $e->getMessage(), 500);
        } catch (\Exception $e) {
            $this->log('error', 'Unexpected error saving domain mapping', ['error' => $e->getMessage()]);
            return $this->error('Save failed: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * 删除域名映射
     */
    public function deleteDomainMapping(Request $request)
    {
        if (!$this->validateMethod($request, 'POST')) {
            return $this->error('Method not allowed', 405);
        }
        
        try {
            $domain = $request->get('domain');
            if (empty($domain)) {
                return $this->error('Domain parameter is required', 400);
            }
            
            $result = $this->configService->deleteDomainMapping($domain);
            return $this->success($result, 'Domain mapping deleted successfully');
        } catch (\Exception $e) {
            return $this->error('Delete failed: ' . $e->getMessage());
        }
    }
    
    /**
     * 获取路径映射列表
     */
    public function getPathMappings(Request $request)
    {
        try {
            $mappings = $this->configService->getPathMappings();
            return $this->success(['mappings' => $mappings]);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * 添加或更新路径映射
     */
    public function savePathMapping(Request $request)
    {
        if (!$this->validateMethod($request, 'POST')) {
            return $this->error('Method not allowed', 405);
        }
        
        try {
            $data = $this->getRequestData($request);
            $result = $this->configService->savePathMapping($data);
            return $this->success($result, 'Path mapping saved successfully');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 400);
        } catch (\RuntimeException $e) {
            $this->log('error', 'Failed to save path mapping', ['error' => $e->getMessage()]);
            return $this->error('Save failed: ' . $e->getMessage(), 500);
        } catch (\Exception $e) {
            $this->log('error', 'Unexpected error saving path mapping', ['error' => $e->getMessage()]);
            return $this->error('Save failed: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * 删除路径映射
     */
    public function deletePathMapping(Request $request)
    {
        if (!$this->validateMethod($request, 'POST')) {
            return $this->error('Method not allowed', 405);
        }
        
        try {
            $path = $request->get('path');
            if (empty($path)) {
                return $this->error('Path parameter is required', 400);
            }
            
            $result = $this->configService->deletePathMapping($path);
            return $this->success($result, 'Path mapping deleted successfully');
        } catch (\Exception $e) {
            return $this->error('Delete failed: ' . $e->getMessage());
        }
    }
    
    /**
     * 获取 WAF 规则配置
     */
    public function getWafRules(Request $request)
    {
        try {
            $rules = $this->configService->getWafRules();
            return $this->success(['rules' => $rules]);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * 更新 WAF 规则配置
     */
    public function updateWafRules(Request $request)
    {
        if (!$this->validateMethod($request, 'POST')) {
            return $this->error('Method not allowed', 405);
        }
        
        try {
            $data = $this->getRequestData($request);
            $result = $this->configService->updateWafRules($data);
            return $this->success($result, 'WAF rules updated successfully');
        } catch (\Exception $e) {
            return $this->error('Update failed: ' . $e->getMessage());
        }
    }
    
    /**
     * 配置管理页面
     */
    public function configPage(Request $request)
    {
        return $this->view('config.index', [
            'csrfToken' => $this->getCsrfToken($request),
        ]);
    }
    
    /**
     * 路径映射表单页面
     */
    public function mappingForm(Request $request)
    {
        $path = $request->get('path');
        $isEdit = !empty($path);
        
        $proxyConfig = $this->configManager->get('proxy') ?? [];
        $backends = $proxyConfig['backends'] ?? [];
        $mappings = $proxyConfig['path_mappings'] ?? [];
        
        $mapping = null;
        if ($isEdit) {
            foreach ($mappings as $m) {
                if (($m['path'] ?? '') === $path) {
                    $mapping = $m;
                    break;
                }
            }
        }
        
        return $this->view('config.mapping_form', [
            'isEdit' => $isEdit,
            'path' => $path,
            'mapping' => $mapping,
            'backends' => $backends,
            'csrfToken' => $this->getCsrfToken($request),
        ]);
    }
    
    /**
     * 域名映射表单页面
     */
    public function domainForm(Request $request)
    {
        $domain = $request->get('domain');
        $isEdit = !empty($domain);
        
        $proxyConfig = $this->configManager->get('proxy') ?? [];
        $backends = $proxyConfig['backends'] ?? [];
        $mappings = $proxyConfig['domain_mappings'] ?? [];
        
        $mapping = null;
        if ($isEdit) {
            foreach ($mappings as $m) {
                if (($m['domain'] ?? '') === $domain) {
                    $mapping = $m;
                    break;
                }
            }
        }
        
        return $this->view('config.domain_form', [
            'isEdit' => $isEdit,
            'domain' => $domain,
            'mapping' => $mapping,
            'backends' => $backends,
            'csrfToken' => $this->getCsrfToken($request),
        ]);
    }
    
    /**
     * 后端服务表单页面
     */
    public function backendForm(Request $request)
    {
        $name = $request->get('name');
        $isEdit = !empty($name);
        
        $proxyConfig = $this->configManager->get('proxy') ?? [];
        $backends = $proxyConfig['backends'] ?? [];
        
        $backend = null;
        if ($isEdit) {
            foreach ($backends as $b) {
                if (($b['name'] ?? '') === $name) {
                    $backend = $b;
                    break;
                }
            }
        }
        
        return $this->view('config.backend_form', [
            'isEdit' => $isEdit,
            'name' => $name,
            'backend' => $backend,
        ]);
    }
    
    /**
     * 生成代理配置文件内容（保留用于视图生成，实际保存逻辑在 ConfigService 中）
     */
    private function generateProxyConfigFile(array $config): string
    {
        $content = "<?php\n\nreturn [\n";
        
        // 基础配置
        $content .= "    // 代理基础配置\n";
        $content .= "    'enabled' => " . var_export($config['enabled'] ?? true, true) . ",\n";
        $content .= "    'timeout' => " . var_export($config['timeout'] ?? 30, true) . ",\n";
        $content .= "    'connect_timeout' => " . var_export($config['connect_timeout'] ?? 5, true) . ",\n";
        $content .= "    'verify_ssl' => " . var_export($config['verify_ssl'] ?? true, true) . ",\n";
        $content .= "    'stream_threshold' => " . var_export($config['stream_threshold'] ?? 1048576, true) . ",\n";
        $content .= "    \n";
        
        // 后端服务配置
        $content .= "    // 后端服务配置\n";
        $content .= "    'backends' => [\n";
        foreach ($config['backends'] ?? [] as $backend) {
            $content .= "        [\n";
            $content .= "            'name' => " . var_export($backend['name'] ?? '', true) . ",\n";
            $content .= "            'url' => " . var_export($backend['url'] ?? '', true) . ",\n";
            $content .= "            'weight' => " . var_export($backend['weight'] ?? 1, true) . ",\n";
            $content .= "            'health_url' => " . var_export($backend['health_url'] ?? '', true) . ",\n";
            $content .= "            'health_timeout' => " . var_export($backend['health_timeout'] ?? 5, true) . ",\n";
            $content .= "            'recovery_time' => " . var_export($backend['recovery_time'] ?? 60, true) . ",\n";
            if (isset($backend['health_check'])) {
                $content .= "            'health_check' => " . var_export($backend['health_check'], true) . ",\n";
            }
            $content .= "        ],\n";
        }
        $content .= "    ],\n";
        $content .= "    \n";
        
        // 域名映射配置
        $content .= "    // 域名映射配置（域名 -> 后端服务名称）- 主要路由方式\n";
        $content .= "    // 示例：'crm.smm.cn' -> 'crm-backend', 'erp.smm.cn' -> 'erp-backend'\n";
        $content .= "    // 支持精确匹配和通配符匹配（如 '*.api.smm.cn'）\n";
        $content .= "    // 优先级：域名映射 > 路径映射 > 默认后端\n";
        $content .= "    'domain_mappings' => [\n";
        foreach ($config['domain_mappings'] ?? [] as $mapping) {
            $content .= "        [\n";
            $content .= "            'domain' => " . var_export($mapping['domain'] ?? '', true) . ",\n";
            $content .= "            'backend' => " . var_export($mapping['backend'] ?? '', true) . ",\n";
            if (isset($mapping['waf_rules'])) {
                $content .= "            'waf_rules' => " . var_export($mapping['waf_rules'], true) . ",\n";
            }
            $content .= "            'enabled' => " . var_export($mapping['enabled'] ?? true, true) . ",\n";
            $content .= "        ],\n";
        }
        $content .= "    ],\n";
        $content .= "    \n";
        
        // 路径映射配置
        $content .= "    // 路径映射配置（路径前缀 -> 后端服务名称）- 补充路由方式\n";
        $content .= "    // 示例：'/app1' -> 'primary', '/app2' -> 'secondary'\n";
        $content .= "    // 如果请求路径匹配映射，则转发到对应的后端服务\n";
        $content .= "    // 优先级：域名映射 > 路径映射 > 默认后端\n";
        $content .= "    'path_mappings' => [\n";
        foreach ($config['path_mappings'] ?? [] as $mapping) {
            $content .= "        [\n";
            $content .= "            'path' => " . var_export($mapping['path'] ?? '', true) . ",\n";
            $content .= "            'backend' => " . var_export($mapping['backend'] ?? '', true) . ",\n";
            $content .= "            'strip_prefix' => " . var_export($mapping['strip_prefix'] ?? false, true) . ",\n";
            if (isset($mapping['waf_rules'])) {
                $content .= "            'waf_rules' => " . var_export($mapping['waf_rules'], true) . ",\n";
            }
            $content .= "            'enabled' => " . var_export($mapping['enabled'] ?? true, true) . ",\n";
            $content .= "        ],\n";
        }
        $content .= "    ],\n";
        $content .= "    \n";
        
        // 默认后端
        $content .= "    // 默认后端（当没有域名或路径匹配时使用）\n";
        $content .= "    'default_backend' => " . var_export($config['default_backend'] ?? 'primary', true) . ",\n";
        $content .= "    \n";
        
        // 其他配置（简化处理，保留原有值）
        if (isset($config['load_balancer'])) {
            $content .= "    // 负载均衡配置\n";
            $content .= "    'load_balancer' => " . var_export($config['load_balancer'], true) . ",\n";
            $content .= "    \n";
        }
        
        // 安全配置
        if (isset($config['security'])) {
            $content .= "    // 安全配置\n";
            $content .= "    'security' => " . var_export($config['security'], true) . ",\n";
            $content .= "    \n";
        }
        
        // 日志配置
        if (isset($config['logging'])) {
            $content .= "    // 日志配置\n";
            $content .= "    'logging' => " . var_export($config['logging'], true) . ",\n";
            $content .= "    \n";
        }
        
        // 监控配置
        if (isset($config['monitoring'])) {
            $content .= "    // 监控配置\n";
            $content .= "    'monitoring' => " . var_export($config['monitoring'], true) . ",\n";
            $content .= "    \n";
        }
        
        // 缓存配置
        if (isset($config['cache'])) {
            $content .= "    // 缓存配置\n";
            $content .= "    'cache' => " . var_export($config['cache'], true) . ",\n";
            $content .= "    \n";
        }
        
        // 重试配置
        if (isset($config['retry'])) {
            $content .= "    // 重试配置\n";
            $content .= "    'retry' => " . var_export($config['retry'], true) . ",\n";
            $content .= "    \n";
        }
        
        // 限流配置
        if (isset($config['rate_limit'])) {
            $content .= "    // 限流配置\n";
            $content .= "    'rate_limit' => " . var_export($config['rate_limit'], true) . ",\n";
        }
        
        $content .= "];\n";
        
        return $content;
    }
    
}

