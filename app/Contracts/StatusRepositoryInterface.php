<?php

namespace App\Contracts;

use App\DTOs\FileStatusDTO;

interface StatusRepositoryInterface
{
    /**
     * Find a file status by ID
     *
     * @param string $fileId
     * @return FileStatusDTO|null
     */
    public function find(string $fileId): ?FileStatusDTO;

    /**
     * Save a file status
     *
     * @param FileStatusDTO $status
     * @return void
     */
    public function save(FileStatusDTO $status): void;

    /**
     * Update file status
     *
     * @param string $fileId
     * @param string $status
     * @param array $additionalData
     * @return void
     */
    public function updateStatus(string $fileId, string $status, array $additionalData = []): void;

    /**
     * Get all files with a specific status
     *
     * @param string $status
     * @return array
     */
    public function findByStatus(string $status): array;

    /**
     * Get statistics grouped by status
     *
     * @return array
     */
    public function getStatistics(): array;
}