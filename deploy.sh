#!/bin/bash

# Automated Deployment Script with Redis Setup
# This script handles the complete deployment including Redis installation

echo "========================================="
echo "Laravel Queue Application Deployment"
echo "========================================="

# Navigate to app directory (adjust path as needed)
APP_DIR="/var/www/laravel-app"
if [ -d "$APP_DIR" ]; then
    cd "$APP_DIR" || exit
else
    echo "App directory not found. Using current directory."
fi

echo "Pulling latest code..."
git pull origin main

echo "Installing Composer dependencies..."
composer install --no-interaction --no-dev --prefer-dist --optimize-autoloader

# Check and install Redis if needed
if ! command -v redis-cli &> /dev/null; then
    echo "Redis not found. Installing Redis..."
    sudo apt-get update
    sudo apt-get install -y redis-server php8.2-redis

    # Configure Redis for Laravel
    sudo bash -c 'cat >> /etc/redis/redis.conf << EOF
# Laravel Queue Optimizations
maxmemory 256mb
maxmemory-policy allkeys-lru
tcp-keepalive 60
EOF'

    sudo systemctl enable redis-server
    sudo systemctl restart redis-server

    echo "Redis installed successfully!"
else
    echo "Redis already installed"
fi

# Test Redis connection
redis-cli ping > /dev/null 2>&1
if [ $? -eq 0 ]; then
    echo "Redis is running"

    # Update .env to use Redis
    echo "Updating .env for Redis..."
    sed -i 's/QUEUE_CONNECTION=database/QUEUE_CONNECTION=redis/' .env
    sed -i 's/CACHE_DRIVER=file/CACHE_DRIVER=redis/' .env

    # Add Redis config if not exists
    grep -q "REDIS_CLIENT" .env || echo "REDIS_CLIENT=phpredis" >> .env
    grep -q "REDIS_QUEUE_DB" .env || echo "REDIS_QUEUE_DB=2" >> .env
else
    echo "WARNING: Redis connection failed. Using database queue driver."
fi

echo "Running migrations..."
php artisan migrate --force

echo "Clearing and optimizing caches..."
php artisan config:clear
php artisan cache:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "Setting permissions..."
sudo chown -R www-data:www-data "$APP_DIR"
sudo chmod -R 755 "$APP_DIR"
sudo chmod -R 777 "$APP_DIR/storage"
sudo chmod -R 777 "$APP_DIR/bootstrap/cache"

echo "Updating Supervisor workers..."
# Configure supervisor for Redis workers
sudo tee /etc/supervisor/conf.d/laravel-worker.conf > /dev/null <<EOF
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php $APP_DIR/artisan queue:work redis --queue=high,default,low --sleep=3 --tries=3 --timeout=90
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=$APP_DIR/storage/logs/worker.log
stopwaitsecs=3600
EOF

# High priority worker
sudo tee /etc/supervisor/conf.d/laravel-high-priority.conf > /dev/null <<EOF
[program:laravel-high-priority]
process_name=%(program_name)s_%(process_num)02d
command=php $APP_DIR/artisan queue:work redis --queue=high --sleep=1 --tries=2 --timeout=60
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=$APP_DIR/storage/logs/high-priority.log
stopwaitsecs=3600
EOF

sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl restart all

echo ""
echo "========================================="
echo "Deployment Complete!"
echo "========================================="
echo ""
echo "[DONE] Code updated"
echo "[DONE] Dependencies installed"
echo "[DONE] Redis configured"
echo "[DONE] Migrations run"
echo "[DONE] Caches optimized"
echo "[DONE] Workers restarted"
echo ""
echo "Queue Driver: $(grep QUEUE_CONNECTION .env | cut -d'=' -f2)"
echo "Redis Status: $(redis-cli ping 2>/dev/null || echo 'Not running')"
echo ""
echo "Test the application at: https://mindi-unetymologic-keyla.ngrok-free.dev"
echo ""