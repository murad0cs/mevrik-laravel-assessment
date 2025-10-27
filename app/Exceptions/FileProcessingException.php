<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Support\Facades\Log;

class FileProcessingException extends Exception
{
    protected string $fileId;
    protected string $processingType;
    protected array $context;

    public function __construct(
        string $message,
        string $fileId = '',
        string $processingType = '',
        array $context = [],
        int $code = 0,
        \Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);

        $this->fileId = $fileId;
        $this->processingType = $processingType;
        $this->context = $context;
    }

    /**
     * Report the exception.
     */
    public function report(): void
    {
        Log::channel('file_processing')->error('File processing failed', [
            'file_id' => $this->fileId,
            'processing_type' => $this->processingType,
            'message' => $this->getMessage(),
            'context' => $this->context,
            'trace' => $this->getTraceAsString()
        ]);
    }

    /**
     * Render the exception into an HTTP response.
     */
    public function render($request)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'error' => 'File processing failed',
                'message' => $this->getMessage(),
                'file_id' => $this->fileId,
                'processing_type' => $this->processingType,
            ], 500);
        }

        return null;
    }

    /**
     * Get the file ID
     */
    public function getFileId(): string
    {
        return $this->fileId;
    }

    /**
     * Get the processing type
     */
    public function getProcessingType(): string
    {
        return $this->processingType;
    }

    /**
     * Get the context
     */
    public function getContext(): array
    {
        return $this->context;
    }
}