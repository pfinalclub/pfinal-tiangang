<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登录 - 天罡 WAF 管理控制台</title>
    
    <!-- Layui CSS -->
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
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        
        .login-container {
            background: #FFFFFF;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 400px;
            overflow: hidden;
        }
        
        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 40px 30px;
            text-align: center;
            color: #FFFFFF;
        }
        
        .login-header .logo {
            font-size: 48px;
            margin-bottom: 10px;
        }
        
        .login-header h1 {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .login-header p {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .login-body {
            padding: 40px 30px;
        }
        
        .login-form .layui-form-item {
            margin-bottom: 20px;
        }
        
        .login-form .layui-input {
            height: 45px;
            line-height: 45px;
            border-radius: 6px;
            border: 1px solid #d9d9d9;
            padding: 0 15px;
            font-size: 14px;
        }
        
        .login-form .layui-input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.2);
        }
        
        .login-form .layui-btn {
            width: 100%;
            height: 45px;
            line-height: 45px;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 500;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            margin-top: 10px;
        }
        
        .login-form .layui-btn:hover {
            opacity: 0.9;
        }
        
        .login-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
            font-size: 13px;
        }
        
        .login-options a {
            color: #667eea;
            text-decoration: none;
        }
        
        .login-options a:hover {
            text-decoration: underline;
        }
        
        .login-footer {
            text-align: center;
            padding: 20px;
            background: #f5f5f5;
            font-size: 12px;
            color: #8c8c8c;
        }
        
        .error-message {
            background: #fff2f0;
            border: 1px solid #ffccc7;
            color: #ff4d4f;
            padding: 10px 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 13px;
            display: none;
        }
        
        .error-message.show {
            display: block;
        }
        
        .input-icon {
            position: relative;
        }
        
        .input-icon i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #bfbfbf;
            font-size: 18px;
        }
        
        .input-icon .layui-input {
            padding-left: 45px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="logo">
                <i class="layui-icon layui-icon-shield"></i>
            </div>
            <h1>天罡 WAF</h1>
            <p>Web Application Firewall 管理控制台</p>
        </div>
        
        <div class="login-body">
            <div class="error-message" id="errorMessage"></div>
            
            <form class="layui-form login-form" id="loginForm">
                <div class="layui-form-item">
                    <div class="input-icon">
                        <i class="layui-icon layui-icon-username"></i>
                        <input type="text" name="username" lay-verify="required" placeholder="用户名" autocomplete="off" class="layui-input">
                    </div>
                </div>
                
                <div class="layui-form-item">
                    <div class="input-icon">
                        <i class="layui-icon layui-icon-password"></i>
                        <input type="password" name="password" lay-verify="required" placeholder="密码" autocomplete="off" class="layui-input">
                    </div>
                </div>
                
                <div class="layui-form-item">
                    <input type="checkbox" name="remember" title="记住我" lay-skin="primary" checked>
                </div>
                
                <div class="layui-form-item">
                    <button class="layui-btn" lay-submit lay-filter="login">
                        <i class="layui-icon layui-icon-login"></i> 登录
                    </button>
                </div>
                
                <div class="login-options">
                    <label class="layui-form-checkbox" lay-skin="primary">
                        <input type="checkbox" name="remember">
                        <i class="layui-icon layui-icon-ok"></i>
                        <span>记住密码</span>
                    </label>
                    <a href="#" onclick="forgotPassword(); return false;">忘记密码？</a>
                </div>
            </form>
        </div>
        
        <div class="login-footer">
            © 2025 天罡 WAF. All rights reserved.
        </div>
    </div>
    
    <!-- Layui JS -->
    <script src="//unpkg.com/layui@2.12.1/dist/layui.js"></script>
    
    <script>
        layui.use(['form', 'layer'], function(){
            var form = layui.form;
            var layer = layui.layer;
            
            // 监听提交
            form.on('submit(login)', function(data){
                var loadIndex = layer.load(2, {shade: 0.3});
                
                // 添加 CSRF Token 到请求数据
                var formData = data.field;
                formData._token = '<?= $csrfToken ?>';
                
                // 发送登录请求
                fetch('/admin/auth/login', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': '<?= $csrfToken ?>',  // 也在 Header 中发送
                    },
                    credentials: 'include',  // 重要：确保发送 Cookie
                    body: JSON.stringify(formData)
                })
                .then(response => response.json())
                .then(result => {
                    layer.close(loadIndex);
                    
                    if (result.code === 0) {
                        layer.msg('登录成功', {icon: 1, time: 1000}, function(){
                            window.location.href = result.data.redirect || '/admin/dashboard';
                        });
                    } else {
                        showError(result.msg || result.message || '登录失败，请重试');
                    }
                })
                .catch(error => {
                    layer.close(loadIndex);
                    console.error('Login error:', error);
                    showError('网络错误，请检查连接');
                });
                
                return false;
            });
        });
        
        // 显示错误消息
        function showError(message) {
            var errorDiv = document.getElementById('errorMessage');
            errorDiv.textContent = message;
            errorDiv.classList.add('show');
            
            setTimeout(function() {
                errorDiv.classList.remove('show');
            }, 3000);
        }
        
        // 忘记密码
        function forgotPassword() {
            layui.use('layer', function(){
                var layer = layui.layer;
                layer.alert('请联系系统管理员重置密码', {
                    icon: 0,
                    title: '忘记密码'
                });
            });
        }
    </script>
</body>
</html>

