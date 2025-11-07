<?php
/**
 * 后端服务表单视图
 * 
 * @var bool $isEdit 是否为编辑模式
 * @var string|null $name 后端名称（编辑时）
 * @var array|null $backend 后端数据（编辑时）
 */
$nameValue = $isEdit ? htmlspecialchars($name) : '';
$urlValue = $isEdit && $backend ? htmlspecialchars($backend['url'] ?? '') : '';
$weightValue = $isEdit && $backend ? ($backend['weight'] ?? 1) : 1;
$healthUrlValue = $isEdit && $backend ? htmlspecialchars($backend['health_url'] ?? '') : '';
$healthTimeoutValue = $isEdit && $backend ? ($backend['health_timeout'] ?? 5) : 5;
$recoveryTimeValue = $isEdit && $backend ? ($backend['recovery_time'] ?? 60) : 60;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isEdit ? '编辑' : '添加' ?>后端服务</title>
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
    <form id="backend-form">
        <div class="form-item">
            <label class="form-label">服务名称 <span style="color: red;">*</span></label>
            <input type="text" name="name" class="form-input" value="<?= $nameValue ?>" placeholder="例如: primary" <?= $isEdit ? 'readonly' : '' ?> required>
            <div class="form-tip">后端服务的唯一标识符</div>
        </div>
        
        <div class="form-item">
            <label class="form-label">服务 URL <span style="color: red;">*</span></label>
            <input type="url" name="url" class="form-input" value="<?= $urlValue ?>" placeholder="http://backend.example.com" required>
            <div class="form-tip">后端服务的完整 URL 地址</div>
        </div>
        
        <div class="form-item">
            <label class="form-label">权重</label>
            <input type="number" name="weight" class="form-input" value="<?= $weightValue ?>" min="1" max="100">
            <div class="form-tip">负载均衡权重，数值越大分配流量越多（1-100）</div>
        </div>
        
        <div class="form-item">
            <label class="form-label">健康检查 URL</label>
            <input type="url" name="health_url" class="form-input" value="<?= $healthUrlValue ?>" placeholder="http://backend.example.com/health">
            <div class="form-tip">健康检查接口地址，留空则使用服务 URL + /health</div>
        </div>
        
        <div class="form-item">
            <label class="form-label">健康检查超时（秒）</label>
            <input type="number" name="health_timeout" class="form-input" value="<?= $healthTimeoutValue ?>" min="1" max="60">
            <div class="form-tip">健康检查请求的超时时间（1-60秒）</div>
        </div>
        
        <div class="form-item">
            <label class="form-label">恢复时间（秒）</label>
            <input type="number" name="recovery_time" class="form-input" value="<?= $recoveryTimeValue ?>" min="10" max="3600">
            <div class="form-tip">服务恢复后等待多长时间再重新加入负载均衡（10-3600秒）</div>
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
            var form = document.getElementById("backend-form");
            var formData = new FormData(form);
            
            // 转换为 JSON
            var data = {};
            for (var pair of formData.entries()) {
                data[pair[0]] = pair[1];
            }
            
            // 转换数字类型
            if (data.weight) data.weight = parseInt(data.weight);
            if (data.health_timeout) data.health_timeout = parseInt(data.health_timeout);
            if (data.recovery_time) data.recovery_time = parseInt(data.recovery_time);
            
            fetch("/admin/api/config/backend/save", {
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

