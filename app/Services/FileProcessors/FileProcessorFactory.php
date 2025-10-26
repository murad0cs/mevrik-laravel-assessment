<?php

namespace App\Services\FileProcessors;

use App\Contracts\FileProcessorInterface;
use App\Services\FileProcessors\Processors\TextProcessor;
use App\Services\FileProcessors\Processors\CsvProcessor;
use App\Services\FileProcessors\Processors\JsonProcessor;
use App\Services\FileProcessors\Processors\ImageProcessor;
use App\Services\FileProcessors\Processors\DefaultProcessor;

class FileProcessorFactory
{
    /**
     * Mapping of processing types to processor classes
     */
    private array $processors = [
        'text_transform' => TextProcessor::class,
        'csv_analyze' => CsvProcessor::class,
        'json_format' => JsonProcessor::class,
        'image_resize' => ImageProcessor::class,
        'metadata' => DefaultProcessor::class,
    ];

    /**
     * Create a processor instance based on type
     */
    public function make(string $type): FileProcessorInterface
    {
        $processorClass = $this->processors[$type] ?? DefaultProcessor::class;

        if (!class_exists($processorClass)) {
            throw new \InvalidArgumentException("Processor class not found: {$processorClass}");
        }

        $processor = app($processorClass);

        if (!$processor instanceof FileProcessorInterface) {
            throw new \InvalidArgumentException("Invalid processor class: {$processorClass}");
        }

        return $processor;
    }

    /**
     * Register a new processor type
     */
    public function register(string $type, string $processorClass): void
    {
        if (!class_exists($processorClass)) {
            throw new \InvalidArgumentException("Processor class not found: {$processorClass}");
        }

        $this->processors[$type] = $processorClass;
    }

    /**
     * Get all available processor types
     */
    public function getAvailableTypes(): array
    {
        return array_keys($this->processors);
    }

    /**
     * Check if a processor type exists
     */
    public function hasProcessor(string $type): bool
    {
        return isset($this->processors[$type]);
    }
}