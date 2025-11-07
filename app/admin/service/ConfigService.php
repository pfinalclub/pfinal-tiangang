<?php

namespace app\admin\service;

use app\admin\Base\BaseService;
use app\waf\config\ConfigManager;
use app\waf\proxy\BackendManager;

/**
 * 配置服务
 * 
 * 处理配置相关的业务逻辑
 */
class ConfigService extends BaseService
{
    private ConfigManager $configManager;
    private BackendManager $backendManager;
    
    public function __construct()
    {
        $this->configManager = new ConfigManager();
        $this->backendManager = new BackendManager();
    }
    
    /**
     * 获取所有配置
     */
    public function getAllConfig(): array
    {
        $wafConfig = $this->configManager->get('waf') ?? [];
        $proxyConfig = $this->getProxyConfig();
        
        return [
            'waf' => $wafConfig,
            'proxy' => [
                'backends' => $proxyConfig['backends'] ?? [],
                'domain_mappings' => $proxyConfig['domain_mappings'] ?? [],
                'path_mappings' => $proxyConfig['path_mappings'] ?? [],
                'default_backend' => $proxyConfig['default_backend'] ?? 'primary',
                'load_balancer' => $proxyConfig['load_balancer'] ?? [],
            ],
        ];
    }
    
    /**
     * 获取后端服务列表
     */
    public function getBackends(): array
    {
        $proxyConfig = $this->getProxyConfig();
        $backends = $proxyConfig['backends'] ?? [];
        
        // 获取后端健康状态
        $healthStatus = $this->backendManager->healthCheck();
        $healthMap = [];
        foreach ($healthStatus as $status) {
            $healthMap[$status['backend']] = $status;
        }
        
        // 合并健康状态
        foreach ($backends as &$backend) {
            $name = $backend['name'] ?? '';
            if (isset($healthMap[$name])) {
                $backend['health'] = $healthMap[$name];
            }
        }
        
        return $backends;
    }
    
    /**
     * 获取后端服务统计
     */
    public function getBackendStats(): array
    {
        return $this->backendManager->getBackendStats();
    }
    
    /**
     * 保存后端服务
     */
    public function saveBackend(array $data): array
    {
        // 验证数据
        $this->validate($data, [
            'name' => ['required' => true, 'type' => 'string'],
            'url' => ['required' => true, 'type' => 'url'],
        ]);
        
        $proxyConfig = $this->getProxyConfig();
        $backends = $proxyConfig['backends'] ?? [];
        
        $name = trim($data['name']);
        $url = trim($data['url']);
        
        // 查找现有后端
        $index = $this->findBackendIndex($backends, $name);
        
        if ($index !== null) {
            // 更新现有后端
            $backends[$index] = array_merge($backends[$index], [
                'url' => $url,
                'weight' => $data['weight'] ?? $backends[$index]['weight'] ?? 1,
                'health_url' => $data['health_url'] ?? $backends[$index]['health_url'] ?? $url . '/health',
                'health_timeout' => $data['health_timeout'] ?? $backends[$index]['health_timeout'] ?? 5,
                'recovery_time' => $data['recovery_time'] ?? $backends[$index]['recovery_time'] ?? 60,
                'health_check' => $data['health_check'] ?? $backends[$index]['health_check'] ?? [
                    'expected_status' => 'ok',
                    'expected_content' => 'healthy'
                ],
            ]);
        } else {
            // 添加新后端
            $backends[] = [
                'name' => $name,
                'url' => $url,
                'weight' => $data['weight'] ?? 1,
                'health_url' => $data['health_url'] ?? $url . '/health',
                'health_timeout' => $data['health_timeout'] ?? 5,
                'recovery_time' => $data['recovery_time'] ?? 60,
                'health_check' => $data['health_check'] ?? [
                    'expected_status' => 'ok',
                    'expected_content' => 'healthy'
                ],
            ];
        }
        
        // 保存配置
        if (!$this->saveProxyConfig('backends', $backends)) {
            throw new \RuntimeException('Failed to save backend configuration');
        }
        
        return ['backends' => $backends];
    }
    
    /**
     * 查找后端索引
     */
    private function findBackendIndex(array $backends, string $name): ?int
    {
        foreach ($backends as $index => $backend) {
            if (($backend['name'] ?? '') === $name) {
                return $index;
            }
        }
        return null;
    }
    
    /**
     * 删除后端服务
     */
    public function deleteBackend(string $name): array
    {
        $proxyConfig = $this->getProxyConfig();
        
        // 检查是否被使用
        $domainMappings = $proxyConfig['domain_mappings'] ?? [];
        $pathMappings = $proxyConfig['path_mappings'] ?? [];
        
        // 检查域名映射
        foreach ($domainMappings as $mapping) {
            if (($mapping['backend'] ?? '') === $name) {
                throw new \InvalidArgumentException("Backend is in use by domain mapping: {$mapping['domain']}");
            }
        }
        
        // 检查路径映射
        foreach ($pathMappings as $mapping) {
            if (($mapping['backend'] ?? '') === $name) {
                throw new \InvalidArgumentException("Backend is in use by path mapping: {$mapping['path']}");
            }
        }
        
        // 检查是否为默认后端
        if (($proxyConfig['default_backend'] ?? '') === $name) {
            throw new \InvalidArgumentException('Cannot delete default backend');
        }
        
        $backends = $proxyConfig['backends'] ?? [];
        
        // 移除匹配的后端
        $backends = array_filter($backends, function($backend) use ($name) {
            return ($backend['name'] ?? '') !== $name;
        });
        
        // 重新索引数组
        $backends = array_values($backends);
        
        // 保存配置
        if (!$this->saveProxyConfig('backends', $backends)) {
            throw new \RuntimeException('Failed to delete backend configuration');
        }
        
        return ['backends' => $backends];
    }
    
    /**
     * 获取域名映射列表
     */
    public function getDomainMappings(): array
    {
        $proxyConfig = $this->getProxyConfig();
        return $proxyConfig['domain_mappings'] ?? [];
    }
    
    /**
     * 保存域名映射
     */
    public function saveDomainMapping(array $data): array
    {
        // 验证数据
        $this->validate($data, [
            'domain' => ['required' => true, 'type' => 'string'],
            'backend' => ['required' => true, 'type' => 'string'],
        ]);
        
        $domain = trim($data['domain']);
        if (empty($domain)) {
            throw new \InvalidArgumentException('Domain cannot be empty');
        }
        
        // 验证域名格式（支持通配符）
        if (strpos($domain, '*') !== false) {
            if (substr_count($domain, '*') > 1 || strpos($domain, '*') !== 0) {
                throw new \InvalidArgumentException('Wildcard domain must start with * and only one wildcard allowed');
            }
        } else {
            if (!preg_match('/^[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?)*$/', $domain)) {
                throw new \InvalidArgumentException('Invalid domain format');
            }
        }
        
        // 验证后端是否存在
        $this->validateBackendExists($data['backend']);
        
        // 读取现有映射
        $proxyConfig = $this->getProxyConfig();
        $mappings = $proxyConfig['domain_mappings'] ?? [];
        
        // 查找现有映射
        $index = $this->findDomainMappingIndex($mappings, $domain);
        
        if ($index !== null) {
            // 更新现有映射
            $mappings[$index] = array_merge($mappings[$index], [
                'backend' => $data['backend'],
                'waf_rules' => $data['waf_rules'] ?? [],
                'enabled' => $data['enabled'] ?? true,
            ]);
        } else {
            // 添加新映射
            $mappings[] = [
                'domain' => $domain,
                'backend' => $data['backend'],
                'waf_rules' => $data['waf_rules'] ?? [],
                'enabled' => $data['enabled'] ?? true,
            ];
        }
        
        // 保存配置
        if (!$this->saveProxyConfig('domain_mappings', $mappings)) {
            throw new \RuntimeException('Failed to save domain mapping configuration');
        }
        
        return ['mappings' => $mappings];
    }
    
    /**
     * 删除域名映射
     */
    public function deleteDomainMapping(string $domain): array
    {
        $proxyConfig = $this->getProxyConfig();
        $mappings = $proxyConfig['domain_mappings'] ?? [];
        
        // 移除匹配的映射
        $mappings = array_filter($mappings, function($mapping) use ($domain) {
            return ($mapping['domain'] ?? '') !== $domain;
        });
        
        // 重新索引数组
        $mappings = array_values($mappings);
        
        // 保存配置
        if (!$this->saveProxyConfig('domain_mappings', $mappings)) {
            throw new \RuntimeException('Failed to delete domain mapping configuration');
        }
        
        return ['mappings' => $mappings];
    }
    
    /**
     * 查找域名映射索引
     */
    private function findDomainMappingIndex(array $mappings, string $domain): ?int
    {
        foreach ($mappings as $index => $mapping) {
            if (($mapping['domain'] ?? '') === $domain) {
                return $index;
            }
        }
        return null;
    }
    
    /**
     * 获取路径映射列表
     */
    public function getPathMappings(): array
    {
        $proxyConfig = $this->getProxyConfig();
        return $proxyConfig['path_mappings'] ?? [];
    }
    
    /**
     * 保存路径映射
     */
    public function savePathMapping(array $data): array
    {
        // 验证数据
        $this->validate($data, [
            'path' => ['required' => true, 'type' => 'string'],
            'backend' => ['required' => true, 'type' => 'string'],
        ]);
        
        $path = trim($data['path']);
        if (empty($path) || !str_starts_with($path, '/')) {
            throw new \InvalidArgumentException('Path must start with /');
        }
        
        // 验证后端是否存在
        $this->validateBackendExists($data['backend']);
        
        // 读取现有映射
        $proxyConfig = $this->getProxyConfig();
        $mappings = $proxyConfig['path_mappings'] ?? [];
        
        // 查找现有映射
        $index = $this->findPathMappingIndex($mappings, $path);
        
        if ($index !== null) {
            // 更新现有映射
            $mappings[$index] = array_merge($mappings[$index], [
                'backend' => $data['backend'],
                'strip_prefix' => $data['strip_prefix'] ?? false,
                'waf_rules' => $data['waf_rules'] ?? [],
                'enabled' => $data['enabled'] ?? true,
            ]);
        } else {
            // 添加新映射
            $mappings[] = [
                'path' => $path,
                'backend' => $data['backend'],
                'strip_prefix' => $data['strip_prefix'] ?? false,
                'waf_rules' => $data['waf_rules'] ?? [],
                'enabled' => $data['enabled'] ?? true,
            ];
        }
        
        // 保存配置
        if (!$this->saveProxyConfig('path_mappings', $mappings)) {
            throw new \RuntimeException('Failed to save path mapping configuration');
        }
        
        return ['mappings' => $mappings];
    }
    
    /**
     * 删除路径映射
     */
    public function deletePathMapping(string $path): array
    {
        $proxyConfig = $this->getProxyConfig();
        $mappings = $proxyConfig['path_mappings'] ?? [];
        
        // 移除匹配的映射
        $mappings = array_filter($mappings, function($mapping) use ($path) {
            return ($mapping['path'] ?? '') !== $path;
        });
        
        // 重新索引数组
        $mappings = array_values($mappings);
        
        // 保存配置
        if (!$this->saveProxyConfig('path_mappings', $mappings)) {
            throw new \RuntimeException('Failed to delete path mapping configuration');
        }
        
        return ['mappings' => $mappings];
    }
    
    /**
     * 查找路径映射索引
     */
    private function findPathMappingIndex(array $mappings, string $path): ?int
    {
        foreach ($mappings as $index => $mapping) {
            if (($mapping['path'] ?? '') === $path) {
                return $index;
            }
        }
        return null;
    }
    
    /**
     * 获取 WAF 规则配置
     */
    public function getWafRules(): array
    {
        $wafConfig = $this->configManager->get('waf') ?? [];
        return $wafConfig['rules'] ?? [];
    }
    
    /**
     * 更新 WAF 规则配置
     */
    public function updateWafRules(array $data): array
    {
        // 这里可以添加保存逻辑
        return ['success' => true];
    }
    
    /**
     * 获取配置文件路径
     */
    private function getConfigFilePath(): string
    {
        // 从 app/admin/service 向上三级到项目根目录，然后进入 config 目录
        return realpath(__DIR__ . '/../../../config/proxy.php') ?: __DIR__ . '/../../../config/proxy.php';
    }
    
    /**
     * 获取代理配置
     */
    private function getProxyConfig(): array
    {
        return $this->configManager->get('proxy') ?? [];
    }
    
    /**
     * 验证后端是否存在
     */
    private function validateBackendExists(string $backendName): void
    {
        $proxyConfig = $this->getProxyConfig();
        $backends = $proxyConfig['backends'] ?? [];
        
        foreach ($backends as $backend) {
            if (($backend['name'] ?? '') === $backendName) {
                return; // 后端存在
            }
        }
        
        throw new \InvalidArgumentException("Backend '{$backendName}' not found");
    }
    
    /**
     * 保存代理配置到文件
     */
    private function saveProxyConfig(string $key, $value): bool
    {
        try {
            $configFile = $this->getConfigFilePath();
            
            if (!file_exists($configFile)) {
                throw new \RuntimeException("Config file not found: {$configFile}");
            }
            
            // 读取现有配置
            $config = require $configFile;
            
            // 更新配置
            $config[$key] = $value;
            
            // 生成 PHP 配置代码（保留注释和格式）
            $content = $this->generateProxyConfigFile($config);
            
            // 写入文件
            return file_put_contents($configFile, $content) !== false;
            
        } catch (\Exception $e) {
            $this->log('error', 'Failed to save proxy config', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * 生成代理配置文件内容
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
        
        if (isset($config['security'])) {
            $content .= "    // 安全配置\n";
            $content .= "    'security' => " . var_export($config['security'], true) . ",\n";
            $content .= "    \n";
        }
        
        if (isset($config['logging'])) {
            $content .= "    // 日志配置\n";
            $content .= "    'logging' => " . var_export($config['logging'], true) . ",\n";
            $content .= "    \n";
        }
        
        if (isset($config['monitoring'])) {
            $content .= "    // 监控配置\n";
            $content .= "    'monitoring' => " . var_export($config['monitoring'], true) . ",\n";
            $content .= "    \n";
        }
        
        if (isset($config['cache'])) {
            $content .= "    // 缓存配置\n";
            $content .= "    'cache' => " . var_export($config['cache'], true) . ",\n";
            $content .= "    \n";
        }
        
        if (isset($config['retry'])) {
            $content .= "    // 重试配置\n";
            $content .= "    'retry' => " . var_export($config['retry'], true) . ",\n";
            $content .= "    \n";
        }
        
        if (isset($config['rate_limit'])) {
            $content .= "    // 限流配置\n";
            $content .= "    'rate_limit' => " . var_export($config['rate_limit'], true) . ",\n";
        }
        
        $content .= "];\n";
        
        return $content;
    }
}

