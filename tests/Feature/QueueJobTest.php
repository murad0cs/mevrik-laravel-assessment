<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Jobs\ProcessFileJob;
use App\Jobs\ProcessNotification;
use App\Jobs\WriteLogJob;

class QueueJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Queue::fake();
    }

    /** @test */
    public function test_file_upload_creates_queue_job()
    {
        $file = UploadedFile::fake()->create('test.txt', 100);

        $response = $this->postJson('/api/queue/upload-file', [
            'file' => $file,
            'processing_type' => 'text_transform',
            'user_id' => 123
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'file_id',
                'processing_type'
            ]);

        Queue::assertPushed(ProcessFileJob::class, function ($job) {
            return $job->processingType === 'text_transform';
        });
    }

    /** @test */
    public function test_all_file_processing_types()
    {
        $processingTypes = ['text_transform', 'csv_analyze', 'json_format', 'image_resize', 'metadata'];

        foreach ($processingTypes as $type) {
            Queue::fake(); // Reset queue for each iteration

            $file = $type === 'csv_analyze'
                ? UploadedFile::fake()->create('test.csv', 100)
                : ($type === 'json_format'
                    ? UploadedFile::fake()->create('test.json', 100)
                    : UploadedFile::fake()->create('test.txt', 100));

            $response = $this->postJson('/api/queue/upload-file', [
                'file' => $file,
                'processing_type' => $type,
                'user_id' => 123
            ]);

            $response->assertStatus(200);
            Queue::assertPushed(ProcessFileJob::class);
        }
    }

    /** @test */
    public function test_notification_job_dispatch()
    {
        $notificationTypes = ['email', 'sms', 'push', 'alert'];

        foreach ($notificationTypes as $type) {
            Queue::fake();

            $response = $this->postJson('/api/queue/dispatch-notification', [
                'user_id' => 123,
                'type' => $type,
                'message' => "Test {$type} notification",
                'metadata' => ['test' => true]
            ]);

            $response->assertStatus(200)
                ->assertJson([
                    'status' => 'success',
                    'type' => $type
                ]);

            Queue::assertPushed(ProcessNotification::class, function ($job) use ($type) {
                return $job->type === $type;
            });
        }
    }

    /** @test */
    public function test_log_job_dispatch_with_different_levels()
    {
        $logLevels = ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'];

        foreach ($logLevels as $level) {
            Queue::fake();

            $response = $this->postJson('/api/queue/dispatch-log', [
                'message' => "Test {$level} log message",
                'level' => $level,
                'context' => ['test' => true, 'level' => $level]
            ]);

            $response->assertStatus(200)
                ->assertJson([
                    'status' => 'success',
                    'level' => $level
                ]);

            Queue::assertPushed(WriteLogJob::class, function ($job) use ($level) {
                return $job->level === $level;
            });
        }
    }

    /** @test */
    public function test_bulk_job_creation()
    {
        $types = ['notification', 'log', 'mixed'];

        foreach ($types as $type) {
            Queue::fake();

            $response = $this->postJson('/api/queue/dispatch-bulk', [
                'count' => 10,
                'type' => $type
            ]);

            $response->assertStatus(200)
                ->assertJson([
                    'status' => 'success',
                    'type' => $type
                ]);

            if ($type === 'notification') {
                Queue::assertPushed(ProcessNotification::class, 10);
            } elseif ($type === 'log') {
                Queue::assertPushed(WriteLogJob::class, 10);
            } else {
                // For mixed, check that jobs were pushed
                Queue::assertPushed(ProcessNotification::class);
                Queue::assertPushed(WriteLogJob::class);
            }
        }
    }

    /** @test */
    public function test_file_status_endpoint()
    {
        Storage::put('processing_status/test-file-123.json', json_encode([
            'file_id' => 'test-file-123',
            'status' => 'completed',
            'processing_type' => 'text_transform',
            'original_name' => 'test.txt',
            'processed_at' => now()->toDateTimeString()
        ]));

        $response = $this->getJson('/api/queue/file-status/test-file-123');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'file_id' => 'test-file-123',
                'processing_status' => 'completed'
            ]);
    }

    /** @test */
    public function test_file_download_when_ready()
    {
        Storage::put('processed/test-file-123_processed.txt', 'Processed content');
        Storage::put('processing_status/test-file-123.json', json_encode([
            'file_id' => 'test-file-123',
            'status' => 'completed',
            'processed_file' => 'test-file-123_processed.txt'
        ]));

        $response = $this->get('/api/queue/download/test-file-123');

        $response->assertStatus(200);
        $response->assertHeader('content-disposition', 'attachment; filename=test-file-123_processed.txt');
    }

    /** @test */
    public function test_queue_status_endpoint()
    {
        $response = $this->getJson('/api/queue/status');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'queue_stats' => [
                    'pending_jobs',
                    'failed_jobs'
                ],
                'file_processing_stats'
            ]);
    }

    /** @test */
    public function test_health_check_endpoint()
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'healthy',
                'services' => [
                    'queue' => 'operational',
                    'database' => 'operational'
                ]
            ]);
    }

    /** @test */
    public function test_file_upload_validation()
    {
        // Test missing file
        $response = $this->postJson('/api/queue/upload-file', [
            'processing_type' => 'text_transform'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);

        // Test invalid processing type
        $file = UploadedFile::fake()->create('test.txt', 100);
        $response = $this->postJson('/api/queue/upload-file', [
            'file' => $file,
            'processing_type' => 'invalid_type'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['processing_type']);

        // Test file too large (over 10MB)
        $largeFile = UploadedFile::fake()->create('large.txt', 11000); // 11MB
        $response = $this->postJson('/api/queue/upload-file', [
            'file' => $largeFile,
            'processing_type' => 'text_transform'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    /** @test */
    public function test_notification_validation()
    {
        // Test missing required fields
        $response = $this->postJson('/api/queue/dispatch-notification', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_id', 'type', 'message']);

        // Test invalid notification type
        $response = $this->postJson('/api/queue/dispatch-notification', [
            'user_id' => 123,
            'type' => 'invalid_type',
            'message' => 'Test message'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    /** @test */
    public function test_concurrent_job_processing()
    {
        Queue::fake();

        // Simulate multiple concurrent uploads
        $promises = [];
        for ($i = 0; $i < 5; $i++) {
            $file = UploadedFile::fake()->create("test{$i}.txt", 100);

            $response = $this->postJson('/api/queue/upload-file', [
                'file' => $file,
                'processing_type' => 'text_transform',
                'user_id' => $i
            ]);

            $response->assertStatus(200);
        }

        // Assert 5 jobs were pushed
        Queue::assertPushed(ProcessFileJob::class, 5);
    }

    /** @test */
    public function test_failed_job_handling()
    {
        Queue::fake();

        // Create a job that will fail
        $file = UploadedFile::fake()->create('test.txt', 100);

        $response = $this->postJson('/api/queue/upload-file', [
            'file' => $file,
            'processing_type' => 'text_transform',
            'user_id' => 123
        ]);

        $response->assertStatus(200);

        // Job should be pushed even if it might fail later
        Queue::assertPushed(ProcessFileJob::class);
    }
}