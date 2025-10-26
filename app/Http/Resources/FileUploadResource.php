<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class FileUploadResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        return [
            'status' => 'success',
            'message' => 'File uploaded and queued for processing',
            'data' => [
                'file_id' => $this->fileId,
                'original_name' => $this->originalName,
                'processing_type' => $this->processingType,
                'status' => $this->status,
                'uploaded_at' => $this->uploadedAt?->toIso8601String(),
                'status_url' => route('api.file.status', ['fileId' => $this->fileId]),
                'download_url' => route('api.file.download', ['fileId' => $this->fileId]),
            ]
        ];
    }
}