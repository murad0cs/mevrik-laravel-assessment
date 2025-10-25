<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The notification data.
     *
     * @var array
     */
    protected $notificationData;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(array $notificationData)
    {
        $this->notificationData = $notificationData;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Log the notification processing start
            Log::info('Processing notification job started', [
                'job_id' => $this->job->getJobId(),
                'data' => $this->notificationData,
                'attempt' => $this->attempts(),
            ]);

            // Simulate notification processing
            $this->processNotification();

            // Log successful completion
            Log::info('Notification processed successfully', [
                'job_id' => $this->job->getJobId(),
                'user_id' => $this->notificationData['user_id'] ?? null,
                'type' => $this->notificationData['type'] ?? 'default',
            ]);

        } catch (\Exception $e) {
            // Log the error
            Log::error('Notification processing failed', [
                'job_id' => $this->job->getJobId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'attempt' => $this->attempts(),
            ]);

            // Re-throw to allow Laravel to handle retry logic
            throw $e;
        }
    }

    /**
     * Process the notification.
     *
     * @return void
     */
    protected function processNotification(): void
    {
        // Extract notification data
        $userId = $this->notificationData['user_id'] ?? null;
        $type = $this->notificationData['type'] ?? 'default';
        $message = $this->notificationData['message'] ?? 'No message provided';
        $metadata = $this->notificationData['metadata'] ?? [];

        // Simulate processing time (remove in production)
        sleep(2);

        // Here you would typically:
        // - Send email notification
        // - Send push notification
        // - Update database records
        // - Call external APIs
        // - etc.

        Log::info('Notification details', [
            'user_id' => $userId,
            'type' => $type,
            'message' => $message,
            'metadata' => $metadata,
            'processed_at' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        // Log the final failure
        Log::critical('Notification job failed permanently', [
            'data' => $this->notificationData,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        // Here you would typically:
        // - Send alert to administrators
        // - Store in failed notifications table
        // - Trigger fallback mechanisms
    }
}
