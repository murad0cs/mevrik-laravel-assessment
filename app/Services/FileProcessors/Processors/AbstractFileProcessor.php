<?php

namespace App\Services\FileProcessors\Processors;

use App\Contracts\FileProcessorInterface;
use App\DTOs\ProcessingResultDTO;

abstract class AbstractFileProcessor implements FileProcessorInterface
{
    /**
     * Process a file and return the result
     */
    abstract public function process(string $inputPath): ProcessingResultDTO;

    /**
     * Check if this processor supports the given file type
     */
    public function supports(string $mimeType): bool
    {
        return in_array($mimeType, $this->getSupportedMimeTypes());
    }

    /**
     * Get supported mime types
     */
    abstract protected function getSupportedMimeTypes(): array;

    /**
     * Read file content safely
     */
    protected function readFile(string $path): string
    {
        if (!file_exists($path)) {
            throw new \RuntimeException("File not found: {$path}");
        }

        $content = file_get_contents($path);

        if ($content === false) {
            throw new \RuntimeException("Failed to read file: {$path}");
        }

        return $content;
    }

    /**
     * Get file info
     */
    protected function getFileInfo(string $path): array
    {
        return [
            'size' => filesize($path),
            'name' => basename($path),
            'extension' => pathinfo($path, PATHINFO_EXTENSION),
            'directory' => dirname($path),
        ];
    }

    /**
     * Create a processing header
     */
    protected function createHeader(string $title, array $info = []): string
    {
        $header = "{$title}\n";
        $header .= "Processed At: " . now()->toDateTimeString() . "\n";
        $header .= str_repeat('=', 50) . "\n";

        foreach ($info as $key => $value) {
            $header .= ucfirst($key) . ": {$value}\n";
        }

        return $header;
    }
}