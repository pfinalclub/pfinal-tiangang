<?php
/**
 * 路径映射表单视图
 * 
 * @var bool $isEdit 是否为编辑模式
 * @var string|null $path 路径（编辑时）
 * @var array|null $mapping 映射数据（编辑时）
 * @var array $backends 后端服务列表
 */
$pathValue = $isEdit ? htmlspecialchars($path) : '';
$stripPrefixChecked = ($isEdit && $mapping && ($mapping['strip_prefix'] ?? false)) ? 'checked' : '';
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
    <title><?= $isEdit ? '编辑' : '添加' ?>路径映射</title>
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
        .form-checkbox {
            margin-right: 15px;
        }
    </style>
</head>
<body>
    <form id="mapping-form" class="layui-form">
        <div class="form-item">
            <label class="form-label">路径前缀 <span style="color: red;">*</span></label>
            <input type="text" name="path" class="form-input layui-input" value="<?= $pathValue ?>" placeholder="/app1" required>
            <div style="font-size: 12px; color: #999; margin-top: 5px;">必须以 / 开头，例如：/app1</div>
        </div>
        
        <div class="form-item">
            <label class="form-label">后端服务 <span style="color: red;">*</span></label>
            <select name="backend" class="form-input layui-select" required <?= empty($backends) ? 'disabled' : '' ?>>
                <?php if (empty($backends)): ?>
                    <option value="">暂无后端服务，请先在配置文件中添加</option>
                <?php else: ?>
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
                <?php endif; ?>
            </select>
            <?php if (empty($backends)): ?>
                <div style="font-size: 12px; color: #ff5722; margin-top: 5px;">请在 config/proxy.php 中配置后端服务</div>
            <?php endif; ?>
        </div>
        
        <div class="form-item">
            <label class="form-label">移除路径前缀</label>
            <input type="checkbox" name="strip_prefix" class="form-checkbox" <?= $stripPrefixChecked ?>>
            <span style="font-size: 12px; color: #999; margin-left: 5px;">启用后，/app1/api 会转发为 http://backend/api（移除 /app1 前缀）</span>
        </div>
        
        <div class="form-item">
            <label class="form-label">WAF 规则</label>
            <div class="form-checkbox-group">
                <input type="checkbox" name="waf_rules[]" value="sql_injection" class="form-checkbox" <?= $sqlChecked ?>> SQL 注入检测
                <input type="checkbox" name="waf_rules[]" value="xss" class="form-checkbox" <?= $xssChecked ?>> XSS 检测
                <input type="checkbox" name="waf_rules[]" value="rate_limit" class="form-checkbox" <?= $rateLimitChecked ?>> 频率限制
                <input type="checkbox" name="waf_rules[]" value="ip_blacklist" class="form-checkbox" <?= $ipBlacklistChecked ?>> IP 黑名单
            </div>
            <div style="font-size: 12px; color: #999; margin-top: 5px;">不选择则使用默认规则</div>
        </div>
        
        <div class="form-item">
            <label class="form-label">启用状态</label>
            <input type="checkbox" name="enabled" class="form-checkbox" <?= $enabledChecked ?> checked>
            <span style="font-size: 12px; color: #999; margin-left: 5px;">禁用后此映射将不生效</span>
        </div>
        
        <div class="form-item" style="text-align: right; margin-top: 30px;">
            <button type="button" class="layui-btn layui-btn-primary" onclick="cancel()">取消</button>
            <button type="submit" class="layui-btn">保存</button>
        </div>
    </form>
    
    <script src="//unpkg.com/layui@2.12.1/dist/layui.js"></script>
    <script>
        layui.use(['form', 'layer'], function(){
            var form = layui.form;
            var layer = layui.layer;
            
            form.on('submit(mapping-form)', function(data){
                submitForm(data.field);
                return false;
            });
        });
        
        function submitForm(formData) {
            // 处理 waf_rules 数组
            let wafRules = [];
            if (formData['waf_rules[]']) {
                wafRules = Array.isArray(formData['waf_rules[]']) 
                    ? formData['waf_rules[]'] 
                    : [formData['waf_rules[]']];
            }
            
            const data = {
                path: formData.path,
                backend: formData.backend,
                strip_prefix: formData.strip_prefix === 'on',
                enabled: formData.enabled === 'on',
                waf_rules: wafRules,
                _token: '<?= htmlspecialchars($csrfToken ?? '') ?>'
            };
            
            fetch("/admin/api/config/path-mapping/save", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(result => {
                if (result.code === 0) {
                    layer.msg("保存成功", {icon: 1}, function(){
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

