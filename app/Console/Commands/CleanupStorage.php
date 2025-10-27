<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class CleanupStorage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'storage:cleanup
                            {--days=30 : Number of days to keep files}
                            {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up old processed files and status files';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');
        $cutoffDate = Carbon::now()->subDays($days);

        $this->info("Cleaning up files older than {$days} days (before {$cutoffDate->toDateTimeString()})");

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No files will be deleted');
        }

        // Clean up processed files
        $this->cleanupProcessedFiles($cutoffDate, $dryRun);

        // Clean up status files
        $this->cleanupStatusFiles($cutoffDate, $dryRun);

        // Clean up upload files
        $this->cleanupUploadFiles($cutoffDate, $dryRun);

        $this->info('Cleanup complete!');
        return Command::SUCCESS;
    }

    /**
     * Clean up old processed files
     */
    private function cleanupProcessedFiles(Carbon $cutoffDate, bool $dryRun): void
    {
        $this->info('Cleaning processed files...');
        $deletedCount = 0;
        $totalSize = 0;

        $files = Storage::files('processed');
        foreach ($files as $file) {
            $lastModified = Carbon::createFromTimestamp(Storage::lastModified($file));

            if ($lastModified->lt($cutoffDate)) {
                $size = Storage::size($file);
                $totalSize += $size;

                if ($dryRun) {
                    $this->line("Would delete: {$file} (Modified: {$lastModified->toDateTimeString()}, Size: " . $this->formatBytes($size) . ")");
                } else {
                    Storage::delete($file);
                    $this->line("Deleted: {$file}");
                }
                $deletedCount++;
            }
        }

        $this->info("Processed files: {$deletedCount} files (" . $this->formatBytes($totalSize) . ")");
    }

    /**
     * Clean up old status files
     */
    private function cleanupStatusFiles(Carbon $cutoffDate, bool $dryRun): void
    {
        $this->info('Cleaning status files...');
        $deletedCount = 0;

        $files = Storage::files('processing_status');
        foreach ($files as $file) {
            // Read the status file to check if it's completed or failed
            $content = Storage::get($file);
            $data = json_decode($content, true);

            // Only clean up completed or failed jobs
            if (!in_array($data['status'] ?? '', ['completed', 'failed'])) {
                continue;
            }

            $lastModified = Carbon::createFromTimestamp(Storage::lastModified($file));

            if ($lastModified->lt($cutoffDate)) {
                if ($dryRun) {
                    $this->line("Would delete: {$file} (Status: {$data['status']}, Modified: {$lastModified->toDateTimeString()})");
                } else {
                    Storage::delete($file);
                    $this->line("Deleted: {$file}");
                }
                $deletedCount++;
            }
        }

        $this->info("Status files: {$deletedCount} files");
    }

    /**
     * Clean up old upload files
     */
    private function cleanupUploadFiles(Carbon $cutoffDate, bool $dryRun): void
    {
        $this->info('Cleaning upload files...');
        $deletedCount = 0;
        $totalSize = 0;

        $files = Storage::files('uploads');
        foreach ($files as $file) {
            $lastModified = Carbon::createFromTimestamp(Storage::lastModified($file));

            if ($lastModified->lt($cutoffDate)) {
                $size = Storage::size($file);
                $totalSize += $size;

                if ($dryRun) {
                    $this->line("Would delete: {$file} (Modified: {$lastModified->toDateTimeString()}, Size: " . $this->formatBytes($size) . ")");
                } else {
                    Storage::delete($file);
                    $this->line("Deleted: {$file}");
                }
                $deletedCount++;
            }
        }

        $this->info("Upload files: {$deletedCount} files (" . $this->formatBytes($totalSize) . ")");
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}