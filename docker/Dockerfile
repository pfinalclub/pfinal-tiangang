# 天罡 WAF 生产环境 Dockerfile
FROM php:8.2-fpm-alpine

# 设置工作目录
WORKDIR /var/www/html

# 安装系统依赖
RUN apk add --no-cache \
    nginx \
    supervisor \
    redis \
    mysql-client \
    curl \
    git \
    unzip \
    && rm -rf /var/cache/apk/*

# 安装 PHP 扩展
RUN docker-php-ext-install \
    pdo \
    pdo_mysql \
    opcache \
    && docker-php-ext-enable opcache

# 安装 Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 复制应用代码
COPY . /var/www/html/

# 安装 PHP 依赖
RUN composer install --no-dev --optimize-autoloader

# 设置权限
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# 复制配置文件
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/php.ini /usr/local/etc/php/php.ini
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# 创建日志目录
RUN mkdir -p /var/log/tiangang \
    && chown -R www-data:www-data /var/log/tiangang

# 创建缓存目录
RUN mkdir -p /var/cache/tiangang \
    && chown -R www-data:www-data /var/cache/tiangang

# 暴露端口
EXPOSE 80 443

# 健康检查
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/health || exit 1

# 启动命令
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
