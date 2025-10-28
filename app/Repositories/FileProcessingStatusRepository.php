<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\StatusRepositoryInterface;
use App\DTOs\FileStatusDTO;
use App\Models\FileProcessingStatus;
use Illuminate\Database\Eloquent\Collection;

class FileProcessingStatusRepository implements StatusRepositoryInterface
{
    /**
     * Find a file status by ID (Interface method)
     */
    public function find(string $fileId): ?FileStatusDTO
    {
        $model = $this->findByFileId($fileId);

        if (!$model) {
            return null;
        }

        return new FileStatusDTO(
            fileId: $model->file_id,
            userId: $model->user_id,
            status: $model->status,
            processingType: $model->processing_type,
            originalFile: $model->file_path ?? '',
            processedFile: $model->processed_path,
            originalName: $model->original_name,
            uploadedAt: $model->created_at,
            completedAt: $model->completed_at,
            error: $model->error_message,
            downloadReady: $model->status === 'completed',
            metadata: $model->metadata ?? []
        );
    }

    /**
     * Save a file status (Interface method)
     */
    public function save(FileStatusDTO $status): void
    {
        FileProcessingStatus::updateOrCreate(
            ['file_id' => $status->fileId],
            [
                'user_id' => $status->userId,
                'status' => $status->status,
                'processing_type' => $status->processingType,
                'file_path' => $status->originalFile ?? '',
                'original_name' => $status->originalName,
                'processed_path' => $status->processedFile,
                'file_size' => 0, // Default, will be updated by job
                'mime_type' => null,
                'error_message' => $status->error,
                'completed_at' => $status->completedAt,
                'metadata' => $status->metadata,
            ]
        );
    }

    /**
     * Find all files by status (Interface method)
     */
    public function findByStatus(string $status): array
    {
        return FileProcessingStatus::where('status', $status)
            ->get()
            ->map(fn($model) => $this->modelToDTO($model))
            ->toArray();
    }

    /**
     * Convert model to DTO
     */
    private function modelToDTO(FileProcessingStatus $model): FileStatusDTO
    {
        return new FileStatusDTO(
            fileId: $model->file_id,
            userId: $model->user_id,
            status: $model->status,
            processingType: $model->processing_type,
            originalFile: $model->file_path ?? '',
            processedFile: $model->processed_path,
            originalName: $model->original_name,
            uploadedAt: $model->created_at,
            completedAt: $model->completed_at,
            error: $model->error_message,
            downloadReady: $model->status === 'completed',
            metadata: $model->metadata ?? []
        );
    }

    /**
     * Create a new file processing status
     */
    public function create(array $data): FileProcessingStatus
    {
        return FileProcessingStatus::create($data);
    }

    /**
     * Find by file ID
     */
    public function findByFileId(string $fileId): ?FileProcessingStatus
    {
        return FileProcessingStatus::where('file_id', $fileId)->first();
    }

    /**
     * Update status (Interface method)
     */
    public function updateStatus(string $fileId, string $status, array $additionalData = []): void
    {
        $record = $this->findByFileId($fileId);

        if (!$record) {
            \Log::warning('Cannot update status - file not found', ['file_id' => $fileId]);
            return;
        }

        $updateData = array_merge(['status' => $status], $additionalData);

        // Set appropriate timestamp based on status
        switch ($status) {
            case FileProcessingStatus::STATUS_PROCESSING:
                $updateData['started_at'] = now();
                break;
            case FileProcessingStatus::STATUS_COMPLETED:
                $updateData['completed_at'] = now();
                $updateData['progress'] = 100;
                break;
            case FileProcessingStatus::STATUS_FAILED:
                $updateData['failed_at'] = now();
                $updateData['retry_count'] = $record->retry_count + 1;
                break;
        }

        $record->update($updateData);
    }

    /**
     * Get all statuses for a user
     */
    public function getByUserId(int $userId, int $limit = 50): Collection
    {
        return FileProcessingStatus::forUser($userId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get user history (used by service)
     */
    public function getUserHistory(int $userId, int $limit = 50): array
    {
        return $this->getByUserId($userId, $limit)
            ->map(fn($model) => $this->modelToDTO($model))
            ->toArray();
    }

    /**
     * Get queue depth statistics
     */
    public function getQueueDepth(): array
    {
        return [
            'pending' => FileProcessingStatus::pending()->count(),
            'processing' => FileProcessingStatus::processing()->count(),
            'stale_processing' => FileProcessingStatus::processing()
                ->where('started_at', '<', now()->subMinutes(30))
                ->count(),
        ];
    }

    /**
     * Get statistics
     */
    public function getStatistics(): array
    {
        return [
            'total' => FileProcessingStatus::count(),
            'pending' => FileProcessingStatus::pending()->count(),
            'processing' => FileProcessingStatus::processing()->count(),
            'completed' => FileProcessingStatus::completed()->count(),
            'failed' => FileProcessingStatus::failed()->count(),
            'by_type' => FileProcessingStatus::selectRaw('processing_type, count(*) as count')
                ->groupBy('processing_type')
                ->pluck('count', 'processing_type')
                ->toArray(),
            'recent_failures' => FileProcessingStatus::failed()
                ->orderBy('failed_at', 'desc')
                ->limit(10)
                ->get(['file_id', 'original_name', 'error_message', 'failed_at']),
        ];
    }

    /**
     * Clean up old records
     */
    public function cleanupOldRecords(int $daysToKeep = 30): int
    {
        return FileProcessingStatus::whereIn('status', [
                FileProcessingStatus::STATUS_COMPLETED,
                FileProcessingStatus::STATUS_FAILED
            ])
            ->where('created_at', '<', now()->subDays($daysToKeep))
            ->delete();
    }
}