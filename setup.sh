#!/bin/bash

# Mevrik Laravel Assessment - Initial Setup Script

echo "========================================"
echo "Mevrik Laravel Assessment Setup"
echo "========================================"

# Check if .env exists
if [ ! -f .env ]; then
    echo "Creating .env file from .env.example..."
    cp .env.example .env
    echo ".env file created. Please update it with your configuration."
else
    echo ".env file already exists."
fi

# Install composer dependencies
echo ""
echo "Installing Composer dependencies..."
if command -v composer &> /dev/null
then
    composer install
else
    echo "Error: Composer is not installed. Please install Composer first."
    exit 1
fi

# Install npm dependencies
echo ""
echo "Installing NPM dependencies..."
if command -v npm &> /dev/null
then
    npm install
else
    echo "Warning: NPM is not installed. Skipping npm install."
fi

# Generate application key
echo ""
echo "Generating application key..."
php artisan key:generate

# Set permissions
echo ""
echo "Setting directory permissions..."
chmod -R 775 storage bootstrap/cache
if [ -d "storage" ]; then
    chmod -R 775 storage
fi
if [ -d "bootstrap/cache" ]; then
    chmod -R 775 bootstrap/cache
fi

# Create database
echo ""
echo "========================================"
echo "Database Setup"
echo "========================================"
read -p "Do you want to run migrations? (y/n) " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]
then
    echo "Running migrations..."
    php artisan migrate
    echo "Migrations completed!"
fi

# Build assets
echo ""
read -p "Do you want to build frontend assets? (y/n) " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]
then
    echo "Building assets..."
    npm run build
    echo "Assets built successfully!"
fi

echo ""
echo "========================================"
echo "Setup completed successfully!"
echo "========================================"
echo ""
echo "Next steps:"
echo "1. Update .env file with your database credentials"
echo "2. Run 'php artisan migrate' if you haven't already"
echo "3. Start the development server: php artisan serve"
echo "4. Start the queue worker: php artisan queue:work"
echo ""
echo "API Endpoints:"
echo "- Queue Status: GET http://localhost:8000/api/queue"
echo "- Dispatch Notification: POST http://localhost:8000/api/queue/dispatch-notification"
echo "- Dispatch Log: POST http://localhost:8000/api/queue/dispatch-log"
echo "- Dispatch Bulk: POST http://localhost:8000/api/queue/dispatch-bulk"
echo "- Health Check: GET http://localhost:8000/api/health"
echo ""
echo "Run tests with: php artisan test"
echo ""
