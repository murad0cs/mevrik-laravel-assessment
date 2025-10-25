#!/bin/bash

# Mevrik Laravel Assessment - Deployment Script

echo "Starting deployment process..."

# Load environment variables
if [ -f .env ]; then
    export $(cat .env | grep -v '#' | awk '/=/ {print $1}')
fi

# Stop queue workers gracefully
echo "Stopping queue workers..."
php artisan queue:restart

# Pull latest changes (if using git)
# git pull origin main

# Install/Update dependencies
echo "Installing composer dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction

# Clear and cache configurations
echo "Optimizing application..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run migrations
echo "Running database migrations..."
php artisan migrate --force

# Set proper permissions
echo "Setting permissions..."
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# Restart queue workers
echo "Starting queue workers..."
php artisan queue:work --daemon &

# Restart PHP-FPM (adjust service name as needed)
# sudo systemctl restart php8.2-fpm

# Restart web server (adjust as needed)
# sudo systemctl reload nginx

echo "Deployment completed successfully!"
