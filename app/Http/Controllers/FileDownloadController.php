<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\FileProcessingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FileDownloadController extends Controller
{
    public function __construct(
        private FileProcessingService $fileService
    ) {}

    /**
     * Download processed file
     */
    public function download(Request $request, string $fileId)
    {
        try {
            // Get file status from service layer
            $statusInfo = $this->fileService->getStatus($fileId);

            if (!$statusInfo['success']) {
                // Check if request is from browser
                if ($request->acceptsHtml() && !$request->has('direct')) {
                    return view('download', [
                        'error' => true,
                        'message' => 'File not found or processing not completed',
                        'fileId' => $fileId,
                        'status' => 'error'
                    ]);
                }

                return response()->json([
                    'status' => 'error',
                    'message' => 'File not found or processing not completed'
                ], 404);
            }

            // Check if processing is completed
            if ($statusInfo['status'] !== 'completed') {
                if ($request->acceptsHtml() && !$request->has('direct')) {
                    return view('download', [
                        'error' => false,
                        'fileId' => $fileId,
                        'status' => $statusInfo['status'],
                        'originalName' => $statusInfo['original_name'],
                        'processingType' => $statusInfo['processing_type'],
                        'message' => 'File is still being processed. Current status: ' . $statusInfo['status']
                    ]);
                }

                return response()->json([
                    'status' => 'error',
                    'message' => 'File processing not completed',
                    'current_status' => $statusInfo['status']
                ], 400);
            }

            // Get processed file
            $processedFile = $this->fileService->getProcessedFile($fileId);

            if (!$processedFile) {
                if ($request->acceptsHtml() && !$request->has('direct')) {
                    return view('download', [
                        'error' => true,
                        'message' => 'Processed file not found on disk',
                        'fileId' => $fileId,
                        'status' => 'error'
                    ]);
                }

                return response()->json([
                    'status' => 'error',
                    'message' => 'Processed file not found'
                ], 404);
            }

            // For browser requests, show download page
            if ($request->acceptsHtml() && !$request->has('direct')) {
                return view('download', [
                    'error' => false,
                    'fileId' => $fileId,
                    'fileName' => $processedFile['name'],
                    'originalName' => $statusInfo['original_name'],
                    'fileSize' => filesize($processedFile['path']),
                    'status' => 'completed',
                    'processingType' => $statusInfo['processing_type'],
                    'completedAt' => $statusInfo['completed_at'],
                    'downloadUrl' => route('queue.download', ['fileId' => $fileId, 'direct' => 1])
                ]);
            }

            // Direct file download
            return response()->download(
                $processedFile['path'],
                $processedFile['name'],
                [
                    'Content-Type' => $processedFile['mime_type'],
                    'Cache-Control' => 'no-cache, no-store, must-revalidate',
                    'Pragma' => 'no-cache',
                    'Expires' => '0'
                ]
            );

        } catch (\Exception $e) {
            \Log::error('Download failed', [
                'file_id' => $fileId,
                'error' => $e->getMessage()
            ]);

            if ($request->acceptsHtml()) {
                return view('download', [
                    'error' => true,
                    'message' => $e->getMessage(),
                    'fileId' => $fileId,
                    'status' => 'error'
                ]);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Download failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download original uploaded file
     */
    public function downloadOriginal(Request $request, string $fileId)
    {
        $statusInfo = $this->fileService->getStatus($fileId);

        if (!$statusInfo['success']) {
            return response()->json([
                'status' => 'error',
                'message' => 'File not found'
            ], 404);
        }

        $filePath = 'uploads/' . $fileId . '.' . pathinfo($statusInfo['original_name'], PATHINFO_EXTENSION);

        if (!Storage::exists($filePath)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Original file no longer exists'
            ], 404);
        }

        $path = storage_path('app/' . $filePath);

        return response()->download(
            $path,
            $statusInfo['original_name'],
            [
                'Content-Type' => 'application/octet-stream',
                'Cache-Control' => 'no-cache, no-store, must-revalidate'
            ]
        );
    }

    /**
     * Get download URL for a processed file
     */
    public function getDownloadUrl(string $fileId)
    {
        $statusInfo = $this->fileService->getStatus($fileId);

        if (!$statusInfo['success'] || $statusInfo['status'] !== 'completed') {
            return response()->json([
                'status' => 'error',
                'message' => 'File not ready for download'
            ], 404);
        }

        $url = route('queue.download', ['fileId' => $fileId]);
        $expiresAt = now()->addHours(24);

        // In production, you might want to generate a signed URL
        $signedUrl = \URL::temporarySignedRoute(
            'queue.download',
            $expiresAt,
            ['fileId' => $fileId]
        );

        return response()->json([
            'status' => 'success',
            'file_id' => $fileId,
            'download_url' => $url,
            'signed_url' => $signedUrl,
            'expires_at' => $expiresAt->toDateTimeString()
        ]);
    }

    /**
     * Stream large file download
     */
    public function stream(string $fileId): BinaryFileResponse
    {
        $processedFile = $this->fileService->getProcessedFile($fileId);

        if (!$processedFile) {
            abort(404, 'File not found');
        }

        // Use streaming for large files
        return response()->streamDownload(
            function () use ($processedFile) {
                $stream = fopen($processedFile['path'], 'r');
                while (!feof($stream)) {
                    echo fread($stream, 1024 * 8);
                    flush();
                }
                fclose($stream);
            },
            $processedFile['name'],
            [
                'Content-Type' => $processedFile['mime_type'],
                'Cache-Control' => 'no-cache'
            ]
        );
    }
}