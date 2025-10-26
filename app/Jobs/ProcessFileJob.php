<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProcessFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $fileId;
    protected $filePath;
    protected $processingType;
    protected $userId;

    /**
     * Create a new job instance.
     */
    public function __construct(string $fileId, string $filePath, string $processingType, int $userId)
    {
        $this->fileId = $fileId;
        $this->filePath = $filePath;
        $this->processingType = $processingType;
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Ensure all required directories exist with proper permissions
            $this->ensureDirectoriesExist();

            Log::info('Starting file processing', [
                'file_id' => $this->fileId,
                'type' => $this->processingType,
                'user_id' => $this->userId
            ]);

            // Update status to processing
            $this->updateStatus('processing');

            // Get the uploaded file path
            $uploadedFilePath = storage_path('app/uploads/' . $this->filePath);

            if (!file_exists($uploadedFilePath)) {
                throw new \Exception('File not found: ' . $uploadedFilePath);
            }

            // Process based on type
            $processedFilePath = $this->processFile($uploadedFilePath);

            // Store processed file info
            $this->storeProcessedFile($processedFilePath);

            // Update status to completed
            $this->updateStatus('completed');

            Log::info('File processing completed', [
                'file_id' => $this->fileId,
                'processed_file' => $processedFilePath
            ]);

        } catch (\Exception $e) {
            Log::error('File processing failed', [
                'file_id' => $this->fileId,
                'error' => $e->getMessage()
            ]);

            $this->updateStatus('failed', $e->getMessage());
            throw $e;
        }
    }

    /**
     * Process the file based on type
     */
    private function processFile(string $filePath): string
    {
        $processedDir = storage_path('app/processed');
        if (!file_exists($processedDir)) {
            mkdir($processedDir, 0755, true);
        }

        $fileExtension = pathinfo($filePath, PATHINFO_EXTENSION);
        $processedFileName = $this->fileId . '_processed.' . $fileExtension;
        $processedFilePath = $processedDir . '/' . $processedFileName;

        switch ($this->processingType) {
            case 'text_transform':
                $this->processTextFile($filePath, $processedFilePath);
                break;

            case 'csv_analyze':
                $this->processCSVFile($filePath, $processedFilePath);
                break;

            case 'image_resize':
                $this->processImageFile($filePath, $processedFilePath);
                break;

            case 'json_format':
                $this->processJSONFile($filePath, $processedFilePath);
                break;

            default:
                // Default processing - add metadata
                $this->addMetadata($filePath, $processedFilePath);
        }

        return $processedFileName;
    }

    /**
     * Process text file - convert to uppercase and add line numbers
     */
    private function processTextFile(string $input, string $output): void
    {
        $content = file_get_contents($input);
        $lines = explode("\n", $content);

        $processed = [];
        foreach ($lines as $index => $line) {
            $lineNumber = $index + 1;
            $processed[] = sprintf("%03d: %s", $lineNumber, strtoupper($line));
        }

        $processedContent = "PROCESSED BY MEVRIK QUEUE SYSTEM\n";
        $processedContent .= "Original File: " . basename($input) . "\n";
        $processedContent .= "Processed At: " . now()->toDateTimeString() . "\n";
        $processedContent .= "Processing Type: Text Transform (Uppercase + Line Numbers)\n";
        $processedContent .= str_repeat('=', 50) . "\n\n";
        $processedContent .= implode("\n", $processed);

        file_put_contents($output, $processedContent);
    }

    /**
     * Process CSV file - add summary statistics
     */
    private function processCSVFile(string $input, string $output): void
    {
        $rows = array_map('str_getcsv', file($input));
        $header = array_shift($rows);

        $summary = "CSV ANALYSIS REPORT\n";
        $summary .= "Processed At: " . now()->toDateTimeString() . "\n";
        $summary .= str_repeat('=', 50) . "\n";
        $summary .= "Total Columns: " . count($header) . "\n";
        $summary .= "Total Rows: " . count($rows) . "\n";
        $summary .= "Column Names: " . implode(', ', $header) . "\n\n";

        // Add first 10 rows as sample
        $summary .= "SAMPLE DATA (First 10 rows):\n";
        $summary .= implode(',', $header) . "\n";

        foreach (array_slice($rows, 0, 10) as $row) {
            $summary .= implode(',', $row) . "\n";
        }

        file_put_contents($output, $summary);
    }

    /**
     * Process image file - create thumbnail info
     */
    private function processImageFile(string $input, string $output): void
    {
        $imageInfo = getimagesize($input);

        $report = "IMAGE PROCESSING REPORT\n";
        $report .= "Processed At: " . now()->toDateTimeString() . "\n";
        $report .= str_repeat('=', 50) . "\n";
        $report .= "Original File: " . basename($input) . "\n";
        $report .= "File Size: " . number_format(filesize($input) / 1024, 2) . " KB\n";
        $report .= "Dimensions: " . ($imageInfo[0] ?? 'unknown') . " x " . ($imageInfo[1] ?? 'unknown') . " pixels\n";
        $report .= "MIME Type: " . ($imageInfo['mime'] ?? 'unknown') . "\n";
        $report .= "Processing: Would resize to 200x200 thumbnail\n";

        file_put_contents($output, $report);
    }

    /**
     * Process JSON file - format and validate
     */
    private function processJSONFile(string $input, string $output): void
    {
        $content = file_get_contents($input);
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $error = "JSON VALIDATION ERROR: " . json_last_error_msg();
            file_put_contents($output, $error);
            return;
        }

        $formatted = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $result = "JSON PROCESSING REPORT\n";
        $result .= "Processed At: " . now()->toDateTimeString() . "\n";
        $result .= str_repeat('=', 50) . "\n";
        $result .= "Validation: PASSED\n";
        $result .= "Total Keys: " . count($data) . "\n\n";
        $result .= "FORMATTED JSON:\n";
        $result .= $formatted;

        file_put_contents($output, $result);
    }

    /**
     * Default processing - add metadata
     */
    private function addMetadata(string $input, string $output): void
    {
        $content = file_get_contents($input);

        $metadata = "FILE PROCESSING METADATA\n";
        $metadata .= str_repeat('=', 50) . "\n";
        $metadata .= "Processed by Mevrik Queue System\n";
        $metadata .= "File ID: " . $this->fileId . "\n";
        $metadata .= "User ID: " . $this->userId . "\n";
        $metadata .= "Original File: " . basename($input) . "\n";
        $metadata .= "File Size: " . number_format(filesize($input) / 1024, 2) . " KB\n";
        $metadata .= "Processing Type: " . $this->processingType . "\n";
        $metadata .= "Processed At: " . now()->toDateTimeString() . "\n";
        $metadata .= str_repeat('=', 50) . "\n\n";
        $metadata .= "ORIGINAL CONTENT:\n";
        $metadata .= $content;

        file_put_contents($output, $metadata);
    }

    /**
     * Ensure all required directories exist with proper permissions
     */
    private function ensureDirectoriesExist(): void
    {
        $directories = [
            storage_path('app/uploads'),
            storage_path('app/processed'),
            storage_path('app/processing_status'),
            storage_path('app/notifications'),
            storage_path('app/custom'),
        ];

        foreach ($directories as $dir) {
            if (!file_exists($dir)) {
                mkdir($dir, 0775, true);
                // Try to set ownership if possible (may fail on some systems)
                @chown($dir, 'www-data');
                @chgrp($dir, 'www-data');
            }
        }
    }

    /**
     * Update processing status in database
     */
    private function updateStatus(string $status, string $error = null): void
    {
        $statusFile = storage_path('app/processing_status/' . $this->fileId . '.json');
        $statusDir = dirname($statusFile);

        if (!file_exists($statusDir)) {
            mkdir($statusDir, 0755, true);
        }

        $statusData = [
            'file_id' => $this->fileId,
            'user_id' => $this->userId,
            'status' => $status,
            'processing_type' => $this->processingType,
            'original_file' => $this->filePath,
            'updated_at' => now()->toDateTimeString(),
            'error' => $error
        ];

        if ($status === 'completed') {
            $statusData['download_ready'] = true;
        }

        file_put_contents($statusFile, json_encode($statusData, JSON_PRETTY_PRINT));
    }

    /**
     * Store processed file info
     */
    private function storeProcessedFile(string $processedFileName): void
    {
        $infoFile = storage_path('app/processing_status/' . $this->fileId . '.json');
        $data = json_decode(file_get_contents($infoFile), true);
        $data['processed_file'] = $processedFileName;
        $data['completed_at'] = now()->toDateTimeString();
        file_put_contents($infoFile, json_encode($data, JSON_PRETTY_PRINT));
    }
}