<?php

namespace App\Services\FileProcessors\Processors;

use App\DTOs\ProcessingResultDTO;

class TextProcessor extends AbstractFileProcessor
{
    /**
     * Process text file - convert to uppercase and add line numbers
     */
    public function process(string $inputPath): ProcessingResultDTO
    {
        try {
            $content = $this->readFile($inputPath);
            $fileInfo = $this->getFileInfo($inputPath);

            // Split content into lines
            $lines = explode("\n", $content);

            // Process each line: add line numbers and convert to uppercase
            $processedLines = [];
            foreach ($lines as $index => $line) {
                $lineNumber = sprintf("%03d", $index + 1);
                $processedLines[] = "{$lineNumber}: " . strtoupper($line);
            }

            // Create result with header
            $result = $this->createHeader(
                'TEXT TRANSFORMATION RESULT',
                [
                    'Original File' => $fileInfo['name'],
                    'Total Lines' => count($lines),
                    'Processing Type' => 'Text Transform (Uppercase + Line Numbers)',
                ]
            );

            $result .= "\nPROCESSED CONTENT:\n";
            $result .= implode("\n", $processedLines);

            return ProcessingResultDTO::success(
                content: $result,
                mimeType: 'text/plain',
                extension: 'txt',
                metadata: [
                    'line_count' => count($lines),
                    'original_size' => $fileInfo['size'],
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
        return 'text_transform';
    }

    /**
     * Get supported mime types
     */
    protected function getSupportedMimeTypes(): array
    {
        return [
            'text/plain',
            'text/html',
            'text/csv',
            'application/txt',
        ];
    }
}