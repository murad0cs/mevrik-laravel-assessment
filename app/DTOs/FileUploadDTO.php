<?php

namespace App\DTOs;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class FileUploadDTO
{
    public function __construct(
        public readonly UploadedFile $file,
        public readonly string $processingType,
        public readonly int $userId,
        public readonly string $fileId,
        public readonly array $metadata = []
    ) {}

    public static function fromRequest(Request $request, string $fileId): self
    {
        return new self(
            file: $request->file('file'),
            processingType: $request->input('processing_type', 'metadata'),
            userId: $request->input('user_id', 1),
            fileId: $fileId,
            metadata: $request->input('metadata', [])
        );
    }

    public function getOriginalName(): string
    {
        return $this->file->getClientOriginalName();
    }

    public function getExtension(): string
    {
        return $this->file->getClientOriginalExtension();
    }

    public function getMimeType(): string
    {
        return $this->file->getMimeType();
    }

    public function getSize(): int
    {
        return $this->file->getSize();
    }
}