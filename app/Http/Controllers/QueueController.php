<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessNotification;
use App\Jobs\WriteLogJob;
use App\Jobs\ProcessFileJob;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class QueueController extends Controller
{
    /**
     * Display queue status and information.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => 'Queue system is operational',
            'endpoints' => [
                'dispatch_notification' => [
                    'method' => 'POST',
                    'url' => '/api/queue/dispatch-notification',
                    'description' => 'Dispatch a notification job to the queue',
                ],
                'dispatch_log' => [
                    'method' => 'POST',
                    'url' => '/api/queue/dispatch-log',
                    'description' => 'Dispatch a log writing job to the queue',
                ],
                'dispatch_bulk' => [
                    'method' => 'POST',
                    'url' => '/api/queue/dispatch-bulk',
                    'description' => 'Dispatch multiple jobs to the queue',
                ],
            ],
        ]);
    }

    /**
     * Dispatch a notification job to the queue.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function dispatchNotification(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer',
            'type' => 'required|string|in:email,sms,push,alert',
            'message' => 'required|string|max:1000',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $notificationData = [
            'user_id' => $request->input('user_id'),
            'type' => $request->input('type'),
            'message' => $request->input('message'),
            'metadata' => $request->input('metadata', []),
        ];

        // Dispatch the job to the queue
        ProcessNotification::dispatch($notificationData);

        return response()->json([
            'status' => 'success',
            'message' => 'Notification job dispatched successfully',
            'data' => $notificationData,
        ], 201);
    }

    /**
     * Dispatch a log writing job to the queue.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function dispatchLog(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'message' => 'required|string|max:1000',
            'level' => 'nullable|string|in:emergency,alert,critical,error,warning,notice,info,debug',
            'context' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $logData = [
            'message' => $request->input('message'),
            'context' => $request->input('context', []),
            'source' => 'api',
        ];

        $level = $request->input('level', 'info');

        // Dispatch the job to the queue
        WriteLogJob::dispatch($logData, $level);

        return response()->json([
            'status' => 'success',
            'message' => 'Log job dispatched successfully',
            'data' => [
                'log_data' => $logData,
                'level' => $level,
            ],
        ], 201);
    }

    /**
     * Dispatch multiple jobs to the queue.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function dispatchBulk(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'count' => 'required|integer|min:1|max:100',
            'type' => 'required|string|in:notification,log,mixed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $count = $request->input('count');
        $type = $request->input('type');
        $dispatched = [];

        for ($i = 1; $i <= $count; $i++) {
            if ($type === 'notification' || ($type === 'mixed' && $i % 2 === 0)) {
                $notificationData = [
                    'user_id' => rand(1, 1000),
                    'type' => ['email', 'sms', 'push', 'alert'][rand(0, 3)],
                    'message' => "Bulk notification #{$i}",
                    'metadata' => [
                        'batch' => true,
                        'batch_index' => $i,
                    ],
                ];
                ProcessNotification::dispatch($notificationData);
                $dispatched[] = ['type' => 'notification', 'index' => $i];
            }

            if ($type === 'log' || ($type === 'mixed' && $i % 2 !== 0)) {
                $logData = [
                    'message' => "Bulk log entry #{$i}",
                    'context' => [
                        'batch' => true,
                        'batch_index' => $i,
                    ],
                    'source' => 'bulk_api',
                ];
                WriteLogJob::dispatch($logData, 'info');
                $dispatched[] = ['type' => 'log', 'index' => $i];
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => "Successfully dispatched {$count} jobs",
            'data' => [
                'total_dispatched' => $count,
                'type' => $type,
                'jobs' => $dispatched,
            ],
        ], 201);
    }

    /**
     * Upload a file for queue processing
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function uploadFile(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:10240', // Max 10MB
            'processing_type' => 'required|string|in:text_transform,csv_analyze,image_resize,json_format,metadata',
            'user_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Generate unique file ID
            $fileId = Str::uuid()->toString();

            // Get uploaded file
            $uploadedFile = $request->file('file');
            $originalName = $uploadedFile->getClientOriginalName();
            $extension = $uploadedFile->getClientOriginalExtension();

            // Store file in uploads directory
            $fileName = $fileId . '.' . $extension;
            $uploadedFile->storeAs('uploads', $fileName);

            // Get processing type and user ID
            $processingType = $request->input('processing_type');
            $userId = $request->input('user_id', 1);

            // Create initial status file
            $statusData = [
                'file_id' => $fileId,
                'user_id' => $userId,
                'status' => 'queued',
                'original_name' => $originalName,
                'processing_type' => $processingType,
                'uploaded_at' => now()->toDateTimeString(),
                'download_ready' => false,
            ];

            // Use Storage facade for better permission handling
            $statusPath = 'processing_status/' . $fileId . '.json';
            Storage::put($statusPath, json_encode($statusData, JSON_PRETTY_PRINT));

            // Dispatch file processing job to queue
            ProcessFileJob::dispatch($fileId, $fileName, $processingType, $userId);

            return response()->json([
                'status' => 'success',
                'message' => 'File uploaded and queued for processing',
                'data' => [
                    'file_id' => $fileId,
                    'original_name' => $originalName,
                    'processing_type' => $processingType,
                    'status_url' => url('/api/queue/file-status/' . $fileId),
                    'download_url' => url('/api/queue/download/' . $fileId),
                ],
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'File upload failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check file processing status
     *
     * @param string $fileId
     * @return JsonResponse
     */
    public function fileStatus(string $fileId): JsonResponse
    {
        $statusPath = 'processing_status/' . $fileId . '.json';

        if (!Storage::exists($statusPath)) {
            return response()->json([
                'status' => 'error',
                'message' => 'File not found',
            ], 404);
        }

        $statusData = json_decode(Storage::get($statusPath), true);

        return response()->json([
            'status' => 'success',
            'data' => $statusData,
        ]);
    }

    /**
     * Download processed file
     *
     * @param string $fileId
     * @return BinaryFileResponse|JsonResponse
     */
    public function downloadProcessed(string $fileId)
    {
        try {
            $statusPath = 'processing_status/' . $fileId . '.json';

            if (!Storage::exists($statusPath)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'File not found',
                    'file_id' => $fileId,
                ], 404);
            }

            $statusData = json_decode(Storage::get($statusPath), true);

            if ($statusData['status'] !== 'completed') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'File processing not completed',
                    'current_status' => $statusData['status'],
                ], 400);
            }

            $processedFilePath = null;
            $processedFileName = null;

            // Check if processed_file field exists
            if (!isset($statusData['processed_file']) || empty($statusData['processed_file'])) {
                // Log for debugging
                Log::warning('Missing processed_file field for: ' . $fileId, ['status_data' => $statusData]);

                // Try to find the processed file using the file_id pattern
                // Get the original file extension if available
                $originalExt = 'txt';
                if (isset($statusData['original_file'])) {
                    $originalExt = pathinfo($statusData['original_file'], PATHINFO_EXTENSION) ?: 'txt';
                }

                // Try with original extension first
                $processedFileName = $fileId . '_processed.' . $originalExt;
                $processedFilePath = storage_path('app/processed/' . $processedFileName);

                // If not found, try other common extensions
                if (!file_exists($processedFilePath)) {
                    $extensions = ['txt', 'json', 'csv', 'log', 'xml'];
                    foreach ($extensions as $ext) {
                        $testFileName = $fileId . '_processed.' . $ext;
                        $testPath = storage_path('app/processed/' . $testFileName);
                        if (file_exists($testPath)) {
                            $processedFilePath = $testPath;
                            $processedFileName = $testFileName;
                            Log::info('Found processed file: ' . $processedFileName);
                            break;
                        }
                    }
                }

                // Still not found? List all files to help debug
                if (!file_exists($processedFilePath)) {
                    $allFiles = [];
                    if (is_dir(storage_path('app/processed'))) {
                        $files = scandir(storage_path('app/processed'));
                        $allFiles = array_diff($files, ['.', '..']);
                    }

                    return response()->json([
                        'status' => 'error',
                        'message' => 'Processed file not found after checking multiple extensions',
                        'file_id' => $fileId,
                        'checked_path' => 'storage/app/processed/' . $fileId . '_processed.*',
                        'available_files' => array_slice($allFiles, 0, 10), // Show first 10 files for debugging
                    ], 404);
                }
            } else {
                $processedFileName = $statusData['processed_file'];
                $processedFilePath = storage_path('app/processed/' . $processedFileName);
            }

            if (!file_exists($processedFilePath)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Processed file not found at expected path',
                    'expected_path' => 'storage/app/processed/' . $processedFileName,
                    'file_exists' => file_exists($processedFilePath),
                ], 404);
            }

            // Determine download filename
            $downloadName = 'processed_file.txt';
            if (isset($statusData['original_name'])) {
                $downloadName = 'processed_' . $statusData['original_name'];
            } elseif (isset($statusData['original_file'])) {
                $downloadName = 'processed_' . basename($statusData['original_file']);
            }

            return response()->download(
                $processedFilePath,
                $downloadName,
                [
                    'Content-Type' => 'application/octet-stream',
                    'X-Processing-Type' => $statusData['processing_type'] ?? 'unknown',
                    'X-File-Id' => $fileId,
                ]
            );

        } catch (\Exception $e) {
            Log::error('Download error for file: ' . $fileId, [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred during download',
                'error' => $e->getMessage(),
                'file_id' => $fileId,
            ], 500);
        }
    }

    /**
     * Get queue statistics including file processing jobs
     *
     * @return JsonResponse
     */
    public function queueStatus(): JsonResponse
    {
        $pendingJobs = DB::table('jobs')->count();
        $failedJobs = DB::table('failed_jobs')->count();

        // Count processing status files
        $processingStats = [
            'queued' => 0,
            'processing' => 0,
            'completed' => 0,
            'failed' => 0,
        ];

        $statusFiles = Storage::files('processing_status');
        foreach ($statusFiles as $file) {
            $data = json_decode(Storage::get($file), true);
            if (isset($data['status'])) {
                $processingStats[$data['status']] = ($processingStats[$data['status']] ?? 0) + 1;
            }
        }

        return response()->json([
            'status' => 'success',
            'queue_stats' => [
                'pending_jobs' => $pendingJobs,
                'failed_jobs' => $failedJobs,
                'workers_active' => 4, // Your supervisor config has 4 workers
            ],
            'file_processing_stats' => $processingStats,
            'endpoints' => [
                'upload_file' => [
                    'method' => 'POST',
                    'url' => '/api/queue/upload-file',
                    'description' => 'Upload a file for queue processing',
                ],
                'file_status' => [
                    'method' => 'GET',
                    'url' => '/api/queue/file-status/{fileId}',
                    'description' => 'Check file processing status',
                ],
                'download' => [
                    'method' => 'GET',
                    'url' => '/api/queue/download/{fileId}',
                    'description' => 'Download processed file',
                ],
            ],
        ]);
    }
}
