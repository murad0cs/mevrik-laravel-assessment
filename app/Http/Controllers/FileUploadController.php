<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UploadFileRequest;
use App\Services\FileProcessingService;
use App\Repositories\FileProcessingStatusRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FileUploadController extends Controller
{
    public function __construct(
        private FileProcessingService $fileService,
        private FileProcessingStatusRepository $statusRepository
    ) {}

    /**
     * Upload a file for processing
     */
    public function upload(UploadFileRequest $request): JsonResponse
    {
        try {
            $result = $this->fileService->processUpload(
                $request->file('file'),
                $request->input('processing_type'),
                $request->input('user_id') ? (int) $request->input('user_id') : null,
                $request->input('metadata', [])
            );

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('File upload failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'File upload failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload multiple files for batch processing
     */
    public function uploadBatch(Request $request): JsonResponse
    {
        $request->validate([
            'files' => 'required|array|min:1|max:10',
            'files.*' => 'required|file|max:10240',
            'processing_type' => 'required|string',
            'user_id' => 'nullable|integer'
        ]);

        $results = [];
        $errors = [];

        foreach ($request->file('files') as $index => $file) {
            try {
                $result = $this->fileService->processUpload(
                    $file,
                    $request->input('processing_type'),
                    $request->input('user_id') ? (int) $request->input('user_id') : null,
                    ['batch_index' => $index]
                );

                $results[] = $result;

            } catch (\Exception $e) {
                $errors[] = [
                    'file' => $file->getClientOriginalName(),
                    'error' => $e->getMessage()
                ];
            }
        }

        return response()->json([
            'success' => count($errors) === 0,
            'uploaded' => count($results),
            'failed' => count($errors),
            'results' => $results,
            'errors' => $errors
        ]);
    }

    /**
     * Get upload limits and configuration
     */
    public function getUploadConfig(): JsonResponse
    {
        return response()->json([
            'max_file_size' => config('file-processing.max_file_size', 10240),
            'allowed_extensions' => config('file-processing.allowed_extensions', [
                'txt', 'csv', 'json', 'xml', 'pdf', 'jpg', 'jpeg', 'png'
            ]),
            'processing_types' => [
                'text_transform' => 'Transform text files',
                'json_format' => 'Format and validate JSON',
                'csv_process' => 'Process CSV data',
                'image_process' => 'Process images',
                'metadata' => 'Extract file metadata'
            ],
            'batch_limit' => 10,
            'retention_days' => config('file-processing.retention_days', 30)
        ]);
    }

    /**
     * Validate file before upload
     */
    public function validateFile(Request $request): JsonResponse
    {
        $request->validate([
            'file_size' => 'required|integer',
            'file_type' => 'required|string',
            'file_name' => 'required|string'
        ]);

        $maxSize = config('file-processing.max_file_size', 10240) * 1024;
        $allowedTypes = config('file-processing.allowed_mime_types', []);

        $valid = true;
        $errors = [];

        if ($request->input('file_size') > $maxSize) {
            $valid = false;
            $errors[] = 'File size exceeds maximum allowed size';
        }

        if (!empty($allowedTypes) && !in_array($request->input('file_type'), $allowedTypes)) {
            $valid = false;
            $errors[] = 'File type is not allowed';
        }

        return response()->json([
            'valid' => $valid,
            'errors' => $errors,
            'max_size' => $maxSize,
            'allowed_types' => $allowedTypes
        ]);
    }
}