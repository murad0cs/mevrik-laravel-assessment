<?php

namespace App\Services\FileProcessors\Processors;

use App\DTOs\ProcessingResultDTO;

class CsvProcessor extends AbstractFileProcessor
{
    /**
     * Process CSV file - generate analysis and statistics
     */
    public function process(string $inputPath): ProcessingResultDTO
    {
        try {
            $fileInfo = $this->getFileInfo($inputPath);

            // Parse CSV file
            $rows = array_map('str_getcsv', file($inputPath));
            $header = array_shift($rows);

            // Calculate statistics
            $stats = $this->calculateStatistics($rows, $header);

            // Create analysis report
            $result = $this->createHeader(
                'CSV ANALYSIS REPORT',
                [
                    'Original File' => $fileInfo['name'],
                    'File Size' => number_format($fileInfo['size'] / 1024, 2) . ' KB',
                ]
            );

            $result .= "\nSTATISTICS:\n";
            $result .= "Total Columns: " . count($header) . "\n";
            $result .= "Total Rows: " . count($rows) . "\n";
            $result .= "Column Names: " . implode(', ', $header) . "\n";

            // Add column statistics if numeric
            if (!empty($stats)) {
                $result .= "\nCOLUMN ANALYSIS:\n";
                foreach ($stats as $column => $info) {
                    $result .= "- {$column}: ";
                    if ($info['numeric']) {
                        $result .= sprintf(
                            "Min: %.2f, Max: %.2f, Avg: %.2f\n",
                            $info['min'],
                            $info['max'],
                            $info['avg']
                        );
                    } else {
                        $result .= "Unique values: {$info['unique']}\n";
                    }
                }
            }

            // Add sample data
            $result .= "\nSAMPLE DATA (First 10 rows):\n";
            $result .= implode(',', $header) . "\n";
            foreach (array_slice($rows, 0, 10) as $row) {
                $result .= implode(',', $row) . "\n";
            }

            return ProcessingResultDTO::success(
                content: $result,
                mimeType: 'text/plain',
                extension: 'txt',
                metadata: [
                    'row_count' => count($rows),
                    'column_count' => count($header),
                    'columns' => $header,
                ]
            );

        } catch (\Exception $e) {
            return ProcessingResultDTO::failure($e->getMessage());
        }
    }

    /**
     * Calculate statistics for CSV columns
     */
    private function calculateStatistics(array $rows, array $header): array
    {
        $stats = [];

        foreach ($header as $index => $column) {
            $values = array_column($rows, $index);
            $numericValues = array_filter($values, 'is_numeric');

            if (count($numericValues) > 0) {
                // Numeric column
                $stats[$column] = [
                    'numeric' => true,
                    'min' => min($numericValues),
                    'max' => max($numericValues),
                    'avg' => array_sum($numericValues) / count($numericValues),
                ];
            } else {
                // Text column
                $stats[$column] = [
                    'numeric' => false,
                    'unique' => count(array_unique($values)),
                ];
            }
        }

        return $stats;
    }

    /**
     * Get the processor type identifier
     */
    public function getType(): string
    {
        return 'csv_analyze';
    }

    /**
     * Get supported mime types
     */
    protected function getSupportedMimeTypes(): array
    {
        return [
            'text/csv',
            'application/csv',
            'application/vnd.ms-excel',
        ];
    }
}