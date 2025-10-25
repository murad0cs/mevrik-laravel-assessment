<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessNotification;
use App\Jobs\WriteLogJob;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

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
}
