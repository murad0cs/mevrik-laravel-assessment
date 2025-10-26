<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

class HealthController extends Controller
{
    /**
     * Check application health status
     *
     * @return JsonResponse
     */
    public function check(): JsonResponse
    {
        $queueHealthy = true;
        $dbHealthy = true;
        $redisHealthy = true;
        $pendingJobs = 0;
        $failedJobs = 0;
        $errors = [];

        // Check database connection
        try {
            DB::connection()->getPdo();
            $pendingJobs = DB::table('jobs')->count();
            $failedJobs = DB::table('failed_jobs')->count();
        } catch (\Exception $e) {
            $dbHealthy = false;
            $errors['database'] = 'Connection failed: ' . $e->getMessage();
            Log::error('Health check - Database error', ['error' => $e->getMessage()]);
        }

        // Check Redis connection if configured
        if (config('queue.default') === 'redis' || config('cache.default') === 'redis') {
            try {
                // Test Redis connection
                $redis = Redis::connection();
                $redis->ping();

                // Get some Redis stats if available
                $redisInfo = $redis->info();
                $redisHealthy = true;
            } catch (\Exception $e) {
                $redisHealthy = false;
                $queueHealthy = false; // Queue is unhealthy if Redis is down
                $errors['redis'] = 'Connection failed: ' . $e->getMessage();
                Log::error('Health check - Redis error', ['error' => $e->getMessage()]);
            }
        }

        // Check if queue workers are running
        try {
            // Check if there are old jobs stuck in processing
            $stuckJobs = DB::table('jobs')
                ->where('created_at', '<', now()->subMinutes(30))
                ->count();

            if ($stuckJobs > 10) {
                $queueHealthy = false;
                $errors['queue'] = 'Found ' . $stuckJobs . ' stuck jobs (older than 30 minutes)';
            }
        } catch (\Exception $e) {
            // Non-critical, just log it
            Log::warning('Health check - Could not check stuck jobs', ['error' => $e->getMessage()]);
        }

        // Determine overall status
        $status = ($queueHealthy && $dbHealthy && $redisHealthy) ? 'healthy' : 'degraded';

        // Build response
        $response = [
            'status' => $status,
            'timestamp' => now()->toDateTimeString(),
            'services' => [
                'database' => $dbHealthy ? 'operational' : 'down',
                'queue' => $queueHealthy ? 'operational' : 'degraded',
                'cache' => $redisHealthy ? 'operational' : 'degraded',
            ],
            'metrics' => [
                'pending_jobs' => $pendingJobs,
                'failed_jobs' => $failedJobs,
            ],
            'environment' => app()->environment(),
        ];

        // Add errors if any
        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        // Add additional info in non-production environments
        if (app()->environment('local', 'development')) {
            $response['debug'] = [
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'queue_driver' => config('queue.default'),
                'cache_driver' => config('cache.default'),
            ];
        }

        // Return appropriate status code
        return response()->json($response, $status === 'healthy' ? 200 : 503);
    }

    /**
     * Simple ping endpoint for basic availability check
     *
     * @return JsonResponse
     */
    public function ping(): JsonResponse
    {
        return response()->json([
            'status' => 'pong',
            'timestamp' => now()->toDateTimeString(),
        ]);
    }
}