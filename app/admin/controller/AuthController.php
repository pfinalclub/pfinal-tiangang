<?php

namespace app\admin\controller;

use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;

/**
 * 认证控制器
 * 
 * 处理用户登录、登出、会话管理等功能
 */
class AuthController
{
    /**
     * 显示登录页面
     */
    public function login(Request $request): Response
    {
        // 如果已经登录，重定向到仪表板
        if ($this->isLoggedIn($request)) {
            return new Response(302, ['Location' => '/admin/dashboard'], '');
        }

        $html = $this->generateLoginPage();
        return new Response(200, ['Content-Type' => 'text/html'], $html);
    }

    /**
     * 处理登录请求
     */
    public function doLogin(Request $request): Response
    {
        // 解析POST数据
        parse_str($request->rawBody(), $postData);
        $username = $postData['username'] ?? '';
        $password = $postData['password'] ?? '';
        $remember = isset($postData['remember']) && $postData['remember'] === 'on';

        // 验证用户名和密码
        if ($this->validateCredentials($username, $password)) {
            // 创建会话
            $sessionId = $this->createSession($request, $username, $remember);
            
            // 设置Cookie头
            $expires = $remember ? time() + (30 * 24 * 3600) : time() + (24 * 3600);
            $cookieValue = "waf_session={$sessionId}; Path=/; HttpOnly; Max-Age=" . ($expires - time());
            
            return new Response(200, [
                'Content-Type' => 'application/json',
                'Set-Cookie' => $cookieValue
            ], json_encode([
                'code' => 0,
                'msg' => '登录成功',
                'data' => ['redirect' => '/admin/dashboard']
            ]));
        } else {
            return new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'code' => 1,
                'msg' => '用户名或密码错误'
            ]));
        }
    }

    /**
     * 处理登出请求
     */
    public function logout(Request $request): Response
    {
        $this->destroySession($request);
        
        // 清除Cookie
        $clearCookie = "waf_session=; Path=/; HttpOnly; Max-Age=0";
        
        return new Response(302, [
            'Location' => '/admin/login',
            'Set-Cookie' => $clearCookie
        ], '');
    }

    /**
     * 检查是否已登录
     */
    public function isLoggedIn(Request $request): bool
    {
        $sessionId = $request->cookie('waf_session');
        if (!$sessionId) {
            return false;
        }

        // 检查会话是否有效
        $sessionData = $this->getSessionData($sessionId);
        return $sessionData !== null && $sessionData['expires'] > time();
    }

    /**
     * 验证用户凭据
     */
    private function validateCredentials(string $username, string $password): bool
    {
        // 默认管理员账户（生产环境应该从数据库验证）
        $validUsers = [
            'admin' => password_hash('admin123', PASSWORD_DEFAULT),
            'waf' => password_hash('waf2024', PASSWORD_DEFAULT),
            'tiangang' => password_hash('tiangang2024', PASSWORD_DEFAULT)
        ];

        if (!isset($validUsers[$username])) {
            return false;
        }

        return password_verify($password, $validUsers[$username]);
    }

    /**
     * 创建用户会话
     */
    private function createSession(Request $request, string $username, bool $remember = false): string
    {
        $sessionId = $this->generateSessionId();
        $expires = $remember ? time() + (30 * 24 * 3600) : time() + (24 * 3600); // 30天或1天

        $sessionData = [
            'username' => $username,
            'login_time' => time(),
            'expires' => $expires,
            'ip' => $this->getClientIp($request),
            'user_agent' => $request->header('User-Agent', '')
        ];

        $this->saveSessionData($sessionId, $sessionData);

        // 在 Workerman 环境中，Cookie 通过 Response 头设置
        // 这里只是保存会话数据，Cookie 会在响应中设置
        
        return $sessionId;
    }

    /**
     * 销毁用户会话
     */
    private function destroySession(Request $request): void
    {
        $sessionId = $request->cookie('waf_session');
        if ($sessionId) {
            $this->deleteSessionData($sessionId);
        }

        // Cookie 清除通过 Response 头处理
    }

    /**
     * 生成会话ID
     */
    private function generateSessionId(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * 获取会话数据
     */
    private function getSessionData(string $sessionId): ?array
    {
        $sessionFile = $this->getSessionFilePath($sessionId);
        if (!file_exists($sessionFile)) {
            return null;
        }

        $data = json_decode(file_get_contents($sessionFile), true);
        if (!$data || $data['expires'] < time()) {
            unlink($sessionFile);
            return null;
        }

        return $data;
    }

    /**
     * 保存会话数据
     */
    private function saveSessionData(string $sessionId, array $data): void
    {
        $sessionFile = $this->getSessionFilePath($sessionId);
        $sessionDir = dirname($sessionFile);
        
        if (!is_dir($sessionDir)) {
            mkdir($sessionDir, 0755, true);
        }

        file_put_contents($sessionFile, json_encode($data));
    }

    /**
     * 删除会话数据
     */
    private function deleteSessionData(string $sessionId): void
    {
        $sessionFile = $this->getSessionFilePath($sessionId);
        if (file_exists($sessionFile)) {
            unlink($sessionFile);
        }
    }

    /**
     * 获取会话文件路径
     */
    private function getSessionFilePath(string $sessionId): string
    {
        return runtime_path('sessions/' . substr($sessionId, 0, 2) . '/' . $sessionId . '.json');
    }

    /**
     * 获取客户端IP地址
     */
    private function getClientIp(Request $request): string
    {
        // 检查各种可能的IP头
        $headers = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($headers as $header) {
            $ip = $request->header($header);
            if ($ip && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }

        // 如果都没有找到，返回连接IP
        return $request->connection->getRemoteIp() ?? '127.0.0.1';
    }

    /**
     * 生成登录页面HTML
     */
    private function generateLoginPage(): string
    {
        return '<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>天罡 WAF - 管理登录</title>
    <link href="//unpkg.com/layui@2.12.1/dist/css/layui.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            width: 100%;
            max-width: 400px;
            position: relative;
            overflow: hidden;
        }
        
        .login-container::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2);
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-logo {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
            font-weight: bold;
        }
        
        .login-title {
            font-size: 24px;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 8px;
        }
        
        .login-subtitle {
            font-size: 14px;
            color: #718096;
        }
        
        .login-form {
            margin-top: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: #2d3748;
            margin-bottom: 8px;
        }
        
        .form-input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: #f8fafc;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-checkbox {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .form-checkbox input {
            margin-right: 8px;
        }
        
        .form-checkbox label {
            font-size: 14px;
            color: #4a5568;
            cursor: pointer;
        }
        
        .login-button {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .login-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .login-button:active {
            transform: translateY(0);
        }
        
        .login-button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .login-footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }
        
        .login-footer p {
            font-size: 12px;
            color: #718096;
        }
        
        .demo-accounts {
            background: #f7fafc;
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
            font-size: 12px;
        }
        
        .demo-accounts h4 {
            color: #2d3748;
            margin-bottom: 10px;
            font-size: 14px;
        }
        
        .demo-accounts p {
            color: #4a5568;
            margin: 5px 0;
        }
        
        .loading {
            display: none;
            text-align: center;
            margin-top: 20px;
        }
        
        .error-message {
            background: #fed7d7;
            color: #c53030;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            display: none;
        }
        
        .success-message {
            background: #c6f6d5;
            color: #2f855a;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            display: none;
        }
        
        @media (max-width: 480px) {
            .login-container {
                margin: 20px;
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="login-logo">🛡️</div>
            <h1 class="login-title">天罡 WAF</h1>
            <p class="login-subtitle">Web应用防火墙管理控制台</p>
        </div>
        
        <div class="error-message" id="errorMessage"></div>
        <div class="success-message" id="successMessage"></div>
        
        <form class="login-form" id="loginForm">
            <div class="form-group">
                <label class="form-label" for="username">用户名</label>
                <input 
                    type="text" 
                    id="username" 
                    name="username" 
                    class="form-input" 
                    placeholder="请输入用户名"
                    required
                    autocomplete="username"
                >
            </div>
            
            <div class="form-group">
                <label class="form-label" for="password">密码</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    class="form-input" 
                    placeholder="请输入密码"
                    required
                    autocomplete="current-password"
                >
            </div>
            
            <div class="form-checkbox">
                <input type="checkbox" id="remember" name="remember">
                <label for="remember">记住我</label>
            </div>
            
            <button type="submit" class="login-button" id="loginButton">
                登录
            </button>
        </form>
        
        <div class="loading" id="loading">
            <i class="layui-icon layui-icon-loading layui-anim layui-anim-rotate layui-anim-loop"></i>
            <span style="margin-left: 10px;">正在登录...</span>
        </div>
        
        <div class="demo-accounts">
            <h4>演示账户</h4>
            <p><strong>管理员:</strong> admin / admin123</p>
            <p><strong>WAF管理员:</strong> waf / waf2024</p>
            <p><strong>天罡管理员:</strong> tiangang / tiangang2024</p>
        </div>
        
        <div class="login-footer">
            <p>© 2024 天罡 WAF. 专业Web应用防火墙</p>
        </div>
    </div>

    <script src="//unpkg.com/layui@2.12.1/dist/layui.js"></script>
    <script>
        layui.use([\'layer\', \'form\'], function(){
            var layer = layui.layer;
            var form = layui.form;
            
            const loginForm = document.getElementById(\'loginForm\');
            const loginButton = document.getElementById(\'loginButton\');
            const loading = document.getElementById(\'loading\');
            const errorMessage = document.getElementById(\'errorMessage\');
            const successMessage = document.getElementById(\'successMessage\');
            
            loginForm.addEventListener(\'submit\', async function(e) {
                e.preventDefault();
                
                const formData = new FormData(loginForm);
                const username = formData.get(\'username\');
                const password = formData.get(\'password\');
                const remember = formData.get(\'remember\') === \'on\';
                
                // 显示加载状态
                loginButton.disabled = true;
                loading.style.display = \'block\';
                errorMessage.style.display = \'none\';
                successMessage.style.display = \'none\';
                
                try {
                    const response = await fetch(\'/admin/auth/login\', {
                        method: \'POST\',
                        headers: {
                            \'Content-Type\': \'application/x-www-form-urlencoded\',
                        },
                        body: `username=${encodeURIComponent(username)}&password=${encodeURIComponent(password)}&remember=${remember}`
                    });
                    
                    const result = await response.json();
                    
                    if (result.code === 0) {
                        successMessage.textContent = result.msg;
                        successMessage.style.display = \'block\';
                        
                        // 延迟跳转
                        setTimeout(() => {
                            window.location.href = result.data.redirect;
                        }, 1000);
                    } else {
                        errorMessage.textContent = result.msg;
                        errorMessage.style.display = \'block\';
                    }
                } catch (error) {
                    console.error(\'登录错误:\', error);
                    errorMessage.textContent = \'网络错误，请稍后重试\';
                    errorMessage.style.display = \'block\';
                } finally {
                    loginButton.disabled = false;
                    loading.style.display = \'none\';
                }
            });
            
            // 回车键登录
            document.addEventListener(\'keypress\', function(e) {
                if (e.key === \'Enter\') {
                    loginForm.dispatchEvent(new Event(\'submit\'));
                }
            });
        });
    </script>
</body>
</html>';
    }
}
