<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\FileProcessingService;
use Illuminate\Support\Facades\Log;

class ProcessFileJobRefactored implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     *
     * @var int
     */
    public $maxExceptions = 3;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected string $fileId,
        protected string $filePath,
        protected string $processingType,
        protected int $userId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(FileProcessingService $fileService): void
    {
        Log::info('Starting file processing job', [
            'file_id' => $this->fileId,
            'type' => $this->processingType,
            'user_id' => $this->userId
        ]);

        try {
            // Process the file using the service
            $result = $fileService->processFile($this->fileId);

            if ($result->success) {
                Log::info('File processing completed successfully', [
                    'file_id' => $this->fileId,
                    'size' => $result->getFileSize()
                ]);

                // Dispatch any events if needed
                // event(new FileProcessingCompleted($this->fileId, $this->userId));
            } else {
                Log::warning('File processing completed with errors', [
                    'file_id' => $this->fileId,
                    'error' => $result->error
                ]);
            }

        } catch (\Exception $e) {
            Log::error('File processing job failed', [
                'file_id' => $this->fileId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Re-throw to mark job as failed
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('File processing job permanently failed', [
            'file_id' => $this->fileId,
            'exception' => $exception->getMessage()
        ]);

        // Update status to failed via service
        try {
            $fileService = app(FileProcessingService::class);
            $statusRepository = app(\App\Contracts\StatusRepositoryInterface::class);

            $statusRepository->updateStatus($this->fileId, 'failed', [
                'error' => $exception->getMessage()
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update status after job failure', [
                'file_id' => $this->fileId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'file-processing',
            'file-id:' . $this->fileId,
            'type:' . $this->processingType,
            'user:' . $this->userId
        ];
    }
}