<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class WriteLogJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The log data.
     *
     * @var array
     */
    protected $logData;

    /**
     * The log level.
     *
     * @var string
     */
    protected $level;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(array $logData, string $level = 'info')
    {
        $this->logData = $logData;
        $this->level = $level;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Write to Laravel log
            $this->writeToLaravelLog();

            // Write to custom log file
            $this->writeToCustomLogFile();

            Log::info('Log writing job completed successfully', [
                'job_id' => $this->job->getJobId(),
                'level' => $this->level,
            ]);

        } catch (\Exception $e) {
            Log::error('Log writing job failed', [
                'job_id' => $this->job->getJobId(),
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            throw $e;
        }
    }

    /**
     * Write to Laravel standard log.
     *
     * @return void
     */
    protected function writeToLaravelLog(): void
    {
        $message = $this->logData['message'] ?? 'Background job log entry';
        $context = array_merge($this->logData, [
            'timestamp' => now()->toDateTimeString(),
            'job_id' => $this->job->getJobId(),
        ]);

        // Write to log based on level
        match ($this->level) {
            'emergency' => Log::emergency($message, $context),
            'alert' => Log::alert($message, $context),
            'critical' => Log::critical($message, $context),
            'error' => Log::error($message, $context),
            'warning' => Log::warning($message, $context),
            'notice' => Log::notice($message, $context),
            'debug' => Log::debug($message, $context),
            default => Log::info($message, $context),
        };
    }

    /**
     * Write to custom log file.
     *
     * @return void
     */
    protected function writeToCustomLogFile(): void
    {
        $logEntry = [
            'timestamp' => now()->toDateTimeString(),
            'level' => strtoupper($this->level),
            'job_id' => $this->job->getJobId(),
            'data' => $this->logData,
        ];

        $logLine = json_encode($logEntry, JSON_PRETTY_PRINT) . "\n";

        // Ensure storage/logs directory exists
        $logPath = storage_path('logs/custom-jobs.log');

        // Append to custom log file
        file_put_contents($logPath, $logLine, FILE_APPEND | LOCK_EX);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::critical('Log writing job failed permanently', [
            'data' => $this->logData,
            'level' => $this->level,
            'error' => $exception->getMessage(),
        ]);
    }
}
