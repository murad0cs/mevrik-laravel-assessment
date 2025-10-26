<?php

namespace App\Exceptions;

class FileNotFoundException extends FileProcessingException
{
    public function __construct(string $fileId, string $path = '')
    {
        $message = "File not found: {$fileId}";
        if ($path) {
            $message .= " at path: {$path}";
        }

        parent::__construct(
            message: $message,
            fileId: $fileId,
            code: 404
        );
    }

    /**
     * Render the exception into an HTTP response.
     */
    public function render($request)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'error' => 'File not found',
                'message' => $this->getMessage(),
                'file_id' => $this->fileId,
            ], 404);
        }

        return null;
    }
}