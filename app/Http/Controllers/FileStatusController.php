<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\FileProcessingService;
use App\Repositories\FileProcessingStatusRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FileStatusController extends Controller
{
    public function __construct(
        private FileProcessingService $fileService,
        private FileProcessingStatusRepository $statusRepository
    ) {}

    /**
     * Get file processing status
     */
    public function getStatus(string $fileId): JsonResponse
    {
        $status = $this->fileService->getStatus($fileId);

        if (!$status['success']) {
            return response()->json($status, 404);
        }

        return response()->json([
            'success' => true,
            'processing_status' => $status['status'],
            'file_id' => $fileId,
            'original_name' => $status['original_name'],
            'processing_type' => $status['processing_type'],
            'progress' => $status['progress'],
            'created_at' => $status['created_at'],
            'started_at' => $status['started_at'],
            'completed_at' => $status['completed_at'],
            'error_message' => $status['error_message'],
            'metadata' => $status['metadata']
        ]);
    }

    /**
     * Get batch status for multiple files
     */
    public function getBatchStatus(Request $request): JsonResponse
    {
        $request->validate([
            'file_ids' => 'required|array|min:1|max:50',
            'file_ids.*' => 'required|string'
        ]);

        $statuses = [];
        foreach ($request->input('file_ids') as $fileId) {
            $status = $this->fileService->getStatus($fileId);
            $statuses[$fileId] = $status;
        }

        return response()->json([
            'success' => true,
            'statuses' => $statuses
        ]);
    }

    /**
     * Get user's processing history
     */
    public function getUserHistory(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer',
            'limit' => 'nullable|integer|min:1|max:100'
        ]);

        $history = $this->fileService->getUserHistory(
            (int) $request->input('user_id'),
            (int) $request->input('limit', 50)
        );

        return response()->json([
            'success' => true,
            'user_id' => $request->input('user_id'),
            'files' => $history,
            'total' => count($history)
        ]);
    }

    /**
     * Get processing statistics
     */
    public function getStatistics(): JsonResponse
    {
        $stats = $this->fileService->getStatistics();

        return response()->json([
            'success' => true,
            'statistics' => $stats,
            'generated_at' => now()->toDateTimeString()
        ]);
    }

    /**
     * Retry failed processing
     */
    public function retry(string $fileId): JsonResponse
    {
        $result = $this->fileService->retryProcessing($fileId);

        if (!$result['success']) {
            return response()->json($result, 400);
        }

        return response()->json($result);
    }

    /**
     * Cancel processing
     */
    public function cancel(string $fileId): JsonResponse
    {
        $result = $this->fileService->cancelProcessing($fileId);

        if (!$result['success']) {
            return response()->json($result, 400);
        }

        return response()->json($result);
    }

    /**
     * Get queue depth and health
     */
    public function getQueueHealth(): JsonResponse
    {
        $queueDepth = $this->statusRepository->getQueueDepth();

        $health = 'healthy';
        if ($queueDepth['pending'] > 100 || $queueDepth['stale_processing'] > 10) {
            $health = 'degraded';
        }
        if ($queueDepth['pending'] > 500 || $queueDepth['stale_processing'] > 50) {
            $health = 'critical';
        }

        return response()->json([
            'success' => true,
            'health' => $health,
            'queue_depth' => $queueDepth,
            'timestamp' => now()->toDateTimeString()
        ]);
    }

    /**
     * Clean up old processing records
     */
    public function cleanup(Request $request): JsonResponse
    {
        $request->validate([
            'days' => 'nullable|integer|min:1|max:365'
        ]);

        $days = (int) $request->input('days', 30);
        $deleted = $this->statusRepository->cleanupOldRecords($days);

        return response()->json([
            'success' => true,
            'deleted_records' => $deleted,
            'retention_days' => $days,
            'message' => "Deleted {$deleted} records older than {$days} days"
        ]);
    }
}