<?php

namespace App\Services\FileProcessors\Processors;

use App\DTOs\ProcessingResultDTO;

class JsonProcessor extends AbstractFileProcessor
{
    /**
     * Process JSON file - validate and format
     */
    public function process(string $inputPath): ProcessingResultDTO
    {
        try {
            $content = $this->readFile($inputPath);
            $fileInfo = $this->getFileInfo($inputPath);

            // Decode JSON
            $data = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return ProcessingResultDTO::failure(
                    'JSON Validation Error: ' . json_last_error_msg()
                );
            }

            // Analyze JSON structure
            $analysis = $this->analyzeJsonStructure($data);

            // Pretty print JSON
            $formatted = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

            // Create result
            $result = $this->createHeader(
                'JSON PROCESSING REPORT',
                [
                    'Original File' => $fileInfo['name'],
                    'File Size' => number_format($fileInfo['size'] / 1024, 2) . ' KB',
                    'Validation' => 'PASSED',
                ]
            );

            $result .= "\nSTRUCTURE ANALYSIS:\n";
            $result .= "Total Keys: {$analysis['total_keys']}\n";
            $result .= "Nesting Depth: {$analysis['max_depth']}\n";
            $result .= "Data Types: " . implode(', ', array_keys($analysis['types'])) . "\n";

            if (!empty($analysis['keys'])) {
                $result .= "Root Keys: " . implode(', ', array_slice($analysis['keys'], 0, 10)) . "\n";
            }

            $result .= "\nFORMATTED JSON:\n";
            $result .= $formatted;

            return ProcessingResultDTO::success(
                content: $result,
                mimeType: 'application/json',
                extension: 'json',
                metadata: $analysis
            );

        } catch (\Exception $e) {
            return ProcessingResultDTO::failure($e->getMessage());
        }
    }

    /**
     * Analyze JSON structure
     */
    private function analyzeJsonStructure($data, int $depth = 1): array
    {
        $analysis = [
            'total_keys' => 0,
            'max_depth' => $depth,
            'types' => [],
            'keys' => [],
        ];

        if (is_array($data)) {
            $analysis['total_keys'] = count($data);
            $analysis['keys'] = is_array($data) && array_keys($data) !== range(0, count($data) - 1)
                ? array_keys($data)
                : [];

            foreach ($data as $key => $value) {
                $type = gettype($value);

                if (!isset($analysis['types'][$type])) {
                    $analysis['types'][$type] = 0;
                }
                $analysis['types'][$type]++;

                if (is_array($value) || is_object($value)) {
                    $subAnalysis = $this->analyzeJsonStructure($value, $depth + 1);
                    $analysis['max_depth'] = max($analysis['max_depth'], $subAnalysis['max_depth']);
                }
            }
        }

        return $analysis;
    }

    /**
     * Get the processor type identifier
     */
    public function getType(): string
    {
        return 'json_format';
    }

    /**
     * Get supported mime types
     */
    protected function getSupportedMimeTypes(): array
    {
        return [
            'application/json',
            'text/json',
        ];
    }
}