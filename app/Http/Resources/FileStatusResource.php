<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class FileStatusResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        return [
            'file_id' => $this->fileId,
            'user_id' => $this->userId,
            'status' => $this->status,
            'processing_type' => $this->processingType,
            'original_file' => $this->originalFile,
            'original_name' => $this->originalName,
            'processed_file' => $this->when(
                $this->processedFile,
                $this->processedFile
            ),
            'uploaded_at' => $this->uploadedAt?->toIso8601String(),
            'completed_at' => $this->when(
                $this->completedAt,
                $this->completedAt?->toIso8601String()
            ),
            'download_ready' => $this->downloadReady,
            'download_url' => $this->when(
                $this->downloadReady,
                route('api.file.download', ['fileId' => $this->fileId])
            ),
            'error' => $this->when(
                $this->error,
                $this->error
            ),
            'metadata' => $this->when(
                !empty($this->metadata),
                $this->metadata
            ),
        ];
    }
}