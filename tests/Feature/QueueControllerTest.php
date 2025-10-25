<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use App\Jobs\ProcessNotification;
use App\Jobs\WriteLogJob;
use Tests\TestCase;

class QueueControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test queue index endpoint.
     */
    public function test_queue_index_returns_successful_response(): void
    {
        $response = $this->getJson('/api/queue');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Queue system is operational',
            ])
            ->assertJsonStructure([
                'status',
                'message',
                'endpoints',
            ]);
    }

    /**
     * Test dispatching notification job.
     */
    public function test_can_dispatch_notification_job(): void
    {
        Queue::fake();

        $data = [
            'user_id' => 123,
            'type' => 'email',
            'message' => 'Test notification message',
            'metadata' => ['key' => 'value'],
        ];

        $response = $this->postJson('/api/queue/dispatch-notification', $data);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'message' => 'Notification job dispatched successfully',
            ]);

        Queue::assertPushed(ProcessNotification::class, function ($job) use ($data) {
            return $job->notificationData['user_id'] === $data['user_id']
                && $job->notificationData['type'] === $data['type'];
        });
    }

    /**
     * Test notification validation fails with invalid data.
     */
    public function test_notification_validation_fails_with_invalid_data(): void
    {
        $response = $this->postJson('/api/queue/dispatch-notification', [
            'user_id' => 'invalid',
            'type' => 'invalid_type',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'message' => 'Validation failed',
            ])
            ->assertJsonValidationErrors(['user_id', 'type', 'message']);
    }

    /**
     * Test dispatching log job.
     */
    public function test_can_dispatch_log_job(): void
    {
        Queue::fake();

        $data = [
            'message' => 'Test log message',
            'level' => 'info',
            'context' => ['user' => 'test'],
        ];

        $response = $this->postJson('/api/queue/dispatch-log', $data);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'message' => 'Log job dispatched successfully',
            ]);

        Queue::assertPushed(WriteLogJob::class, function ($job) use ($data) {
            return $job->logData['message'] === $data['message']
                && $job->level === $data['level'];
        });
    }

    /**
     * Test log validation with invalid level.
     */
    public function test_log_validation_fails_with_invalid_level(): void
    {
        $response = $this->postJson('/api/queue/dispatch-log', [
            'message' => 'Test message',
            'level' => 'invalid_level',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['level']);
    }

    /**
     * Test dispatching bulk jobs.
     */
    public function test_can_dispatch_bulk_jobs(): void
    {
        Queue::fake();

        $data = [
            'count' => 5,
            'type' => 'notification',
        ];

        $response = $this->postJson('/api/queue/dispatch-bulk', $data);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
            ])
            ->assertJsonPath('data.total_dispatched', 5);

        Queue::assertPushed(ProcessNotification::class, 5);
    }

    /**
     * Test bulk dispatch validation.
     */
    public function test_bulk_dispatch_validation_enforces_limits(): void
    {
        $response = $this->postJson('/api/queue/dispatch-bulk', [
            'count' => 150,
            'type' => 'notification',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['count']);
    }

    /**
     * Test mixed bulk dispatch.
     */
    public function test_can_dispatch_mixed_bulk_jobs(): void
    {
        Queue::fake();

        $data = [
            'count' => 10,
            'type' => 'mixed',
        ];

        $response = $this->postJson('/api/queue/dispatch-bulk', $data);

        $response->assertStatus(201);

        Queue::assertPushed(ProcessNotification::class);
        Queue::assertPushed(WriteLogJob::class);
    }

    /**
     * Test health check endpoint.
     */
    public function test_health_check_returns_healthy_status(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'healthy',
            ])
            ->assertJsonStructure([
                'status',
                'timestamp',
                'services',
            ]);
    }
}
