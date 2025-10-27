<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\FileProcessingService;
use App\Models\FileProcessingStatus;
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
            $processedFile = $this->fileService->getProcessedFile($fileId);

            if (!$processedFile) {
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
                    'success' => false,
                    'message' => 'File not found or processing not completed'
                ], 404);
            }

            // For browser requests, show download page
            if ($request->acceptsHtml() && !$request->has('direct')) {
                $status = FileProcessingStatus::where('file_id', $fileId)->first();

                return view('download', [
                    'error' => false,
                    'fileId' => $fileId,
                    'fileName' => $processedFile['name'],
                    'originalName' => $status->original_name ?? 'processed_file',
                    'fileSize' => filesize($processedFile['path']),
                    'status' => 'completed',
                    'processingType' => $status->processing_type ?? 'unknown',
                    'completedAt' => $status->completed_at ?? now(),
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
                'success' => false,
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
        $status = FileProcessingStatus::where('file_id', $fileId)->first();

        if (!$status) {
            return response()->json([
                'success' => false,
                'message' => 'File not found'
            ], 404);
        }

        if (!Storage::exists($status->file_path)) {
            return response()->json([
                'success' => false,
                'message' => 'Original file no longer exists'
            ], 404);
        }

        $path = storage_path('app/' . $status->file_path);

        return response()->download(
            $path,
            $status->original_name,
            [
                'Content-Type' => $status->mime_type ?? 'application/octet-stream',
                'Cache-Control' => 'no-cache, no-store, must-revalidate'
            ]
        );
    }

    /**
     * Get download URL for a processed file
     */
    public function getDownloadUrl(string $fileId)
    {
        $status = FileProcessingStatus::where('file_id', $fileId)->first();

        if (!$status || !$status->isCompleted()) {
            return response()->json([
                'success' => false,
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
            'success' => true,
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