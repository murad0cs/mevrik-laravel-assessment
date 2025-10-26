<?php

namespace App\Services\FileProcessors\Processors;

use App\DTOs\ProcessingResultDTO;

class ImageProcessor extends AbstractFileProcessor
{
    /**
     * Process image file - generate metadata report
     */
    public function process(string $inputPath): ProcessingResultDTO
    {
        try {
            $fileInfo = $this->getFileInfo($inputPath);

            // Get image information
            $imageInfo = @getimagesize($inputPath);

            if ($imageInfo === false) {
                return ProcessingResultDTO::failure('Invalid image file or unable to read image information');
            }

            // Extract EXIF data if available
            $exifData = [];
            if (function_exists('exif_read_data') && in_array($imageInfo['mime'], ['image/jpeg', 'image/tiff'])) {
                $exif = @exif_read_data($inputPath);
                if ($exif) {
                    $exifData = $this->extractRelevantExifData($exif);
                }
            }

            // Create report
            $result = $this->createHeader(
                'IMAGE PROCESSING REPORT',
                [
                    'Original File' => $fileInfo['name'],
                    'File Size' => number_format($fileInfo['size'] / 1024, 2) . ' KB',
                ]
            );

            $result .= "\nIMAGE INFORMATION:\n";
            $result .= "Dimensions: {$imageInfo[0]} x {$imageInfo[1]} pixels\n";
            $result .= "MIME Type: {$imageInfo['mime']}\n";
            $result .= "Bits: " . ($imageInfo['bits'] ?? 'unknown') . "\n";
            $result .= "Channels: " . ($imageInfo['channels'] ?? 'unknown') . "\n";

            if ($imageInfo[0] > 0 && $imageInfo[1] > 0) {
                $aspectRatio = round($imageInfo[0] / $imageInfo[1], 2);
                $result .= "Aspect Ratio: {$aspectRatio}:1\n";
                $megapixels = round(($imageInfo[0] * $imageInfo[1]) / 1000000, 2);
                $result .= "Megapixels: {$megapixels} MP\n";
            }

            if (!empty($exifData)) {
                $result .= "\nEXIF DATA:\n";
                foreach ($exifData as $key => $value) {
                    $result .= "{$key}: {$value}\n";
                }
            }

            $result .= "\nPROCESSING OPTIONS:\n";
            $result .= "- Thumbnail: Would generate 200x200 thumbnail\n";
            $result .= "- Optimization: Would compress to 85% quality\n";
            $result .= "- Format: Would convert to WebP for web optimization\n";

            return ProcessingResultDTO::success(
                content: $result,
                mimeType: 'text/plain',
                extension: 'txt',
                metadata: [
                    'width' => $imageInfo[0],
                    'height' => $imageInfo[1],
                    'mime' => $imageInfo['mime'],
                    'size' => $fileInfo['size'],
                ]
            );

        } catch (\Exception $e) {
            return ProcessingResultDTO::failure($e->getMessage());
        }
    }

    /**
     * Extract relevant EXIF data
     */
    private function extractRelevantExifData(array $exif): array
    {
        $relevant = [];

        $fields = [
            'Make' => 'Camera Make',
            'Model' => 'Camera Model',
            'DateTime' => 'Date Taken',
            'ExposureTime' => 'Exposure Time',
            'FNumber' => 'F-Stop',
            'ISOSpeedRatings' => 'ISO',
            'FocalLength' => 'Focal Length',
            'Flash' => 'Flash',
            'Orientation' => 'Orientation',
        ];

        foreach ($fields as $exifKey => $displayName) {
            if (isset($exif[$exifKey])) {
                $value = $exif[$exifKey];
                if (is_array($value)) {
                    $value = implode(', ', $value);
                }
                $relevant[$displayName] = $value;
            }
        }

        return $relevant;
    }

    /**
     * Get the processor type identifier
     */
    public function getType(): string
    {
        return 'image_resize';
    }

    /**
     * Get supported mime types
     */
    protected function getSupportedMimeTypes(): array
    {
        return [
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/bmp',
            'image/tiff',
        ];
    }
}