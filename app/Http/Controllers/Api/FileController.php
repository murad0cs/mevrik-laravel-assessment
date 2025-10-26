<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UploadFileRequest;
use App\Http\Resources\FileStatusResource;
use App\Http\Resources\FileUploadResource;
use App\Services\FileProcessingService;
use App\DTOs\FileUploadDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FileController extends Controller
{
    public function __construct(
        private FileProcessingService $fileService
    ) {}

    /**
     * Upload a file for processing
     */
    public function upload(UploadFileRequest $request): JsonResponse
    {
        $fileId = Str::uuid()->toString();

        $uploadDto = FileUploadDTO::fromRequest($request, $fileId);
        $status = $this->fileService->uploadAndQueue($uploadDto);

        return response()->json(new FileUploadResource($status), 201);
    }

    /**
     * Get file processing status
     */
    public function status(string $fileId): JsonResponse
    {
        $status = $this->fileService->getStatus($fileId);

        if (!$status) {
            return response()->json([
                'error' => 'File not found',
                'message' => 'No processing status found for this file ID'
            ], 404);
        }

        return response()->json(new FileStatusResource($status));
    }

    /**
     * Download processed file
     */
    public function download(string $fileId): BinaryFileResponse|JsonResponse
    {
        try {
            $fileInfo = $this->fileService->getProcessedFile($fileId);

            return response()->download(
                $fileInfo['path'],
                $fileInfo['name'],
                [
                    'Content-Type' => $fileInfo['mime'],
                    'X-File-Id' => $fileId,
                ]
            );

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Download failed',
                'message' => $e->getMessage(),
                'file_id' => $fileId
            ], 404);
        }
    }

    /**
     * Get processing statistics
     */
    public function statistics(): JsonResponse
    {
        $stats = $this->fileService->getStatistics();

        return response()->json([
            'status' => 'success',
            'statistics' => $stats,
            'timestamp' => now()->toDateTimeString()
        ]);
    }
}