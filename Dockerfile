# 階段 J：Render 部署用 Dockerfile（v2 - Apache）
#
# 改用 php:apache 而不是 php:cli，因為：
#   1. Apache 處理靜態檔案 + URL rewrite 比 php -S 穩定太多
#   2. 直接服務 Vue build 的 JS / CSS / 圖片
#   3. mod_rewrite 自動處理 Laravel 路由
#   4. 不需要自寫 router.php，Apache 直接處理

FROM php:8.3-apache

# 安裝系統依賴
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        curl \
        ca-certificates \
        libpq-dev \
        libonig-dev \
        libzip-dev \
        libpng-dev \
        libxml2-dev \
        zip \
        unzip \
    && curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && docker-php-ext-install pdo_pgsql pgsql mbstring zip gd \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# 安裝 Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Apache 設定：把 DocumentRoot 從 /var/www/html 改成 /var/www/html/public
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# 啟用 mod_rewrite 讓 Laravel 的 .htaccess 生效
RUN a2enmod rewrite headers

WORKDIR /var/www/html

# 先複製 composer 依賴（可被 cache）
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# 再複製 npm 依賴
COPY package.json package-lock.json ./
RUN npm ci

# 複製剩下的程式碼
COPY . .

# 完成 build：composer dump-autoload + 前端 production build
RUN composer dump-autoload --optimize \
    && npm run build \
    && chmod -R 775 storage bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

# Render 透過 PORT 環境變數決定要監聽哪個 port
# Apache 預設聽 80，要改成 $PORT
ENV PORT=80
EXPOSE 80

# 啟動 script：替換 Apache 設定的 port、跑 migration、啟動 Apache
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

CMD ["/usr/local/bin/docker-entrypoint.sh"]
