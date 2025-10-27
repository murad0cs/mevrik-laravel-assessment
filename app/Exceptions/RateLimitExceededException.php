<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class RateLimitExceededException extends Exception
{
    protected int $limit;
    protected int $remainingTime;
    protected string $tier;

    /**
     * Create a new rate limit exceeded exception
     */
    public function __construct(
        int $limit,
        int $remainingTime,
        string $tier = 'default',
        string $message = '',
        int $code = 429,
        ?Exception $previous = null
    ) {
        $this->limit = $limit;
        $this->remainingTime = $remainingTime;
        $this->tier = $tier;

        if (empty($message)) {
            $message = "Rate limit exceeded. Limit: {$limit} requests. Please wait {$remainingTime} seconds.";
        }

        parent::__construct($message, $code, $previous);
    }

    /**
     * Get the rate limit
     */
    public function getLimit(): int
    {
        return $this->limit;
    }

    /**
     * Get remaining time in seconds until rate limit resets
     */
    public function getRemainingTime(): int
    {
        return $this->remainingTime;
    }

    /**
     * Get the tier
     */
    public function getTier(): string
    {
        return $this->tier;
    }

    /**
     * Render the exception into an HTTP response
     */
    public function render($request)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'error' => 'Rate limit exceeded',
                'message' => $this->getMessage(),
                'limit' => $this->limit,
                'retry_after' => $this->remainingTime,
                'tier' => $this->tier,
            ], 429)
            ->header('Retry-After', (string) $this->remainingTime)
            ->header('X-RateLimit-Limit', (string) $this->limit)
            ->header('X-RateLimit-Remaining', '0')
            ->header('X-RateLimit-Reset', (string) (time() + $this->remainingTime));
        }

        return response()->view('errors.429', [
            'message' => $this->getMessage(),
            'retryAfter' => $this->remainingTime,
        ], 429);
    }

    /**
     * Report the exception
     */
    public function report(): void
    {
        \Log::warning('Rate limit exceeded', [
            'tier' => $this->tier,
            'limit' => $this->limit,
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'url' => request()->fullUrl(),
        ]);
    }
}