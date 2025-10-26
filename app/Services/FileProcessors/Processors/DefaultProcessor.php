<?php

namespace App\Services\FileProcessors\Processors;

use App\DTOs\ProcessingResultDTO;

class DefaultProcessor extends AbstractFileProcessor
{
    /**
     * Default processor - adds metadata
     */
    public function process(string $inputPath): ProcessingResultDTO
    {
        try {
            $content = $this->readFile($inputPath);
            $fileInfo = $this->getFileInfo($inputPath);

            // Get file metadata
            $stats = stat($inputPath);
            $mimeType = mime_content_type($inputPath) ?: 'application/octet-stream';

            // Create metadata report
            $result = $this->createHeader(
                'FILE METADATA REPORT',
                [
                    'Processing Type' => 'Metadata Analysis',
                    'Processed By' => 'Laravel Queue System',
                ]
            );

            $result .= "\nFILE INFORMATION:\n";
            $result .= "Name: {$fileInfo['name']}\n";
            $result .= "Size: " . number_format($fileInfo['size'] / 1024, 2) . " KB\n";
            $result .= "Extension: {$fileInfo['extension']}\n";
            $result .= "MIME Type: {$mimeType}\n";
            $result .= "Directory: {$fileInfo['directory']}\n";

            $result .= "\nFILE STATISTICS:\n";
            $result .= "Created: " . date('Y-m-d H:i:s', $stats['ctime']) . "\n";
            $result .= "Modified: " . date('Y-m-d H:i:s', $stats['mtime']) . "\n";
            $result .= "Accessed: " . date('Y-m-d H:i:s', $stats['atime']) . "\n";
            $result .= "Permissions: " . decoct($stats['mode'] & 0777) . "\n";

            // Content analysis
            $lines = substr_count($content, "\n");
            $words = str_word_count($content);
            $characters = strlen($content);

            $result .= "\nCONTENT ANALYSIS:\n";
            $result .= "Total Lines: {$lines}\n";
            $result .= "Total Words: {$words}\n";
            $result .= "Total Characters: {$characters}\n";

            // Hash signatures
            $result .= "\nFILE SIGNATURES:\n";
            $result .= "MD5: " . md5_file($inputPath) . "\n";
            $result .= "SHA1: " . sha1_file($inputPath) . "\n";

            $result .= "\nORIGINAL CONTENT (First 1000 characters):\n";
            $result .= str_repeat('-', 50) . "\n";
            $result .= substr($content, 0, 1000);
            if (strlen($content) > 1000) {
                $result .= "\n... (truncated, " . (strlen($content) - 1000) . " more characters)";
            }

            return ProcessingResultDTO::success(
                content: $result,
                mimeType: 'text/plain',
                extension: 'txt',
                metadata: [
                    'file_size' => $fileInfo['size'],
                    'mime_type' => $mimeType,
                    'lines' => $lines,
                    'words' => $words,
                    'characters' => $characters,
                ]
            );

        } catch (\Exception $e) {
            return ProcessingResultDTO::failure($e->getMessage());
        }
    }

    /**
     * Get the processor type identifier
     */
    public function getType(): string
    {
        return 'metadata';
    }

    /**
     * Get supported mime types - supports all types
     */
    protected function getSupportedMimeTypes(): array
    {
        return ['*/*'];
    }

    /**
     * Override supports to accept all mime types
     */
    public function supports(string $mimeType): bool
    {
        return true;
    }
}