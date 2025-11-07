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
            <input type="text" name="domain" class="form-input" value="<?= $domainValue ?>" placeholder="例如: crm.smm.cn 或 *.api.smm.cn" <?= $isEdit ? 'readonly' : '' ?> required>
            <div class="form-tip">支持精确域名（如 crm.smm.cn）或通配符域名（如 *.api.smm.cn，通配符必须在开头）</div>
        </div>
        
        <div class="form-item">
            <label class="form-label">后端服务 <span style="color: red;">*</span></label>
            <select name="backend" class="form-input" required>
                <option value="">请选择后端服务</option>
                <?php foreach ($backends as $backend): ?>
                    <?php
                    $name = $backend['name'] ?? '';
                    if (empty($name)) continue;
                    $selected = ($isEdit && $mapping && ($mapping['backend'] ?? '') === $name) ? 'selected' : '';
                    $url = $backend['url'] ?? '';
                    $displayName = $name . ($url ? ' (' . htmlspecialchars($url) . ')' : '');
                    ?>
                    <option value="<?= htmlspecialchars($name) ?>" <?= $selected ?>><?= htmlspecialchars($displayName) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="form-tip">选择该域名要路由到的后端服务</div>
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
        
        function submitForm() {
            var form = document.getElementById("domain-form");
            var formData = new FormData(form);
            
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
            
            // enabled 复选框处理
            data.enabled = document.getElementById("enabled").checked;
            
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
    </script>
</body>
</html>

