<?php

namespace App\DTOs;

class ProcessingResultDTO
{
    public function __construct(
        public readonly string $content,
        public readonly string $mimeType = 'text/plain',
        public readonly string $extension = 'txt',
        public readonly array $metadata = [],
        public readonly bool $success = true,
        public readonly ?string $error = null
    ) {}

    public static function success(
        string $content,
        string $mimeType = 'text/plain',
        string $extension = 'txt',
        array $metadata = []
    ): self {
        return new self(
            content: $content,
            mimeType: $mimeType,
            extension: $extension,
            metadata: $metadata,
            success: true,
            error: null
        );
    }

    public static function failure(string $error): self
    {
        return new self(
            content: '',
            mimeType: 'text/plain',
            extension: 'txt',
            metadata: [],
            success: false,
            error: $error
        );
    }

    public function getFileSize(): int
    {
        return strlen($this->content);
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'mime_type' => $this->mimeType,
            'extension' => $this->extension,
            'size' => $this->getFileSize(),
            'metadata' => $this->metadata,
            'error' => $this->error,
        ];
    }
}