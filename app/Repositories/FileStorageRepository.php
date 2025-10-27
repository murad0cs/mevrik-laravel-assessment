<?php

namespace App\Repositories;

use App\Contracts\FileStorageInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class FileStorageRepository implements FileStorageInterface
{
    /**
     * Store an uploaded file
     */
    public function store(UploadedFile $file, string $directory = 'uploads'): string
    {
        return $file->store($directory);
    }

    /**
     * Store an uploaded file with a specific name
     */
    public function storeAs(UploadedFile $file, string $directory, string $filename): string
    {
        return $file->storeAs($directory, $filename);
    }

    /**
     * Get a file's content
     */
    public function get(string $path): ?string
    {
        if (!Storage::exists($path)) {
            return null;
        }

        return Storage::get($path);
    }

    /**
     * Check if a file exists
     */
    public function exists(string $path): bool
    {
        return Storage::exists($path);
    }

    /**
     * Delete a file
     */
    public function delete(string $path): bool
    {
        return Storage::delete($path);
    }

    /**
     * Put content to a file
     */
    public function put(string $path, string $content): bool
    {
        return Storage::put($path, $content);
    }

    /**
     * Get the full path to a file
     */
    public function path(string $path): string
    {
        return storage_path('app/' . $path);
    }

    /**
     * Get files in a directory
     */
    public function files(string $directory): array
    {
        return Storage::files($directory);
    }

    /**
     * Create a directory
     */
    public function makeDirectory(string $path): bool
    {
        return Storage::makeDirectory($path);
    }
}