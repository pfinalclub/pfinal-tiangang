<?php
/**
 * 域名映射表单视图
 * 
 * @var bool $isEdit 是否为编辑模式
 * @var string|null $domain 域名（编辑时）
 * @var array|null $mapping 映射数据（编辑时）
 * @var array $backends 后端服务列表
 */
$domainValue = $isEdit ? htmlspecialchars($domain) : '';
$enabledChecked = ($isEdit && $mapping && ($mapping['enabled'] ?? true)) ? 'checked' : '';

$wafRules = $isEdit && $mapping ? ($mapping['waf_rules'] ?? []) : [];
$sqlChecked = in_array('sql_injection', $wafRules) ? 'checked' : '';
$xssChecked = in_array('xss', $wafRules) ? 'checked' : '';
$rateLimitChecked = in_array('rate_limit', $wafRules) ? 'checked' : '';
$ipBlacklistChecked = in_array('ip_blacklist', $wafRules) ? 'checked' : '';

// 判断当前 backend 是 URL 还是 name
$currentBackend = $isEdit && $mapping ? ($mapping['backend'] ?? '') : '';
$isBackendUrl = !empty($currentBackend) && filter_var($currentBackend, FILTER_VALIDATE_URL);
$backendType = $isBackendUrl ? 'direct' : 'select';

// preserve_host 选项
$preserveHostChecked = ($isEdit && $mapping && ($mapping['preserve_host'] ?? false)) ? 'checked' : '';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isEdit ? '编辑' : '添加' ?>域名映射</title>
    <link href="//unpkg.com/layui@2.12.1/dist/css/layui.css" rel="stylesheet">
    <style>
        body {
            padding: 20px;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        .form-item {
            margin-bottom: 20px;
        }
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }
        .form-input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        .form-checkbox-group {
            margin-top: 10px;
        }
        .form-checkbox-item {
            margin-bottom: 10px;
        }
        .form-tip {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }
        .form-actions {
            margin-top: 30px;
            text-align: right;
        }
        .form-actions button {
            margin-left: 10px;
        }
    </style>
</head>
<body>
    <form id="domain-form">
        <div class="form-item">
            <label class="form-label">域名 <span style="color: red;">*</span></label>
            <input type="text" name="domain" id="domain_input" class="form-input" value="<?= $domainValue ?>" placeholder="例如: crm.smm.cn 或 *.api.smm.cn" <?= $isEdit ? 'readonly' : '' ?> required>
            <?php if ($isEdit): ?>
            <!-- 编辑模式下，使用隐藏字段确保域名字段被提交 -->
            <input type="hidden" name="domain" value="<?= htmlspecialchars($domainValue) ?>">
            <?php endif; ?>
            <div class="form-tip">支持精确域名（如 crm.smm.cn）或通配符域名（如 *.api.smm.cn，通配符必须在开头）<?= $isEdit ? '（编辑模式下域名不可修改）' : '' ?></div>
        </div>
        
        <div class="form-item">
            <label class="form-label">后端服务 <span style="color: red;">*</span></label>
            <div style="margin-bottom: 10px;">
                <input type="radio" name="backend_type" value="select" id="backend_type_select" <?= $backendType === 'select' ? 'checked' : '' ?> onchange="toggleBackendInput()">
                <label for="backend_type_select" style="margin-right: 20px; margin-left: 5px;">选择已有后端服务</label>
                <input type="radio" name="backend_type" value="direct" id="backend_type_direct" <?= $backendType === 'direct' ? 'checked' : '' ?> onchange="toggleBackendInput()">
                <label for="backend_type_direct" style="margin-left: 5px;">直接输入 URL</label>
            </div>
            
            <!-- 选择已有后端服务 -->
            <select name="backend" id="backend_select" class="form-input" <?= $backendType === 'select' ? 'required' : '' ?> style="<?= $backendType === 'direct' ? 'display: none;' : '' ?>">
                <option value="">请选择后端服务</option>
                <?php foreach ($backends as $backend): ?>
                    <?php
                    $name = $backend['name'] ?? '';
                    if (empty($name)) continue;
                    $selected = ($isEdit && $mapping && !$isBackendUrl && ($mapping['backend'] ?? '') === $name) ? 'selected' : '';
                    $url = $backend['url'] ?? '';
                    $displayName = $name . ($url ? ' (' . htmlspecialchars($url) . ')' : '');
                    ?>
                    <option value="<?= htmlspecialchars($name) ?>" <?= $selected ?>><?= htmlspecialchars($displayName) ?></option>
                <?php endforeach; ?>
            </select>
            
            <!-- 直接输入 URL -->
            <input type="text" name="backend_url" id="backend_url" class="form-input" 
                   placeholder="例如: http://192.168.1.100:8080 或 https://api.example.com" 
                   style="<?= $backendType === 'select' ? 'display: none;' : '' ?>"
                   value="<?= $isBackendUrl ? htmlspecialchars($currentBackend) : '' ?>"
                   <?= $backendType === 'direct' ? 'required' : '' ?>>
            
            <div class="form-tip">选择该域名要路由到的后端服务，或直接输入后端服务的 URL（IP:端口）</div>
        </div>
        
        <div class="form-item">
            <label class="form-label">WAF 保护规则</label>
            <div class="form-checkbox-group">
                <div class="form-checkbox-item">
                    <input type="checkbox" name="waf_rules[]" value="sql_injection" id="waf_sql" <?= $sqlChecked ?>>
                    <label for="waf_sql">SQL 注入检测</label>
                </div>
                <div class="form-checkbox-item">
                    <input type="checkbox" name="waf_rules[]" value="xss" id="waf_xss" <?= $xssChecked ?>>
                    <label for="waf_xss">XSS 攻击检测</label>
                </div>
                <div class="form-checkbox-item">
                    <input type="checkbox" name="waf_rules[]" value="rate_limit" id="waf_rate" <?= $rateLimitChecked ?>>
                    <label for="waf_rate">频率限制</label>
                </div>
                <div class="form-checkbox-item">
                    <input type="checkbox" name="waf_rules[]" value="ip_blacklist" id="waf_ip" <?= $ipBlacklistChecked ?>>
                    <label for="waf_ip">IP 黑名单</label>
                </div>
            </div>
            <div class="form-tip">选择要启用的 WAF 保护规则，不选择则使用默认规则</div>
        </div>
        
        <div class="form-item">
            <label class="form-label">透明代理模式</label>
            <div class="form-checkbox-item">
                <input type="checkbox" name="preserve_host" value="1" id="preserve_host" <?= $preserveHostChecked ?>>
                <label for="preserve_host">保持原始 Host 头（透明代理）</label>
            </div>
            <div class="form-tip">启用后，转发请求时会保持原始域名作为 Host 头，而不是使用后端服务的域名</div>
        </div>
        
        <div class="form-item">
            <label class="form-label">状态</label>
            <div class="form-checkbox-item">
                <input type="checkbox" name="enabled" value="1" id="enabled" <?= $enabledChecked ?>>
                <label for="enabled">启用此映射</label>
            </div>
        </div>
        
        <div class="form-actions">
            <button type="button" class="layui-btn" onclick="submitForm()">保存</button>
            <button type="button" class="layui-btn layui-btn-primary" onclick="cancel()">取消</button>
        </div>
    </form>
    
    <script src="//unpkg.com/layui@2.12.1/dist/layui.js"></script>
    <script>
        var layer = layui.layer;
        
        function toggleBackendInput() {
            var backendType = document.querySelector('input[name="backend_type"]:checked').value;
            var selectInput = document.getElementById('backend_select');
            var urlInput = document.getElementById('backend_url');
            
            if (backendType === 'select') {
                selectInput.style.display = 'block';
                selectInput.required = true;
                urlInput.style.display = 'none';
                urlInput.required = false;
                urlInput.value = ''; // 清空 URL 输入
            } else {
                selectInput.style.display = 'none';
                selectInput.required = false;
                selectInput.value = ''; // 清空选择
                urlInput.style.display = 'block';
                urlInput.required = true;
            }
        }
        
        function submitForm() {
            var form = document.getElementById("domain-form");
            var formData = new FormData(form);
            
            // 处理后端服务选择
            var backendType = document.querySelector('input[name="backend_type"]:checked').value;
            var backendValue = '';
            
            if (backendType === 'direct') {
                var backendUrl = document.getElementById('backend_url').value.trim();
                if (!backendUrl) {
                    layer.msg('请输入后端服务 URL', {icon: 2});
                    return;
                }
                // 验证 URL 格式
                if (!/^https?:\/\/.+/.test(backendUrl)) {
                    layer.msg('URL 格式不正确，必须以 http:// 或 https:// 开头', {icon: 2});
                    return;
                }
                backendValue = backendUrl;
            } else {
                var backendSelect = document.getElementById('backend_select').value;
                if (!backendSelect) {
                    layer.msg('请选择后端服务', {icon: 2});
                    return;
                }
                backendValue = backendSelect;
            }
            
            // 处理复选框数组
            var wafRules = [];
            form.querySelectorAll('input[name="waf_rules[]"]:checked').forEach(function(checkbox) {
                wafRules.push(checkbox.value);
            });
            formData.delete('waf_rules[]');
            wafRules.forEach(function(rule) {
                formData.append('waf_rules[]', rule);
            });
            
            // 转换为 JSON
            var data = {};
            for (var pair of formData.entries()) {
                var key = pair[0];
                var value = pair[1];
                
                // 跳过 backend_type 和 backend_url，使用我们处理后的 backendValue
                if (key === 'backend_type' || key === 'backend_url') {
                    continue;
                }
                
                if (key.endsWith('[]')) {
                    key = key.slice(0, -2);
                    if (!data[key]) {
                        data[key] = [];
                    }
                    data[key].push(value);
                } else {
                    data[key] = value;
                }
            }
            
            // 设置后端服务值
            data.backend = backendValue;
            
            // enabled 复选框处理
            data.enabled = document.getElementById("enabled").checked;
            
            // preserve_host 复选框处理
            data.preserve_host = document.getElementById("preserve_host").checked;
            
            // 添加 CSRF Token
            data._token = '<?= htmlspecialchars($csrfToken ?? '') ?>';
            
            fetch("/admin/api/config/domain-mapping/save", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(result => {
                if (result.code === 0) {
                    layer.msg("保存成功", {icon: 1}, function() {
                        var index = parent.layer.getFrameIndex(window.name);
                        parent.layer.close(index);
                    });
                } else {
                    layer.msg(result.msg || "保存失败", {icon: 2});
                }
            })
            .catch(error => {
                layer.msg("保存失败: " + error.message, {icon: 2});
            });
        }
        
        function cancel() {
            var index = parent.layer.getFrameIndex(window.name);
            parent.layer.close(index);
        }
        
        // 页面加载时初始化
        window.onload = function() {
            toggleBackendInput();
        };
    </script>
</body>
</html>

