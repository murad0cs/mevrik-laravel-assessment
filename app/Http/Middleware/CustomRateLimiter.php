<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Cache\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class CustomRateLimiter
{
    protected RateLimiter $limiter;

    public function __construct(RateLimiter $limiter)
    {
        $this->limiter = $limiter;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $type
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string $type = 'default')
    {
        $key = $this->resolveRequestSignature($request, $type);
        $maxAttempts = $this->getMaxAttempts($type);
        $decayMinutes = $this->getDecayMinutes($type);

        if ($this->limiter->tooManyAttempts($key, $maxAttempts)) {
            return $this->buildResponse($request, $key, $maxAttempts);
        }

        $this->limiter->hit($key, $decayMinutes * 60);

        $response = $next($request);

        return $this->addHeaders(
            $response,
            $maxAttempts,
            $this->calculateRemainingAttempts($key, $maxAttempts)
        );
    }

    /**
     * Get the rate limit signature for the request.
     */
    protected function resolveRequestSignature(Request $request, string $type): string
    {
        // Don't try to get user if no auth guard is configured
        $identifier = $request->ip();

        return sprintf(
            'rate_limit:%s:%s:%s',
            $type,
            $identifier,
            $request->fingerprint()
        );
    }

    /**
     * Get max attempts based on type.
     */
    protected function getMaxAttempts(string $type): int
    {
        return match ($type) {
            'strict' => 10,        // 10 requests per minute for critical endpoints
            'moderate' => 30,      // 30 requests per minute for file processing
            'relaxed' => 60,       // 60 requests per minute for status checks
            'bulk' => 5,           // 5 requests per minute for bulk operations
            default => 60,         // Default to 60 requests per minute
        };
    }

    /**
     * Get decay minutes based on type.
     */
    protected function getDecayMinutes(string $type): int
    {
        return match ($type) {
            'strict' => 5,         // 5 minute cooldown
            'moderate' => 2,       // 2 minute cooldown
            'relaxed' => 1,        // 1 minute cooldown
            'bulk' => 10,          // 10 minute cooldown for bulk
            default => 1,
        };
    }

    /**
     * Create a 'too many attempts' response.
     */
    protected function buildResponse(Request $request, string $key, int $maxAttempts): Response
    {
        $retryAfter = $this->limiter->availableIn($key);

        if ($request->expectsJson()) {
            return response()->json([
                'error' => 'Too Many Attempts',
                'message' => 'Rate limit exceeded. Please try again later.',
                'retry_after' => $retryAfter,
                'retry_after_readable' => $this->humanReadableTime($retryAfter),
            ], 429);
        }

        return response('Too Many Attempts. Retry after ' . $retryAfter . ' seconds.', 429);
    }

    /**
     * Add rate limit headers to response.
     */
    protected function addHeaders(Response $response, int $maxAttempts, int $remainingAttempts): Response
    {
        // Use headers->set() which works for all response types including BinaryFileResponse
        $response->headers->set('X-RateLimit-Limit', $maxAttempts);
        $response->headers->set('X-RateLimit-Remaining', $remainingAttempts);

        return $response;
    }

    /**
     * Calculate remaining attempts.
     */
    protected function calculateRemainingAttempts(string $key, int $maxAttempts): int
    {
        $attempts = $this->limiter->attempts($key);
        return max(0, $maxAttempts - $attempts);
    }

    /**
     * Convert seconds to human-readable format.
     */
    protected function humanReadableTime(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds . ' seconds';
        }

        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60;

        if ($remainingSeconds > 0) {
            return $minutes . ' minutes and ' . $remainingSeconds . ' seconds';
        }

        return $minutes . ' minutes';
    }
}