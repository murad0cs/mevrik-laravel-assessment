<?php

namespace Tests\Unit;

use App\Jobs\ProcessNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ProcessNotificationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test job can be instantiated.
     */
    public function test_job_can_be_instantiated(): void
    {
        $data = [
            'user_id' => 1,
            'type' => 'email',
            'message' => 'Test message',
        ];

        $job = new ProcessNotification($data);

        $this->assertInstanceOf(ProcessNotification::class, $job);
    }

    /**
     * Test job has correct properties.
     */
    public function test_job_has_correct_properties(): void
    {
        $data = [
            'user_id' => 1,
            'type' => 'email',
            'message' => 'Test message',
        ];

        $job = new ProcessNotification($data);

        $this->assertEquals(3, $job->tries);
        $this->assertEquals(120, $job->timeout);
    }

    /**
     * Test job can be serialized.
     */
    public function test_job_can_be_serialized(): void
    {
        $data = [
            'user_id' => 1,
            'type' => 'email',
            'message' => 'Test message',
        ];

        $job = new ProcessNotification($data);
        $serialized = serialize($job);

        $this->assertIsString($serialized);
        $this->assertNotEmpty($serialized);
    }

    /**
     * Test job logs processing information.
     */
    public function test_job_logs_processing_information(): void
    {
        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message, $context) {
                return $message === 'Processing notification job started';
            });

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message, $context) {
                return str_contains($message, 'Notification');
            });

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message, $context) {
                return $message === 'Notification processed successfully';
            });

        $data = [
            'user_id' => 1,
            'type' => 'email',
            'message' => 'Test message',
        ];

        $job = new ProcessNotification($data);

        // Set up a mock job instance for the handle method
        $mockJob = \Mockery::mock();
        $mockJob->shouldReceive('getJobId')->andReturn('test-job-id');

        // Set the job property
        $job->job = $mockJob;

        // Execute the job
        $job->handle();
    }
}
