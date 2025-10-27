<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\FileStorageInterface;
use App\Contracts\StatusRepositoryInterface;
use App\DTOs\FileStatusDTO;
use App\DTOs\FileUploadDTO;
use App\DTOs\ProcessingResultDTO;
use App\Jobs\ProcessFileJob;
use App\Services\FileProcessors\FileProcessorFactory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FileProcessingService
{
    public function __construct(
        private FileStorageInterface $storage,
        private StatusRepositoryInterface $statusRepository,
        private FileProcessorFactory $processorFactory
    ) {}

    /**
     * Handle file upload and queue processing
     */
    public function uploadAndQueue(FileUploadDTO $uploadDto): FileStatusDTO
    {
        try {
            // Store the uploaded file
            $fileName = $uploadDto->fileId . '.' . $uploadDto->getExtension();
            $storedPath = $this->storage->store($uploadDto->file, 'uploads');

            // Create initial status
            $status = new FileStatusDTO(
                fileId: $uploadDto->fileId,
                userId: $uploadDto->userId,
                status: 'queued',
                processingType: $uploadDto->processingType,
                originalFile: $fileName,
                originalName: $uploadDto->getOriginalName(),
                uploadedAt: now(),
                downloadReady: false,
                metadata: $uploadDto->metadata
            );

            // Save status
            $this->statusRepository->save($status);

            // Dispatch job for processing
            ProcessFileJob::dispatch(
                $uploadDto->fileId,
                $fileName,
                $uploadDto->processingType,
                $uploadDto->userId
            );

            Log::info('File uploaded and queued', [
                'file_id' => $uploadDto->fileId,
                'type' => $uploadDto->processingType
            ]);

            return $status;

        } catch (\Exception $e) {
            Log::error('File upload failed', [
                'file_id' => $uploadDto->fileId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Process a file using the appropriate processor
     */
    public function processFile(string $fileId): ProcessingResultDTO
    {
        $status = $this->statusRepository->find($fileId);

        if (!$status) {
            throw new \Exception("File status not found for ID: {$fileId}");
        }

        // Update status to processing
        $this->statusRepository->updateStatus($fileId, 'processing');

        try {
            // Get the appropriate processor
            $processor = $this->processorFactory->make($status->processingType);

            // Get the file path
            $filePath = $this->storage->path('uploads/' . $status->originalFile);

            // Process the file
            $result = $processor->process($filePath);

            if ($result->success) {
                // Save processed file
                $processedFileName = $this->saveProcessedFile($fileId, $result);

                // Update status to completed
                $this->statusRepository->updateStatus($fileId, 'completed', [
                    'processed_file' => $processedFileName
                ]);

                Log::info('File processing completed', [
                    'file_id' => $fileId,
                    'processed_file' => $processedFileName
                ]);
            } else {
                // Update status to failed
                $this->statusRepository->updateStatus($fileId, 'failed', [
                    'error' => $result->error
                ]);

                Log::error('File processing failed', [
                    'file_id' => $fileId,
                    'error' => $result->error
                ]);
            }

            return $result;

        } catch (\Exception $e) {
            $this->statusRepository->updateStatus($fileId, 'failed', [
                'error' => $e->getMessage()
            ]);

            Log::error('File processing exception', [
                'file_id' => $fileId,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Get file status
     */
    public function getStatus(string $fileId): ?FileStatusDTO
    {
        return $this->statusRepository->find($fileId);
    }

    /**
     * Download processed file
     */
    public function getProcessedFile(string $fileId): array
    {
        $status = $this->statusRepository->find($fileId);

        if (!$status) {
            throw new \Exception("File not found: {$fileId}");
        }

        if (!$status->isCompleted()) {
            throw new \Exception("File processing not completed. Status: {$status->status}");
        }

        // Try to find the processed file
        $processedFilePath = $this->findProcessedFile($status);

        if (!$processedFilePath) {
            throw new \Exception("Processed file not found for: {$fileId}");
        }

        return [
            'path' => $processedFilePath,
            'name' => 'processed_' . ($status->originalName ?? $status->originalFile),
            'mime' => 'application/octet-stream'
        ];
    }

    /**
     * Get queue statistics
     */
    public function getStatistics(): array
    {
        $queueStats = [
            'pending_jobs' => \DB::table('jobs')->count(),
            'failed_jobs' => \DB::table('failed_jobs')->count(),
        ];

        $fileStats = $this->statusRepository->getStatistics();

        return [
            'queue' => $queueStats,
            'files' => $fileStats
        ];
    }

    /**
     * Save processed file content
     */
    private function saveProcessedFile(string $fileId, ProcessingResultDTO $result): string
    {
        $fileName = $fileId . '_processed.' . $result->extension;
        $path = 'processed/' . $fileName;

        $this->storage->put($path, $result->content);

        return $fileName;
    }

    /**
     * Find processed file for a status
     */
    private function findProcessedFile(FileStatusDTO $status): ?string
    {
        // If processed_file field exists, use it
        if ($status->processedFile) {
            $path = $this->storage->path('processed/' . $status->processedFile);
            if (file_exists($path)) {
                return $path;
            }
        }

        // Try to find by pattern
        $extensions = ['txt', 'json', 'csv', 'log', 'xml'];
        foreach ($extensions as $ext) {
            $fileName = $status->fileId . '_processed.' . $ext;
            $path = $this->storage->path('processed/' . $fileName);
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }
}