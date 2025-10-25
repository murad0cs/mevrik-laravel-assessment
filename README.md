# Laravel Queue Application

This Laravel application demonstrates a queue system implementation with automated deployment via GitHub Actions for the Mevrik DevOps assessment.

## Requirements

- PHP 8.1 or higher
- MySQL 5.7 or higher
- Composer

## Setup Steps

Clone the repository and install dependencies:
```bash
git clone https://github.com/YOUR_USERNAME/mevrik-laravel-assessment.git
cd mevrik-laravel-assessment
composer install
```

Configure the environment:
```bash
cp .env.example .env
php artisan key:generate
```

Update `.env` with database credentials:
```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=laravel_db
DB_USERNAME=root
DB_PASSWORD=your_password
QUEUE_CONNECTION=database
```

Run migrations and start services:
```bash
php artisan migrate
php artisan serve
php artisan queue:work
```

## Queue Configuration

The application uses Laravel's database queue driver. Jobs are stored in the `jobs` table and failed jobs in `failed_jobs`.

### Sample Jobs

**ProcessNotification** (`app/Jobs/ProcessNotification.php`)
Handles notification processing for email, SMS, and push notifications.

**WriteLogJob** (`app/Jobs/WriteLogJob.php`)
Writes structured log entries to both Laravel logs and custom JSON files.

### API Endpoints

- `GET /api/health` - Health check
- `GET /api/queue` - Queue status
- `POST /api/queue/dispatch-notification` - Dispatch notification job
- `POST /api/queue/dispatch-log` - Dispatch log job
- `POST /api/queue/dispatch-bulk` - Dispatch multiple jobs

Example usage:
```bash
curl http://localhost:8000/api/queue

curl -X POST http://localhost:8000/api/queue/dispatch-notification \
  -H "Content-Type: application/json" \
  -d '{"type":"email","recipient":"test@example.com","message":"Test message"}'
```

## Testing

```bash
php artisan test
```

Tests cover queue job dispatching, API endpoints, and job processing logic.

## Deployment Process

### Server Setup

Connect to server:
```bash
ssh -i ssh_key.pem constantk@4.145.113.8
```

Install dependencies:
```bash
sudo apt update
sudo apt install php8.2-fpm php8.2-mysql php8.2-mbstring php8.2-xml php8.2-curl nginx mysql-server supervisor
```

### Application Deployment

Clone repository on server:
```bash
cd /var/www/
sudo git clone https://github.com/YOUR_USERNAME/mevrik-laravel-assessment.git laravel-app
sudo chown -R constantk:www-data laravel-app
cd laravel-app
```

Install and configure:
```bash
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
php artisan migrate --force
```

Set permissions:
```bash
sudo chmod -R 755 /var/www/laravel-app
sudo chmod -R 777 storage bootstrap/cache
```

### Queue Worker Setup

Configure Supervisor to manage queue workers. Create `/etc/supervisor/conf.d/laravel-worker.conf`:

```ini
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/laravel-app/artisan queue:work database --sleep=3 --tries=3
autostart=true
autorestart=true
user=constantk
numprocs=4
redirect_stderr=true
stdout_logfile=/var/www/laravel-app/storage/logs/worker.log
```

Start workers:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start laravel-worker:*
```

### CI/CD Pipeline

The project includes GitHub Actions workflow (`.github/workflows/deploy.yml`) for automated testing and deployment.

To enable CI/CD:

1. Add SSH private key to GitHub secrets:
   - Go to Settings > Secrets and variables > Actions
   - Add secret named `SSH_PRIVATE_KEY`
   - Paste contents of `ssh_key.pem`

2. Share repository with required users:
   - Go to Settings > Manage access > Add people
   - Add `johnefemer` and `RiyadMorshed`

The pipeline automatically runs tests and deploys on push to main branch.

## Troubleshooting

Queue not processing:
```bash
sudo supervisorctl status
sudo supervisorctl restart laravel-worker:*
```

Check logs:
```bash
tail -f storage/logs/laravel.log
tail -f storage/logs/worker.log
```

Database issues:
```bash
mysql -u root -p
SHOW DATABASES;
USE laravel_db;
SHOW TABLES;
```
