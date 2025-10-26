# Laravel Queue Processing Application

This is a Laravel application I built to demonstrate a robust queue-based file processing system with automated CI/CD deployment through GitHub Actions.

## What This Project Does

The application processes uploaded files through a queue system, supporting various file types like text, CSV, JSON, and images. Each file gets processed asynchronously, and you can track the status and download the results when ready. Pretty neat for handling heavy file operations without blocking the main application!

## Project Repository

You can find the complete source code at: https://github.com/murad0cs/mevrik-laravel-assessment

## Tech Stack

- **Laravel 10** - The PHP framework
- **MySQL 8.0** - Database for queue jobs and application data
- **PHP 8.2** - Runtime environment
- **Supervisor** - Manages queue workers in production
- **GitHub Actions** - Handles CI/CD pipeline
- **Nginx** - Web server for production

## Setup Steps

### Local Development Setup

First, let's get you up and running locally:

1. **Clone the repository**
```bash
git clone https://github.com/murad0cs/mevrik-laravel-assessment.git
cd mevrik-laravel-assessment
```

2. **Install PHP dependencies**
```bash
composer install
```

3. **Set up your environment file**
```bash
cp .env.example .env
```

Now open `.env` and update these values:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=laravel_queue_db
DB_USERNAME=root
DB_PASSWORD=yourpassword

# This is important - we're using database queue driver
QUEUE_CONNECTION=database
```

4. **Generate application key**
```bash
php artisan key:generate
```

5. **Run database migrations**
```bash
php artisan migrate
```

This creates all the necessary tables including:
- `jobs` - Stores pending queue jobs
- `failed_jobs` - Tracks failed jobs for retry
- `job_batches` - For batch processing (if needed)

6. **Set up storage directories**
```bash
php artisan storage:setup
# This creates directories for file uploads and processing
```

7. **Start the application**
```bash
# Terminal 1 - Web server
php artisan serve

# Terminal 2 - Queue worker (important!)
php artisan queue:work --tries=3 --timeout=90
```

The queue worker is crucial - without it, your jobs won't process!

## Queue Configuration

### How the Queue System Works

I've configured the application to use Laravel's database queue driver. Here's why:
- **Reliability** - Jobs persist even if the application restarts
- **Visibility** - Easy to monitor job status in the database
- **Retry Logic** - Failed jobs can be retried automatically

### Queue Workers

In development, you run `php artisan queue:work` manually. But in production, we use Supervisor to manage multiple workers:

```ini
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/laravel-app/artisan queue:work --sleep=3 --tries=3 --timeout=90
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/www/laravel-app/storage/logs/worker.log
stopwaitsecs=3600
```

This configuration:
- Runs 4 workers in parallel (handles more jobs simultaneously)
- Retries failed jobs up to 3 times
- Restarts workers automatically if they crash
- Logs everything to `storage/logs/worker.log`

### Queue Job Examples

The application includes several queue jobs:

1. **ProcessFileJob** - The main file processing job
   - Handles text transformation, CSV analysis, JSON formatting, etc.
   - Updates status in real-time
   - Stores processed results

2. **ProcessNotification** - Sends notifications
   - Supports email, SMS, and push notifications
   - Logs all notification attempts

3. **WriteLogJob** - Custom logging system
   - Writes to separate log files based on severity
   - Useful for debugging and monitoring

## API Endpoints

### File Processing (Main Feature)

**Upload a file for processing:**
```bash
POST /api/queue/upload-file
Content-Type: multipart/form-data

Parameters:
- file: The file to upload (max 10MB)
- processing_type: text_transform|csv_analyze|json_format|image_resize|metadata
- user_id: Optional user identifier
```

**Check processing status:**
```bash
GET /api/queue/file-status/{fileId}
```

**Download processed file:**
```bash
GET /api/queue/download/{fileId}
```

### Queue Management

**Check queue health:**
```bash
GET /api/health
GET /api/queue/status
```

**Dispatch notification jobs:**
```bash
POST /api/queue/dispatch-notification
{
    "user_id": 123,
    "type": "email",
    "message": "Your file is ready!",
    "metadata": {"file_id": "abc-123"}
}
```

**Bulk job creation (for testing):**
```bash
POST /api/queue/dispatch-bulk
{
    "count": 10,
    "type": "notification"
}
```

## Testing

### Running Tests

```bash
# Run all tests
php artisan test

# Run with coverage
php artisan test --coverage

# Run specific test
php artisan test --filter=BasicTest
```

### Manual Testing with Postman

I've included a Postman collection (`postman_collection_live.json`) with all endpoints configured. Just import it and update the base URL.

### Quick Test with cURL

```bash
# Upload a text file
curl -X POST http://localhost:8000/api/queue/upload-file \
  -F "file=@sample.txt" \
  -F "processing_type=text_transform" \
  -F "user_id=123"

# You'll get a response with file_id
# Use it to check status
curl http://localhost:8000/api/queue/file-status/{file_id}

# When status is "completed", download the result
curl http://localhost:8000/api/queue/download/{file_id} -o result.txt
```

## Deployment Process

### CI/CD with GitHub Actions

The repository includes a complete CI/CD pipeline (`.github/workflows/deploy.yml`) that:

1. **On every push:**
   - Sets up PHP environment
   - Installs dependencies
   - Runs database migrations
   - Executes all tests

2. **On merge to main:**
   - Deploys to production server automatically
   - Runs migrations
   - Clears caches
   - Restarts queue workers

### Setting Up CI/CD

1. **Add SSH key to GitHub Secrets:**
   - Go to repository Settings → Secrets → Actions
   - Add new secret: `SSH_PRIVATE_KEY`
   - Paste your private key content

2. **Add database password (if needed):**
   - Add secret: `DB_PASSWORD`
   - Use your production database password

That's it! Push to main branch and watch the magic happen.

### Manual Deployment Steps

If you need to deploy manually (or understand what the CI/CD does):

1. **SSH into the server:**
```bash
ssh [your-user]@[your-server-ip]
# Server details are stored in GitHub Secrets
```

2. **Navigate to app directory:**
```bash
cd /var/www/laravel-app
```

3. **Pull latest code:**
```bash
git pull origin main
```

4. **Install/update dependencies:**
```bash
composer install --no-dev --optimize-autoloader
```

5. **Run migrations:**
```bash
php artisan migrate --force
```

6. **Clear and cache configs:**
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

7. **Set proper permissions:**
```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

8. **Restart queue workers:**
```bash
sudo supervisorctl restart laravel-worker:*
```

## Architecture Overview

I've implemented a clean architecture with proper separation of concerns:

### Service Layer Pattern
- `app/Services/FileProcessingService.php` - Handles all business logic
- Controllers stay thin and only deal with HTTP stuff

### Repository Pattern
- `app/Repositories/` - Abstracts data access
- Easy to switch from file storage to database later

### Strategy Pattern for File Processing
- `app/Services/FileProcessors/` - Different processor for each file type
- Easy to add new processors without changing existing code

### Data Transfer Objects (DTOs)
- `app/DTOs/` - Type-safe data structures
- No more passing arrays everywhere!

This makes the code much easier to test and maintain.

## File Processing Types

The application supports these processing types:

1. **text_transform** - Converts text to uppercase and adds line numbers
2. **csv_analyze** - Generates statistical analysis of CSV data
3. **json_format** - Validates and pretty-prints JSON files
4. **image_resize** - Analyzes image metadata (resize coming soon!)
5. **metadata** - Default processor that extracts file metadata

## Monitoring & Troubleshooting

### Check Queue Status

```bash
# See pending jobs
php artisan queue:monitor

# View failed jobs
php artisan queue:failed

# Retry all failed jobs
php artisan queue:retry all
```

### Common Issues and Fixes

**Queue jobs not processing?**
```bash
# Check if workers are running
sudo supervisorctl status

# Restart workers
sudo supervisorctl restart laravel-worker:*

# Check logs
tail -f storage/logs/worker.log
```

**Permission errors?**
```bash
sudo chown -R www-data:www-data storage
sudo chmod -R 775 storage
```

**Can't upload files?**
Check PHP settings:
```ini
upload_max_filesize = 10M
post_max_size = 10M
```

### Log Files

- Main application: `storage/logs/laravel.log`
- Queue workers: `storage/logs/worker.log`
- Notifications: `storage/logs/notifications/*.json`

## Performance Notes

- The application uses database queue for reliability
- 4 parallel workers process jobs concurrently
- File processing happens asynchronously (non-blocking)
- Processed files are cached for quick retrieval

## Security Considerations

- File uploads are validated for type and size
- All inputs are validated using Laravel Form Requests
- Processed files are stored outside public directory
- User can only access their own processed files

## Future Improvements

Some ideas for extending this:
- Add S3 storage for processed files
- Implement websocket notifications for real-time status
- Add more file processors (PDF, Excel, etc.)
- Create admin dashboard for queue monitoring

## Contributing

Feel free to fork and submit PRs! Make sure to:
1. Write tests for new features
2. Follow PSR-12 coding standards
3. Update documentation

## Support

If you run into issues:
1. Check the logs first (`storage/logs/`)
2. Ensure queue workers are running
3. Verify database connection
4. Check file permissions

## Author

Built by Murad for the Software Engineer assessment.

---

Thanks for checking out this project! The queue system is pretty robust and can handle quite a bit of load. Let me know if you have any questions!
