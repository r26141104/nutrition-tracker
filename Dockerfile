# 階段 J：Render 部署用 Dockerfile
#
# 為什麼用 Dockerfile？
#   Render 原生 runtime 不支援 PHP（只支援 Node/Python/Ruby/Go），
#   所以 Laravel 專案要部署到 Render 必須用 Docker。
#
# 這個 Dockerfile 做什麼：
#   1. 用官方 PHP 8.3 CLI 映像當基底
#   2. 安裝 Laravel + PostgreSQL 需要的系統套件
#   3. 安裝 Node 20 來 build 前端
#   4. 跑 composer install + npm build + 各種 Laravel cache
#   5. 啟動時自動跑 migration 然後啟動 PHP 內建伺服器
#
# 注意：使用 PHP 內建 server（php -S）對 demo 夠用，
# 上線等級流量需要 nginx + php-fpm，但學期報告不需要。

FROM php:8.3-cli

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

WORKDIR /var/www/html

# 先複製依賴清單，分層 cache（之後改 code 不重抓套件）
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

COPY package.json package-lock.json ./
RUN npm ci

# 複製剩下的檔案
COPY . .

# 完成 composer + npm build
RUN composer dump-autoload --optimize \
    && npm run build \
    && chmod -R 775 storage bootstrap/cache

# Render 會把 PORT 環境變數塞進來（通常 10000）
ENV PORT=10000
EXPOSE 10000

# 啟動指令：cache config → 跑 migration → 啟動 PHP 內建 server
# migration 是 idempotent，重啟跑沒副作用
CMD php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache \
    && php artisan migrate --force \
    && php -S 0.0.0.0:${PORT} -t public public/index.php
