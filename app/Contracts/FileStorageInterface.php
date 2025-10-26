<?php

namespace App\Contracts;

use Illuminate\Http\UploadedFile;

interface FileStorageInterface
{
    /**
     * Store an uploaded file
     *
     * @param UploadedFile $file
     * @param string $directory
     * @return string The stored file path
     */
    public function store(UploadedFile $file, string $directory = 'uploads'): string;

    /**
     * Get a file's content
     *
     * @param string $path
     * @return string|null
     */
    public function get(string $path): ?string;

    /**
     * Check if a file exists
     *
     * @param string $path
     * @return bool
     */
    public function exists(string $path): bool;

    /**
     * Delete a file
     *
     * @param string $path
     * @return bool
     */
    public function delete(string $path): bool;

    /**
     * Put content to a file
     *
     * @param string $path
     * @param string $content
     * @return bool
     */
    public function put(string $path, string $content): bool;
}