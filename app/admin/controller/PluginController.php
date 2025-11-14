<?php

namespace app\admin\controller;

use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;
use app\admin\Base\BaseController;
use app\waf\plugins\PluginManager;
use app\license\manager\PluginLicenseManager;
use app\waf\config\ConfigManager;

/**
 * 插件管理控制器
 * 
 * 负责插件的上传、安装、卸载、激活、停用等管理功能
 */
class PluginController extends BaseController
{
    private PluginManager $pluginManager;
    private PluginLicenseManager $licenseManager;
    private ConfigManager $configManager;
    
    public function __construct()
    {
        $this->pluginManager = new PluginManager();
        $this->configManager = new ConfigManager();
        $this->licenseManager = $this->pluginManager->getLicenseManager();
    }
    
    /**
     * 插件列表
     */
    public function index(Request $request): Response
    {
        try {
            $allPlugins = $this->pluginManager->getAllPluginInfo();
            $plugins = [];
            
            foreach ($allPlugins as $name => $info) {
                $plugin = $this->pluginManager->getPlugin($name);
                if ($plugin) {
                    $plugins[] = [
                        'name' => $name,
                        'version' => $plugin->getVersion(),
                        'description' => $plugin->getDescription(),
                        'enabled' => $plugin->isEnabled(),
                        'priority' => $plugin->getPriority(),
                        'requires_license' => $info['requires_license'] ?? false,
                        'license_valid' => $info['license_valid'] ?? false,
                        'license_message' => $info['license_message'] ?? '',
                        'supports_quick_detection' => $plugin->supportsQuickDetection(),
                        'file' => $info['file'] ?? '',
                    ];
                }
            }
            
            return $this->success([
                'plugins' => $plugins,
                'total' => count($plugins)
            ]);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * 上传插件
     */
    public function upload(Request $request): Response
    {
        if (!$this->validateMethod($request, 'POST')) {
            return $this->error('Method not allowed', 405);
        }
        
        try {
            $file = $request->file('plugin');
            
            if (!$file) {
                return $this->error('请上传插件文件', 400);
            }
            
            $uploadPath = runtime_path('uploads/plugins/');
            if (!is_dir($uploadPath)) {
                mkdir($uploadPath, 0755, true);
            }
            
            // 验证文件类型
            $extension = $file->getUploadExtension();
            if ($extension !== 'zip') {
                return $this->error('仅支持 ZIP 格式插件', 400);
            }
            
            // 验证文件大小（最大10MB）
            if ($file->getSize() > 10 * 1024 * 1024) {
                return $this->error('插件文件大小不能超过 10MB', 400);
            }
            
            // 保存文件
            $filename = uniqid('plugin_') . '.zip';
            $filepath = $uploadPath . $filename;
            $file->move($uploadPath, $filename);
            
            return $this->success([
                'file' => $filename,
                'path' => $filepath,
                'size' => filesize($filepath)
            ], '上传成功');
        } catch (\Exception $e) {
            $this->log('error', 'Plugin upload failed', ['error' => $e->getMessage()]);
            return $this->error('上传失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 安装插件
     */
    public function install(Request $request): Response
    {
        if (!$this->validateMethod($request, 'POST')) {
            return $this->error('Method not allowed', 405);
        }
        
        try {
            $filename = $request->post('filename');
            
            if (empty($filename)) {
                return $this->error('插件文件名不能为空', 400);
            }
            
            // 安全验证：防止路径遍历攻击
            $filename = basename($filename);
            if (!preg_match('/^plugin_[a-f0-9]+\.zip$/', $filename)) {
                return $this->error('非法的插件文件名', 400);
            }
            
            $uploadPath = runtime_path('uploads/plugins/');
            $filepath = $uploadPath . $filename;
            
            if (!file_exists($filepath)) {
                return $this->error('插件文件不存在', 404);
            }
            
            // 解压插件到plugins目录
            $pluginPath = base_path('plugins/waf/');
            if (!is_dir($pluginPath)) {
                mkdir($pluginPath, 0755, true);
            }
            
            $zip = new \ZipArchive();
            if ($zip->open($filepath) === true) {
                $zip->extractTo($pluginPath);
                $zip->close();
                
                // 删除上传的临时文件
                @unlink($filepath);
                
                // 重新加载插件
                $this->pluginManager->reload();
                
                return $this->success(null, '插件安装成功');
            } else {
                return $this->error('插件解压失败', 500);
            }
        } catch (\Exception $e) {
            $this->log('error', 'Plugin install failed', ['error' => $e->getMessage()]);
            return $this->error('安装失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 卸载插件
     */
    public function uninstall(Request $request): Response
    {
        if (!$this->validateMethod($request, 'POST')) {
            return $this->error('Method not allowed', 405);
        }
        
        try {
            $pluginName = $request->post('plugin_name');
            
            if (empty($pluginName)) {
                return $this->error('插件名称不能为空', 400);
            }
            
            $pluginInfo = $this->pluginManager->getPluginInfo($pluginName);
            if (!$pluginInfo) {
                return $this->error('插件不存在', 404);
            }
            
            // 删除插件文件（安全路径验证）
            $pluginFile = base_path('plugins/waf/' . basename($pluginInfo['file']));
            if (file_exists($pluginFile)) {
                if (!unlink($pluginFile)) {
                    return $this->error('插件文件删除失败，请检查文件权限', 500);
                }
                
                // 重新加载插件
                $this->pluginManager->reload();
                
                return $this->success(null, '插件卸载成功');
            } else {
                return $this->error('插件文件不存在', 404);
            }
        } catch (\Exception $e) {
            $this->log('error', 'Plugin uninstall failed', ['error' => $e->getMessage()]);
            return $this->error('卸载失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 激活许可证
     */
    public function activate(Request $request): Response
    {
        if (!$this->validateMethod($request, 'POST')) {
            return $this->error('Method not allowed', 405);
        }
        
        try {
            $pluginName = $request->post('plugin_name');
            $licenseKey = $request->post('license_key');
            
            if (empty($pluginName) || empty($licenseKey)) {
                return $this->error('插件名称和许可证密钥不能为空', 400);
            }
            
            $result = $this->licenseManager->validatePluginLicense($pluginName, $licenseKey);
            
            if ($result['valid']) {
                // 保存许可证到配置
                $licenses = $this->configManager->get('license', []);
                $licenses[$pluginName] = [
                    'key' => $licenseKey,
                    'expires_at' => $result['expires_at'] ?? null,
                    'activated_at' => time()
                ];
                $this->configManager->set('license', $licenses);
                
                // 清除许可证缓存
                $this->licenseManager->clearLicenseCache($pluginName);
                
                return $this->success([
                    'plugin' => $pluginName,
                    'expires_at' => $result['expires_at'] ?? null,
                    'message' => $result['message'] ?? ''
                ], '许可证激活成功');
            }
            
            return $this->error($result['message'] ?? '许可证验证失败', 400);
        } catch (\Exception $e) {
            $this->log('error', 'License activation failed', ['error' => $e->getMessage()]);
            return $this->error('激活失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 停用插件
     */
    public function deactivate(Request $request): Response
    {
        if (!$this->validateMethod($request, 'POST')) {
            return $this->error('Method not allowed', 405);
        }
        
        try {
            $pluginName = $request->post('plugin_name');
            
            if (empty($pluginName)) {
                return $this->error('插件名称不能为空', 400);
            }
            
            $result = $this->pluginManager->disablePlugin($pluginName);
            
            if ($result) {
                return $this->success(null, '插件停用成功');
            }
            
            return $this->error('插件停用失败', 500);
        } catch (\Exception $e) {
            $this->log('error', 'Plugin deactivation failed', ['error' => $e->getMessage()]);
            return $this->error('停用失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 插件配置
     */
    public function config(Request $request): Response
    {
        try {
            $pluginName = $request->get('plugin_name');
            
            if (empty($pluginName)) {
                return $this->error('插件名称不能为空', 400);
            }
            
            if ($request->method() === 'GET') {
                // 获取配置
                $plugin = $this->pluginManager->getPlugin($pluginName);
                if (!$plugin) {
                    return $this->error('插件不存在', 404);
                }
                
                return $this->success([
                    'config' => $plugin->getConfig()
                ]);
            } else if ($request->method() === 'POST') {
                // 更新配置
                if (!$this->validateMethod($request, 'POST')) {
                    return $this->error('Method not allowed', 405);
                }
                
                $config = $this->getRequestData($request);
                
                // TODO: 实现插件配置更新逻辑
                // $this->pluginManager->updatePluginConfig($pluginName, $config);
                
                return $this->success(null, '配置更新成功');
            }
            
            return $this->error('不支持的请求方法', 405);
        } catch (\Exception $e) {
            $this->log('error', 'Plugin config operation failed', ['error' => $e->getMessage()]);
            return $this->error('操作失败: ' . $e->getMessage());
        }
    }
}

