    </div>
    <!-- /主内容区 -->
    
    <!-- Layui JS -->
    <script src="//unpkg.com/layui@2.12.1/dist/layui.js"></script>
    
    <!-- jQuery -->
    <script src="//code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- 通用 JS -->
    <script>
        // 初始化 Layui 组件
        layui.use(['element', 'layer', 'form', 'table', 'laydate'], function(){
            var element = layui.element;
            var layer = layui.layer;
            var form = layui.form;
            var table = layui.table;
            var laydate = layui.laydate;
            
            // 全局变量
            window.layerInstance = layer;
            window.formInstance = form;
            window.tableInstance = table;
            window.laydateInstance = laydate;
        });
        
        // 退出登录
        function logout() {
            layui.use('layer', function(){
                var layer = layui.layer;
                layer.confirm('确定要退出登录吗？', {
                    icon: 3,
                    title: '提示'
                }, function(index){
                    window.location.href = '/admin/auth/logout';
                    layer.close(index);
                });
            });
        }
        
        // 刷新页面
        document.getElementById('refreshBtn')?.addEventListener('click', function() {
            location.reload();
        });
        
        // AJAX 辅助函数
        function ajaxRequest(url, data, success, error) {
            $.ajax({
                url: url,
                type: 'POST',
                data: JSON.stringify(data),
                contentType: 'application/json',
                dataType: 'json',
                success: function(response) {
                    if (response.code === 0) {
                        if (success) success(response.data);
                    } else {
                        layui.use('layer', function(){
                            var layer = layui.layer;
                            layer.msg(response.message || '操作失败', {icon: 2});
                        });
                        if (error) error(response);
                    }
                },
                error: function(xhr, status, err) {
                    layui.use('layer', function(){
                        var layer = layui.layer;
                        layer.msg('请求失败：' + (err || status), {icon: 2});
                    });
                    if (error) error({code: -1, message: err || status});
                }
            });
        }
        
        // GET 请求
        function ajaxGet(url, success, error) {
            $.ajax({
                url: url,
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.code === 0) {
                        if (success) success(response.data);
                    } else {
                        layui.use('layer', function(){
                            var layer = layui.layer;
                            layer.msg(response.message || '操作失败', {icon: 2});
                        });
                        if (error) error(response);
                    }
                },
                error: function(xhr, status, err) {
                    layui.use('layer', function(){
                        var layer = layui.layer;
                        layer.msg('请求失败：' + (err || status), {icon: 2});
                    });
                    if (error) error({code: -1, message: err || status});
                }
            });
        }
        
        // 格式化时间
        function formatTime(timestamp) {
            if (!timestamp) return '-';
            var date = new Date(timestamp * 1000);
            var year = date.getFullYear();
            var month = String(date.getMonth() + 1).padStart(2, '0');
            var day = String(date.getDate()).padStart(2, '0');
            var hours = String(date.getHours()).padStart(2, '0');
            var minutes = String(date.getMinutes()).padStart(2, '0');
            var seconds = String(date.getSeconds()).padStart(2, '0');
            return year + '-' + month + '-' + day + ' ' + hours + ':' + minutes + ':' + seconds;
        }
        
        // 格式化文件大小
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 B';
            var k = 1024;
            var sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
            var i = Math.floor(Math.log(bytes) / Math.log(k));
            return (bytes / Math.pow(k, i)).toFixed(2) + ' ' + sizes[i];
        }
        
        // 格式化数字
        function formatNumber(num) {
            if (num >= 1000000) {
                return (num / 1000000).toFixed(1) + 'M';
            } else if (num >= 1000) {
                return (num / 1000).toFixed(1) + 'K';
            }
            return num;
        }
        
        // 复制到剪贴板
        function copyToClipboard(text) {
            var textarea = document.createElement('textarea');
            textarea.value = text;
            document.body.appendChild(textarea);
            textarea.select();
            try {
                document.execCommand('copy');
                layui.use('layer', function(){
                    var layer = layui.layer;
                    layer.msg('已复制到剪贴板', {icon: 1});
                });
            } catch (err) {
                layui.use('layer', function(){
                    var layer = layui.layer;
                    layer.msg('复制失败', {icon: 2});
                });
            }
            document.body.removeChild(textarea);
        }
    </script>
    
    <?php if (isset($customJs)): ?>
        <script><?= $customJs ?></script>
    <?php endif; ?>
</body>
</html>

