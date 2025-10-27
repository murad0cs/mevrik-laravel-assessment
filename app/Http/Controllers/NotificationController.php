<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\DispatchNotificationRequest;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(
        private NotificationService $notificationService
    ) {}

    /**
     * Dispatch a notification to the queue
     */
    public function dispatch(DispatchNotificationRequest $request): JsonResponse
    {
        $result = $this->notificationService->dispatch($request->validated());

        if (!$result['success']) {
            return response()->json($result, 500);
        }

        return response()->json($result);
    }

    /**
     * Dispatch multiple notifications (batch)
     */
    public function dispatchBatch(Request $request): JsonResponse
    {
        $request->validate([
            'notifications' => 'required|array|min:1|max:100',
            'notifications.*.user_id' => 'required|integer',
            'notifications.*.type' => 'required|string|in:email,sms,push,alert,webhook',
            'notifications.*.message' => 'required|string|max:5000'
        ]);

        $results = $this->notificationService->sendBatch($request->input('notifications'));

        return response()->json([
            'success' => $results['failed'] === 0,
            'results' => $results
        ]);
    }

    /**
     * Get notification statistics
     */
    public function getStatistics(): JsonResponse
    {
        $stats = $this->notificationService->getStatistics();

        return response()->json([
            'success' => true,
            'statistics' => $stats,
            'timestamp' => now()->toDateTimeString()
        ]);
    }

    /**
     * Send test notification
     */
    public function sendTest(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|string|in:email,sms,push,webhook',
            'recipient' => 'required|string'
        ]);

        $testData = [
            'user_id' => 0, // Test user
            'type' => $request->input('type'),
            'message' => 'This is a test notification sent at ' . now()->toDateTimeString(),
            'metadata' => [
                'test' => true,
                'recipient' => $request->input('recipient')
            ]
        ];

        $result = $this->notificationService->dispatch($testData);

        return response()->json([
            'success' => $result['success'],
            'message' => $result['success']
                ? 'Test notification sent successfully'
                : 'Failed to send test notification',
            'result' => $result
        ]);
    }

    /**
     * Get notification templates
     */
    public function getTemplates(): JsonResponse
    {
        $templates = [
            'welcome' => [
                'name' => 'Welcome Email',
                'type' => 'email',
                'variables' => ['user_name', 'activation_link'],
                'default_message' => 'Welcome {user_name}! Click here to activate: {activation_link}'
            ],
            'password_reset' => [
                'name' => 'Password Reset',
                'type' => 'email',
                'variables' => ['user_name', 'reset_link', 'expires_in'],
                'default_message' => 'Hi {user_name}, click here to reset your password: {reset_link}. Link expires in {expires_in} minutes.'
            ],
            'order_confirmation' => [
                'name' => 'Order Confirmation',
                'type' => 'email',
                'variables' => ['order_id', 'total_amount', 'delivery_date'],
                'default_message' => 'Order #{order_id} confirmed. Total: ${total_amount}. Delivery by {delivery_date}.'
            ],
            'alert_critical' => [
                'name' => 'Critical Alert',
                'type' => 'alert',
                'variables' => ['service_name', 'error_message', 'timestamp'],
                'default_message' => 'CRITICAL: {service_name} error at {timestamp}: {error_message}'
            ]
        ];

        return response()->json([
            'success' => true,
            'templates' => $templates
        ]);
    }

    /**
     * Schedule a notification for later
     */
    public function schedule(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer',
            'type' => 'required|string|in:email,sms,push,alert,webhook',
            'message' => 'required|string|max:5000',
            'schedule_at' => 'required|date|after:now',
            'metadata' => 'nullable|array'
        ]);

        $notificationData = $request->all();
        $notificationData['scheduled'] = true;

        // In production, you would store this in database and use a scheduler
        // For now, we'll just queue it with delay
        $delay = now()->diffInSeconds($request->input('schedule_at'));

        $result = $this->notificationService->dispatch($notificationData);

        return response()->json([
            'success' => $result['success'],
            'message' => 'Notification scheduled',
            'scheduled_for' => $request->input('schedule_at'),
            'delay_seconds' => $delay,
            'result' => $result
        ]);
    }
}