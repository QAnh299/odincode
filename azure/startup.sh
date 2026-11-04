#!/bin/bash
# Startup Command trên Azure: bash /home/site/wwwroot/azure/startup.sh
cd /home/site/wwwroot

# 1. Nginx trỏ vào public/
cp /home/site/wwwroot/azure/nginx.conf /etc/nginx/sites-available/default
service nginx reload

# 2. Thư mục ghi được cho Laravel (nằm trong /home nên không mất khi khởi động lại)
mkdir -p storage/framework/{cache/data,sessions,views} storage/logs storage/app/public bootstrap/cache
chmod -R 775 storage bootstrap/cache

# 3. Tối ưu cho production
php artisan storage:link --force || true
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 4. Scheduler (opportunities:expire-undecided 00:10, invoices:mark-overdue 00:15) chạy nền
pkill -f "artisan schedule:work" || true
nohup php artisan schedule:work > storage/logs/scheduler.log 2>&1 &
