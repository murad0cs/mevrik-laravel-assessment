<?php

namespace App\DTOs;

use Carbon\Carbon;

class FileStatusDTO
{
    public function __construct(
        public readonly string $fileId,
        public readonly int $userId,
        public readonly string $status,
        public readonly string $processingType,
        public readonly string $originalFile,
        public readonly ?string $processedFile = null,
        public readonly ?string $originalName = null,
        public readonly ?Carbon $uploadedAt = null,
        public readonly ?Carbon $completedAt = null,
        public readonly ?string $error = null,
        public readonly bool $downloadReady = false,
        public readonly array $metadata = []
    ) {}

    public static function create(array $data): self
    {
        return new self(
            fileId: $data['file_id'],
            userId: $data['user_id'],
            status: $data['status'],
            processingType: $data['processing_type'],
            originalFile: $data['original_file'],
            processedFile: $data['processed_file'] ?? null,
            originalName: $data['original_name'] ?? null,
            uploadedAt: isset($data['uploaded_at']) ? Carbon::parse($data['uploaded_at']) : null,
            completedAt: isset($data['completed_at']) ? Carbon::parse($data['completed_at']) : null,
            error: $data['error'] ?? null,
            downloadReady: $data['download_ready'] ?? false,
            metadata: $data['metadata'] ?? []
        );
    }

    public function toArray(): array
    {
        return [
            'file_id' => $this->fileId,
            'user_id' => $this->userId,
            'status' => $this->status,
            'processing_type' => $this->processingType,
            'original_file' => $this->originalFile,
            'processed_file' => $this->processedFile,
            'original_name' => $this->originalName,
            'uploaded_at' => $this->uploadedAt?->toDateTimeString(),
            'completed_at' => $this->completedAt?->toDateTimeString(),
            'error' => $this->error,
            'download_ready' => $this->downloadReady,
            'metadata' => $this->metadata,
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT);
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    public function isQueued(): bool
    {
        return $this->status === 'queued';
    }
}