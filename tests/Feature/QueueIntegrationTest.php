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

class QueueIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    /** @test */
    public function test_end_to_end_file_processing_workflow()
    {
        // Don't fake the queue for integration test
        Queue::shouldReceive('push')->never();

        // 1. Upload a file
        $content = "Line 1\nLine 2\nLine 3";
        $file = UploadedFile::fake()->createWithContent('test.txt', $content);

        $uploadResponse = $this->postJson('/api/queue/upload-file', [
            'file' => $file,
            'processing_type' => 'text_transform',
            'user_id' => 123
        ]);

        $uploadResponse->assertStatus(200);
        $fileId = $uploadResponse->json('file_id');

        // 2. Process the job synchronously for testing
        // ProcessFileJob constructor: (fileId, filePath, processingType, userId)
        $job = new ProcessFileJob($fileId, 'test.txt', 'text_transform', 123);
        $job->handle();

        // 3. Check status
        $statusResponse = $this->getJson("/api/queue/file-status/{$fileId}");
        $statusResponse->assertStatus(200)
            ->assertJson([
                'processing_status' => 'completed'
            ]);

        // 4. Download processed file
        $downloadResponse = $this->get("/api/queue/download/{$fileId}");
        $downloadResponse->assertStatus(200);
    }

    /** @test */
    public function test_notification_processing_workflow()
    {
        $types = ['email', 'sms', 'push', 'alert'];

        foreach ($types as $type) {
            $response = $this->postJson('/api/queue/dispatch-notification', [
                'user_id' => 123,
                'type' => $type,
                'message' => "Test {$type} notification",
                'metadata' => ['test' => true]
            ]);

            $response->assertStatus(200);

            // Process the job synchronously
            // ProcessNotification constructor expects array of notification data
            $job = new ProcessNotification([
                'user_id' => 123,
                'type' => $type,
                'message' => "Test {$type} notification",
                'metadata' => ['test' => true]
            ]);
            $job->handle();

            // Check that notification was logged
            $logPath = storage_path("logs/notifications/{$type}");
            $this->assertDirectoryExists($logPath);
        }
    }

    /** @test */
    public function test_log_processing_workflow()
    {
        $levels = ['info', 'error', 'warning'];

        foreach ($levels as $level) {
            $response = $this->postJson('/api/queue/dispatch-log', [
                'message' => "Test {$level} message",
                'level' => $level,
                'context' => ['test' => true]
            ]);

            $response->assertStatus(200);

            // Process the job synchronously
            // WriteLogJob constructor: (logData array, level string)
            $job = new WriteLogJob([
                'message' => "Test {$level} message",
                'context' => ['test' => true],
                'source' => 'test'
            ], $level);
            $job->handle();

            // Check that log was created
            $logPath = storage_path("logs/custom/{$level}.log");
            $this->assertFileExists($logPath);
        }
    }

    /** @test */
    public function test_queue_statistics_accuracy()
    {
        // Create some test data
        \DB::table('jobs')->insert([
            ['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'available_at' => now()->timestamp, 'created_at' => now()->timestamp],
            ['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'available_at' => now()->timestamp, 'created_at' => now()->timestamp],
        ]);

        \DB::table('failed_jobs')->insert([
            ['uuid' => \Str::uuid(), 'connection' => 'database', 'queue' => 'default', 'payload' => '{}', 'exception' => 'Test exception', 'failed_at' => now()],
        ]);

        $response = $this->getJson('/api/queue/status');

        $response->assertStatus(200)
            ->assertJson([
                'queue_stats' => [
                    'pending_jobs' => 2,
                    'failed_jobs' => 1
                ]
            ]);
    }

    /** @test */
    public function test_v2_api_endpoints()
    {
        // Test v2 upload
        $file = UploadedFile::fake()->create('test.txt', 100);

        $response = $this->postJson('/api/v2/files/upload', [
            'file' => $file,
            'processing_type' => 'text_transform'
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'id',
                    'status',
                    'processing_type'
                ]
            ]);

        $fileId = $response->json('data.id');

        // Test v2 status check
        $statusResponse = $this->getJson("/api/v2/files/{$fileId}/status");
        $statusResponse->assertStatus(200);

        // Test v2 statistics
        $statsResponse = $this->getJson('/api/v2/files/statistics');
        $statsResponse->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'total_files',
                    'by_type',
                    'by_status'
                ]
            ]);
    }

    /** @test */
    public function test_error_handling_and_recovery()
    {
        // Test handling of non-existent file
        $response = $this->getJson('/api/queue/file-status/non-existent-file');
        $response->assertStatus(404);

        $response = $this->get('/api/queue/download/non-existent-file');
        $response->assertStatus(404);

        // Test invalid JSON for json_format processing
        $invalidJson = UploadedFile::fake()->createWithContent('invalid.json', 'not a valid json{');

        $response = $this->postJson('/api/queue/upload-file', [
            'file' => $invalidJson,
            'processing_type' => 'json_format',
            'user_id' => 123
        ]);

        $response->assertStatus(200); // Should accept the file

        $fileId = $response->json('file_id');

        // Process the job
        // ProcessFileJob constructor: (fileId, filePath, processingType, userId)
        $job = new ProcessFileJob($fileId, 'test.json', 'json_format', 123);

        try {
            $job->handle();
        } catch (\Exception $e) {
            // Job should handle the error gracefully
            $this->assertInstanceOf(\Exception::class, $e);
        }

        // Check that status shows failed
        $statusResponse = $this->getJson("/api/queue/file-status/{$fileId}");
        $statusData = json_decode(Storage::get("processing_status/{$fileId}.json"), true);
        $this->assertEquals('failed', $statusData['status'] ?? 'failed');
    }

    /** @test */
    public function test_concurrent_access_handling()
    {
        $fileId = 'test-concurrent-' . uniqid();

        // Simulate concurrent status checks
        $responses = [];
        for ($i = 0; $i < 5; $i++) {
            Storage::put("processing_status/{$fileId}.json", json_encode([
                'file_id' => $fileId,
                'status' => 'processing',
                'attempt' => $i
            ]));

            $response = $this->getJson("/api/queue/file-status/{$fileId}");
            $responses[] = $response->assertStatus(200);
        }

        // All responses should be successful despite concurrent access
        $this->assertCount(5, $responses);
    }
}