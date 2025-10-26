<?php

namespace App\Repositories;

use App\Contracts\StatusRepositoryInterface;
use App\DTOs\FileStatusDTO;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class FileStatusRepository implements StatusRepositoryInterface
{
    private const STATUS_DIRECTORY = 'processing_status';

    /**
     * Find a file status by ID
     */
    public function find(string $fileId): ?FileStatusDTO
    {
        $path = $this->getStatusPath($fileId);

        if (!Storage::exists($path)) {
            return null;
        }

        try {
            $data = json_decode(Storage::get($path), true);
            return FileStatusDTO::create($data);
        } catch (\Exception $e) {
            Log::error('Failed to read status file', [
                'file_id' => $fileId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Save a file status
     */
    public function save(FileStatusDTO $status): void
    {
        $path = $this->getStatusPath($status->fileId);

        try {
            Storage::put($path, $status->toJson());
        } catch (\Exception $e) {
            Log::error('Failed to save status file', [
                'file_id' => $status->fileId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Update file status
     */
    public function updateStatus(string $fileId, string $status, array $additionalData = []): void
    {
        $statusDto = $this->find($fileId);

        if (!$statusDto) {
            Log::warning('Status file not found for update', ['file_id' => $fileId]);
            return;
        }

        $data = $statusDto->toArray();
        $data['status'] = $status;
        $data['updated_at'] = now()->toDateTimeString();

        if ($status === 'completed') {
            $data['completed_at'] = now()->toDateTimeString();
            $data['download_ready'] = true;
        }

        if ($status === 'failed' && isset($additionalData['error'])) {
            $data['error'] = $additionalData['error'];
        }

        if (isset($additionalData['processed_file'])) {
            $data['processed_file'] = $additionalData['processed_file'];
        }

        $updatedStatus = FileStatusDTO::create(array_merge($data, $additionalData));
        $this->save($updatedStatus);
    }

    /**
     * Get all files with a specific status
     */
    public function findByStatus(string $status): array
    {
        $results = [];
        $files = Storage::files(self::STATUS_DIRECTORY);

        foreach ($files as $file) {
            try {
                $data = json_decode(Storage::get($file), true);
                if ($data['status'] === $status) {
                    $results[] = FileStatusDTO::create($data);
                }
            } catch (\Exception $e) {
                Log::warning('Failed to read status file', ['file' => $file]);
                continue;
            }
        }

        return $results;
    }

    /**
     * Get statistics grouped by status
     */
    public function getStatistics(): array
    {
        $stats = [
            'queued' => 0,
            'processing' => 0,
            'completed' => 0,
            'failed' => 0,
        ];

        $files = Storage::files(self::STATUS_DIRECTORY);

        foreach ($files as $file) {
            try {
                $data = json_decode(Storage::get($file), true);
                $status = $data['status'] ?? 'unknown';

                if (isset($stats[$status])) {
                    $stats[$status]++;
                }
            } catch (\Exception $e) {
                Log::warning('Failed to read status file for stats', ['file' => $file]);
                continue;
            }
        }

        return $stats;
    }

    /**
     * Get the path for a status file
     */
    private function getStatusPath(string $fileId): string
    {
        return self::STATUS_DIRECTORY . '/' . $fileId . '.json';
    }
}