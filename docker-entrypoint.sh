#!/bin/bash
set -e

# Apache 啟動腳本（給 Render Docker 部署用）
#
# 流程：
#   1. 把 Apache 設定裡的 port 80 換成 Render 給的 $PORT
#   2. 跑 Laravel cache + migration + seeder
#   3. 啟動 Apache（foreground）

# === 1. 把 Apache 改成監聽 $PORT ===
PORT=${PORT:-80}
echo "Configuring Apache to listen on port ${PORT}"
sed -i "s/Listen 80/Listen ${PORT}/g" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/g" /etc/apache2/sites-available/000-default.conf

# === 2. Laravel 初始化 ===
cd /var/www/html

echo "Caching Laravel config / routes / views..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "Running migrations..."
php artisan migrate --force

echo "Running seeders (idempotent, safe to re-run)..."
php artisan db:seed --force --class=FoodSeeder || true
php artisan db:seed --force --class=ChainStoreSeeder || true

# === 3. 啟動 Apache（foreground 模式讓 container 不退出） ===
echo "Starting Apache..."
exec apache2-foreground
