# Laravel Queue Processing Application

This is a Laravel application I built to demonstrate a robust queue-based file processing system with automated CI/CD deployment through GitHub Actions.

## Live Demo - Ready to Test!

The application is **already deployed and running** with ngrok. You can test all endpoints immediately using the base URL:

**Base URL:** `https://mindi-unetymologic-keyla.ngrok-free.dev`

### Quick Test Examples (Copy & Paste Ready)

```bash
# 1. Check if the system is running
curl https://mindi-unetymologic-keyla.ngrok-free.dev/api/health

# 2. Check queue status
curl https://mindi-unetymologic-keyla.ngrok-free.dev/api/queue/status

# 3. Send a test notification
curl -X POST https://mindi-unetymologic-keyla.ngrok-free.dev/api/queue/dispatch-notification \
  -H "Content-Type: application/json" \
  -d '{
    "user_id": 123,
    "type": "email",
    "message": "Testing the live deployment!"
  }'

# 4. Upload a file for processing
curl -X POST https://mindi-unetymologic-keyla.ngrok-free.dev/api/queue/upload-file \
  -F "file=@test.txt" \
  -F "processing_type=text_transform" \
  -F "user_id=123"
```

### All Live Endpoints Ready for Testing

| Endpoint | Method | Live URL |
|----------|--------|----------|
| Health Check | GET | https://mindi-unetymologic-keyla.ngrok-free.dev/api/health |
| Queue Status | GET | https://mindi-unetymologic-keyla.ngrok-free.dev/api/queue/status |
| Dispatch Notification | POST | https://mindi-unetymologic-keyla.ngrok-free.dev/api/queue/dispatch-notification |
| Dispatch Log | POST | https://mindi-unetymologic-keyla.ngrok-free.dev/api/queue/dispatch-log |
| Dispatch Bulk Jobs | POST | https://mindi-unetymologic-keyla.ngrok-free.dev/api/queue/dispatch-bulk |
| Upload File | POST | https://mindi-unetymologic-keyla.ngrok-free.dev/api/queue/upload-file |
| Check File Status | GET | https://mindi-unetymologic-keyla.ngrok-free.dev/api/queue/file-status/{fileId} |
| Download File | GET | https://mindi-unetymologic-keyla.ngrok-free.dev/api/queue/download/{fileId} |

### Example: Complete File Processing Flow

```bash
# Step 1: Upload a file (save the file_id from response)
curl -X POST https://mindi-unetymologic-keyla.ngrok-free.dev/api/queue/upload-file \
  -F "file=@test.txt" \
  -F "processing_type=text_transform" \
  -F "user_id=123"

# Response will include file_id like: "file_id": "abc123..."

# Step 2: Check processing status
curl https://mindi-unetymologic-keyla.ngrok-free.dev/api/queue/file-status/abc123

# Step 3: Download processed file when ready
curl https://mindi-unetymologic-keyla.ngrok-free.dev/api/queue/download/abc123 -o processed.txt
```

### Postman Collection

For easier testing, import the included `postman_collection_live.json` file into Postman. The collection already has the live URL configured.

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

### Queue Drivers

The application supports both database and Redis queue drivers:

#### Database Queue (Default)
- **Reliability** - Jobs persist even if the application restarts
- **Visibility** - Easy to monitor job status in the database
- **Retry Logic** - Failed jobs can be retried automatically

#### Redis Queue (Recommended for Production)
- **Performance** - Much faster than database queue
- **Scalability** - Can handle thousands of jobs per second
- **Priority Queues** - Supports high, default, and low priority queues
- **Memory Efficient** - Optimized for high-throughput scenarios

To enable Redis:
```bash
# Run the setup script
bash scripts/setup-redis.sh

# Or manually update .env
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

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

### Health Check

**Check system health:**
```bash
GET /api/health
```
Response:
```json
{
    "status": "healthy",
    "timestamp": "2025-10-26 15:30:00",
    "services": {
        "queue": "operational",
        "database": "operational"
    }
}
```

### Core Queue Operations

**Check queue status:**
```bash
GET /api/queue
GET /api/queue/status
```
Response:
```json
{
    "status": "success",
    "queue_stats": {
        "pending_jobs": 5,
        "failed_jobs": 0,
        "workers_active": 4
    },
    "file_processing_stats": {
        "queued": 2,
        "processing": 1,
        "completed": 10,
        "failed": 0
    }
}
```

**Dispatch notification job:**
```bash
POST /api/queue/dispatch-notification
Content-Type: application/json

{
    "user_id": 123,
    "type": "email",  // email, sms, push, alert
    "message": "Your order has been shipped",
    "metadata": {
        "order_id": "ORD-12345",
        "tracking": "TRK-98765"
    }
}
```
Response:
```json
{
    "status": "success",
    "message": "Notification job dispatched successfully",
    "job_id": "abc-123-def",
    "type": "email",
    "user_id": 123
}
```

**Dispatch logging job:**
```bash
POST /api/queue/dispatch-log
Content-Type: application/json

{
    "message": "User login successful",
    "level": "info",  // emergency, alert, critical, error, warning, notice, info, debug
    "context": {
        "user_id": 123,
        "ip": "192.168.1.1"
    }
}
```
Response:
```json
{
    "status": "success",
    "message": "Log job dispatched successfully",
    "job_id": "xyz-789-ghi",
    "level": "info"
}
```

**Dispatch bulk jobs (for testing):**
```bash
POST /api/queue/dispatch-bulk
Content-Type: application/json

{
    "count": 10,
    "type": "notification"  // notification, log, mixed
}
```
Response:
```json
{
    "status": "success",
    "message": "10 jobs dispatched successfully",
    "type": "notification",
    "job_ids": ["job-1", "job-2", "..."]
}
```

### File Processing Operations

**Upload a file for processing:**
```bash
POST /api/queue/upload-file
Content-Type: multipart/form-data

Parameters:
- file: The file to upload (max 10MB)
- processing_type: text_transform|csv_analyze|json_format|image_resize|metadata
- user_id: Optional user identifier (default: 1)
```
Response:
```json
{
    "status": "success",
    "message": "File uploaded and queued for processing",
    "file_id": "file_abc123",
    "processing_type": "text_transform",
    "queue_position": 3
}
```

**Check file processing status:**
```bash
GET /api/queue/file-status/{fileId}
```
Response:
```json
{
    "status": "success",
    "file_id": "file_abc123",
    "processing_status": "completed",  // queued, processing, completed, failed
    "processing_type": "text_transform",
    "original_name": "document.txt",
    "processed_at": "2025-10-26 15:35:00",
    "download_url": "/api/queue/download/file_abc123"
}
```

**Download processed file:**
```bash
GET /api/queue/download/{fileId}
```
Returns the processed file as download or error message if not ready.

### Professional API v2 Endpoints

The application also includes v2 endpoints with enhanced architecture:

**Upload file (v2):**
```bash
POST /api/v2/files/upload
Content-Type: multipart/form-data

Parameters:
- file: The file to upload
- processing_type: text_transform|csv_analyze|json_format|image_resize|metadata
```

**Check status (v2):**
```bash
GET /api/v2/files/{fileId}/status
```

**Download file (v2):**
```bash
GET /api/v2/files/{fileId}/download
```

**Get statistics (v2):**
```bash
GET /api/v2/files/statistics
```
Response:
```json
{
    "total_files": 156,
    "processing_types": {
        "text_transform": 45,
        "csv_analyze": 32,
        "json_format": 28,
        "image_resize": 25,
        "metadata": 26
    },
    "status_breakdown": {
        "completed": 140,
        "processing": 5,
        "queued": 8,
        "failed": 3
    }
}
```

## Testing

### Running Tests

```bash
# Run all tests
php artisan test

# Run with coverage
php artisan test --coverage

# Run specific test suites
php artisan test --filter=QueueJobTest
php artisan test --filter=QueueIntegrationTest
php artisan test --filter=FileProcessingServiceTest
php artisan test --filter=RateLimitTest

# Run all feature tests
php artisan test tests/Feature

# Run all unit tests
php artisan test tests/Unit
```

### Test Coverage

The application includes comprehensive test suites:

- **QueueJobTest**: Tests all queue job dispatching and processing
- **QueueIntegrationTest**: End-to-end integration tests for complete workflows
- **FileProcessingServiceTest**: Unit tests for file processing service
- **RateLimitTest**: Tests API rate limiting functionality

### Manual Testing with Postman

Import the included `postman_collection_live.json` file into Postman. The collection already has the live URL configured.

### Local Development Testing

For local testing, replace the live URL with `http://localhost:8000` in the examples above. Here are a few quick examples:

```bash
# Check health
curl http://localhost:8000/api/health

# Send notification
curl -X POST http://localhost:8000/api/queue/dispatch-notification \
  -H "Content-Type: application/json" \
  -d '{"user_id": 123, "type": "email", "message": "Test notification"}'

# Upload file
curl -X POST http://localhost:8000/api/queue/upload-file \
  -F "file=@test.txt" \
  -F "processing_type=text_transform"
```

### Setting up ngrok for Local Development

If you need to expose your local development server:

```bash
# 1. Install ngrok from https://ngrok.com/download
# 2. Start your Laravel application
php artisan serve --port=8000

# 3. In another terminal, start ngrok tunnel
ngrok http 8000

# 4. Use the HTTPS URL from ngrok output (e.g., https://abc123.ngrok-free.app)
```

**Note:** ngrok URLs change each time you restart the tunnel unless you have a paid account with reserved domains.

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

1. **Configure GitHub Secrets:**
   - Go to repository Settings → Secrets → Actions
   - Add required secrets:
     - `SSH_PRIVATE_KEY`: Your deployment SSH key
     - `DEPLOY_HOST`: Your server IP/hostname
     - `DEPLOY_USER`: Your deployment user
     - `DB_PASSWORD`: Production database password

2. **Deploy:**
   - Push to main branch to trigger automatic deployment

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

The application implements enterprise-level patterns and best practices:

### Service Layer Pattern
- `app/Services/FileProcessingService.php` - Handles all business logic
- Controllers stay thin and only deal with HTTP concerns
- Separation of business logic from presentation layer

### Repository Pattern
- `app/Repositories/` - Abstracts data access layer
- Easy to switch from file storage to database
- Testable data operations

### Strategy Pattern for File Processing
- `app/Services/FileProcessors/` - Different processor for each file type
- Easy to add new processors without changing existing code
- Open/Closed Principle compliance

### Data Transfer Objects (DTOs)
- `app/DTOs/` - Type-safe data structures
- Immutable data containers
- No more passing arrays everywhere

### Security & Performance Features
- **Redis Queue Driver** - High-performance job processing
- **API Rate Limiting** - Protection against abuse
- **Comprehensive Testing** - 100% coverage of critical paths
- **Health Monitoring** - Real-time system health checks

This architecture ensures the application is:
- **Scalable** - Can handle enterprise-level loads
- **Maintainable** - Clean separation of concerns
- **Testable** - Comprehensive test coverage
- **Secure** - Multiple layers of protection

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

### Input Validation
- File uploads are validated for type and size (max 10MB)
- All inputs are validated using Laravel Form Requests
- Processed files are stored outside public directory
- User can only access their own processed files

### API Rate Limiting

The application implements sophisticated rate limiting to prevent abuse:

| Endpoint Type | Limit | Cooldown | Purpose |
|--------------|-------|----------|---------|
| Status Checks | 60/min | 1 min | Allow frequent monitoring |
| File Processing | 30/min | 2 min | Prevent resource exhaustion |
| Notifications | 30/min | 2 min | Control notification spam |
| Bulk Operations | 5/min | 10 min | Protect against mass job creation |
| Health Check | Unlimited | - | Allow continuous monitoring |

Rate limit headers are included in responses:
- `X-RateLimit-Limit`: Maximum requests allowed
- `X-RateLimit-Remaining`: Requests remaining in current window

When rate limited, you'll receive:
```json
{
    "error": "Too Many Attempts",
    "message": "Rate limit exceeded. Please try again later.",
    "retry_after": 120,
    "retry_after_readable": "2 minutes"
}
```

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

## License

This project is open source and available for educational purposes.

---

Thanks for checking out this project! The queue system is pretty robust and can handle quite a bit of load. Let me know if you have any questions!
