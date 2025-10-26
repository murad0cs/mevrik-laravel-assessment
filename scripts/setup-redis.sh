#!/bin/bash

echo "Setting up Redis for Laravel Queue System..."

# Check if running as root
if [[ $EUID -eq 0 ]]; then
   echo "This script should not be run as root. Please run without sudo."
   exit 1
fi

# Update package list
echo "Updating package list..."
sudo apt-get update

# Install Redis server
echo "Installing Redis server..."
sudo apt-get install -y redis-server

# Install PHP Redis extension
echo "Installing PHP Redis extension..."
sudo apt-get install -y php-redis

# Configure Redis for better performance
echo "Configuring Redis for optimal performance..."
sudo bash -c 'cat > /etc/redis/redis.conf.d/queue.conf << EOF
# Laravel Queue Optimizations
maxmemory 256mb
maxmemory-policy allkeys-lru
save ""
appendonly no
tcp-keepalive 60
tcp-backlog 511
timeout 0
EOF'

# Start and enable Redis service
echo "Starting Redis service..."
sudo systemctl start redis-server
sudo systemctl enable redis-server

# Test Redis connection
echo "Testing Redis connection..."
redis-cli ping

if [ $? -eq 0 ]; then
    echo "✓ Redis is running successfully!"
else
    echo "✗ Redis connection failed. Please check the installation."
    exit 1
fi

# Update .env file if it exists
if [ -f ".env" ]; then
    echo "Updating .env file for Redis..."

    # Backup current .env
    cp .env .env.backup.$(date +%Y%m%d_%H%M%S)

    # Update queue connection
    sed -i 's/QUEUE_CONNECTION=.*/QUEUE_CONNECTION=redis/' .env

    # Add Redis configuration if not exists
    grep -q "REDIS_CLIENT" .env || echo "REDIS_CLIENT=phpredis" >> .env
    grep -q "REDIS_QUEUE_DB" .env || echo "REDIS_QUEUE_DB=2" >> .env
    grep -q "REDIS_CACHE_DB" .env || echo "REDIS_CACHE_DB=1" >> .env

    echo "✓ .env file updated for Redis"
fi

# Clear Laravel cache
echo "Clearing Laravel cache..."
php artisan config:clear
php artisan cache:clear

echo ""
echo "========================================="
echo "Redis Setup Complete!"
echo "========================================="
echo ""
echo "Redis is now configured for Laravel queues with:"
echo "- Performance optimizations enabled"
echo "- Dedicated database for queues (DB 2)"
echo "- Dedicated database for cache (DB 1)"
echo ""
echo "To use Redis queues, make sure your .env has:"
echo "  QUEUE_CONNECTION=redis"
echo ""
echo "To monitor Redis:"
echo "  redis-cli monitor"
echo ""
echo "To check Redis info:"
echo "  redis-cli info"
echo ""