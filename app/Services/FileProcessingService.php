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
     * Process file upload (wrapper for controllers)
     */
    public function processUpload($file, string $processingType, ?int $userId = null, array $metadata = []): array
    {
        try {
            $fileId = Str::uuid()->toString();

            $uploadDto = new FileUploadDTO(
                file: $file,
                fileId: $fileId,
                userId: $userId,
                processingType: $processingType,
                metadata: $metadata
            );

            $status = $this->uploadAndQueue($uploadDto);

            return [
                'status' => 'success',
                'message' => 'File uploaded and queued for processing',
                'data' => [
                    'file_id' => $status->fileId,
                    'original_name' => $status->originalName,
                    'processing_type' => $status->processingType,
                    'status_url' => url('/api/queue/file-status/' . $status->fileId),
                    'download_url' => url('/api/queue/download/' . $status->fileId),
                ]
            ];

        } catch (\Exception $e) {
            Log::error('File upload failed', [
                'error' => $e->getMessage()
            ]);

            return [
                'status' => 'error',
                'message' => 'File upload failed',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Handle file upload and queue processing
     */
    public function uploadAndQueue(FileUploadDTO $uploadDto): FileStatusDTO
    {
        try {
            // Store the uploaded file with specific filename
            $fileName = $uploadDto->fileId . '.' . $uploadDto->getExtension();
            $storedPath = $this->storage->storeAs($uploadDto->file, 'uploads', $fileName);

            Log::info('File stored', [
                'file_id' => $uploadDto->fileId,
                'stored_path' => $storedPath,
                'filename' => $fileName
            ]);

            // Create initial status
            $status = new FileStatusDTO(
                fileId: $uploadDto->fileId,
                userId: $uploadDto->userId,
                status: 'pending',
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
                    'processed_path' => $processedFileName
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
    public function getStatus(string $fileId): array
    {
        $status = $this->statusRepository->find($fileId);

        if (!$status) {
            return [
                'success' => false,
                'message' => 'File not found'
            ];
        }

        return [
            'success' => true,
            'status' => $status->status,
            'file_id' => $status->fileId,
            'original_name' => $status->originalName,
            'processing_type' => $status->processingType,
            'progress' => 0,
            'created_at' => $status->uploadedAt?->toDateTimeString(),
            'started_at' => null,
            'completed_at' => $status->completedAt?->toDateTimeString(),
            'error_message' => $status->error ?? null,
            'metadata' => $status->metadata ?? []
        ];
    }

    /**
     * Download processed file
     */
    public function getProcessedFile(string $fileId): ?array
    {
        $status = $this->statusRepository->find($fileId);

        if (!$status) {
            return null;
        }

        if ($status->status !== 'completed') {
            return null;
        }

        // Try to find the processed file
        $processedFilePath = $this->findProcessedFile($status);

        if (!$processedFilePath) {
            return null;
        }

        return [
            'path' => $processedFilePath,
            'name' => 'processed_' . ($status->originalName ?? $status->originalFile),
            'mime_type' => 'application/octet-stream'
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
     * Get user processing history
     */
    public function getUserHistory(int $userId, int $limit = 50): array
    {
        return $this->statusRepository->getUserHistory($userId, $limit);
    }

    /**
     * Retry failed processing
     */
    public function retryProcessing(string $fileId): array
    {
        try {
            $status = $this->statusRepository->find($fileId);

            if (!$status) {
                return [
                    'success' => false,
                    'message' => 'File not found'
                ];
            }

            if ($status->status !== 'failed') {
                return [
                    'success' => false,
                    'message' => 'Can only retry failed processing'
                ];
            }

            // Reset status to pending
            $this->statusRepository->updateStatus($fileId, 'pending');

            // Re-dispatch the job
            ProcessFileJob::dispatch(
                $fileId,
                $status->originalFile,
                $status->processingType,
                $status->userId
            );

            Log::info('Processing retry queued', ['file_id' => $fileId]);

            return [
                'success' => true,
                'message' => 'Processing retry queued',
                'file_id' => $fileId
            ];

        } catch (\Exception $e) {
            Log::error('Retry processing failed', [
                'file_id' => $fileId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Retry failed',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Cancel processing
     */
    public function cancelProcessing(string $fileId): array
    {
        try {
            $status = $this->statusRepository->find($fileId);

            if (!$status) {
                return [
                    'success' => false,
                    'message' => 'File not found'
                ];
            }

            if ($status->status === 'completed') {
                return [
                    'success' => false,
                    'message' => 'Cannot cancel completed processing'
                ];
            }

            // Update status to cancelled
            $this->statusRepository->updateStatus($fileId, 'cancelled');

            Log::info('Processing cancelled', ['file_id' => $fileId]);

            return [
                'success' => true,
                'message' => 'Processing cancelled',
                'file_id' => $fileId
            ];

        } catch (\Exception $e) {
            Log::error('Cancel processing failed', [
                'file_id' => $fileId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Cancel failed',
                'error' => $e->getMessage()
            ];
        }
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