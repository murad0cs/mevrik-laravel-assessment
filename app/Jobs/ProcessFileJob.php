<?php

namespace App\Jobs;

use App\Contracts\StatusRepositoryInterface;
use App\Services\FileProcessors\FileProcessorFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProcessFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $fileId;
    protected $filePath;
    protected $processingType;
    protected $userId;

    /**
     * Create a new job instance.
     */
    public function __construct(string $fileId, string $filePath, string $processingType, int $userId)
    {
        $this->fileId = $fileId;
        $this->filePath = $filePath;
        $this->processingType = $processingType;
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     */
    public function handle(
        StatusRepositoryInterface $statusRepository,
        FileProcessorFactory $processorFactory
    ): void {
        try {
            // Ensure all required directories exist with proper permissions
            $this->ensureDirectoriesExist();

            Log::info('Starting file processing', [
                'file_id' => $this->fileId,
                'type' => $this->processingType,
                'user_id' => $this->userId
            ]);

            // Update status to processing
            $statusRepository->updateStatus($this->fileId, 'processing');

            // Get the uploaded file path
            $uploadedFilePath = storage_path('app/uploads/' . $this->filePath);

            if (!file_exists($uploadedFilePath)) {
                throw new \Exception('File not found: ' . $uploadedFilePath);
            }

            // Process based on type using processor factory
            $processedFilePath = $this->processFile($uploadedFilePath, $processorFactory);

            // Store processed file info
            $statusRepository->updateStatus($this->fileId, 'completed', [
                'processed_file' => $processedFilePath
            ]);

            Log::info('File processing completed', [
                'file_id' => $this->fileId,
                'processed_file' => $processedFilePath
            ]);

        } catch (\Exception $e) {
            Log::error('File processing failed', [
                'file_id' => $this->fileId,
                'error' => $e->getMessage()
            ]);

            $statusRepository->updateStatus($this->fileId, 'failed', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Process the file using the appropriate processor
     */
    private function processFile(string $filePath, FileProcessorFactory $factory): string
    {
        // Ensure processed directory exists
        $processedDir = storage_path('app/processed');
        if (!file_exists($processedDir)) {
            mkdir($processedDir, 0755, true);
        }

        // Get the appropriate processor for this processing type
        $processor = $factory->make($this->processingType);

        Log::info('Using processor', [
            'file_id' => $this->fileId,
            'processor' => get_class($processor),
            'type' => $this->processingType
        ]);

        // Process the file and get result DTO
        $result = $processor->process($filePath);

        // Check if processing was successful
        if (!$result->success) {
            throw new \Exception($result->error ?? 'File processing failed');
        }

        // Generate filename with correct extension from processor
        $processedFileName = $this->fileId . '_processed.' . $result->extension;
        $processedFilePath = $processedDir . '/' . $processedFileName;

        // Save the processed content
        file_put_contents($processedFilePath, $result->content);

        Log::info('File processed successfully', [
            'file_id' => $this->fileId,
            'output_file' => $processedFileName,
            'extension' => $result->extension,
            'size' => strlen($result->content)
        ]);

        return $processedFileName;
    }

    /**
     * Ensure all required directories exist with proper permissions
     */
    private function ensureDirectoriesExist(): void
    {
        $directories = [
            storage_path('app/uploads'),
            storage_path('app/processed'),
            storage_path('app/processing_status'),
            storage_path('app/notifications'),
            storage_path('app/custom'),
        ];

        foreach ($directories as $dir) {
            if (!file_exists($dir)) {
                mkdir($dir, 0775, true);
                // Try to set ownership if possible (may fail on some systems)
                @chown($dir, 'www-data');
                @chgrp($dir, 'www-data');
            }
        }
    }

}