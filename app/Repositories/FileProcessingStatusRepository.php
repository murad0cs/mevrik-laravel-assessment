<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\FileProcessingStatus;
use Illuminate\Database\Eloquent\Collection;

class FileProcessingStatusRepository
{
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
     * Update status
     */
    public function updateStatus(string $fileId, string $status, array $additionalData = []): bool
    {
        $record = $this->findByFileId($fileId);

        if (!$record) {
            return false;
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

        return $record->update($updateData);
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

    /**
     * Get processing queue depth
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
}