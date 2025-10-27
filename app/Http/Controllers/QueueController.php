<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessNotification;
use App\Jobs\WriteLogJob;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

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
