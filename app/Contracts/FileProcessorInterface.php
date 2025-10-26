<?php

namespace App\Contracts;

use App\DTOs\ProcessingResultDTO;

interface FileProcessorInterface
{
    /**
     * Process a file and return the result
     *
     * @param string $inputPath
     * @return ProcessingResultDTO
     * @throws \App\Exceptions\FileProcessingException
     */
    public function process(string $inputPath): ProcessingResultDTO;

    /**
     * Check if this processor supports the given file type
     *
     * @param string $mimeType
     * @return bool
     */
    public function supports(string $mimeType): bool;

    /**
     * Get the processor type identifier
     *
     * @return string
     */
    public function getType(): string;
}