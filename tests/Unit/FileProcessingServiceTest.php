<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\FileProcessingService;
use App\DTOs\FileUploadDTO;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class FileProcessingServiceTest extends TestCase
{
    private FileProcessingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->service = new FileProcessingService();
    }

    /** @test */
    public function test_process_text_file_transformation()
    {
        $content = "hello world\ntest line 2\nfinal line";
        $file = UploadedFile::fake()->createWithContent('test.txt', $content);

        $dto = new FileUploadDTO(
            file: $file,
            processingType: 'text_transform',
            userId: 123
        );

        $result = $this->service->processFile($dto);

        $this->assertNotNull($result);
        $this->assertEquals('text_transform', $result->processingType);
        $this->assertEquals('queued', $result->status);
        $this->assertEquals(123, $result->userId);
    }

    /** @test */
    public function test_process_csv_file_analysis()
    {
        $csvContent = "Name,Age,City\nJohn,30,NYC\nJane,25,LA\nBob,35,Chicago";
        $file = UploadedFile::fake()->createWithContent('test.csv', $csvContent);

        $dto = new FileUploadDTO(
            file: $file,
            processingType: 'csv_analyze',
            userId: 456
        );

        $result = $this->service->processFile($dto);

        $this->assertNotNull($result);
        $this->assertEquals('csv_analyze', $result->processingType);
    }

    /** @test */
    public function test_process_json_file_formatting()
    {
        $jsonContent = '{"name":"John","age":30,"city":"NYC"}';
        $file = UploadedFile::fake()->createWithContent('test.json', $jsonContent);

        $dto = new FileUploadDTO(
            file: $file,
            processingType: 'json_format',
            userId: 789
        );

        $result = $this->service->processFile($dto);

        $this->assertNotNull($result);
        $this->assertEquals('json_format', $result->processingType);
    }

    /** @test */
    public function test_unique_file_id_generation()
    {
        $file = UploadedFile::fake()->create('test.txt', 100);

        $dto1 = new FileUploadDTO(
            file: $file,
            processingType: 'text_transform',
            userId: 123
        );

        $dto2 = new FileUploadDTO(
            file: $file,
            processingType: 'text_transform',
            userId: 123
        );

        $result1 = $this->service->processFile($dto1);
        $result2 = $this->service->processFile($dto2);

        $this->assertNotEquals($result1->id, $result2->id);
    }

    /** @test */
    public function test_file_status_tracking()
    {
        $file = UploadedFile::fake()->create('test.txt', 100);

        $dto = new FileUploadDTO(
            file: $file,
            processingType: 'text_transform',
            userId: 123
        );

        $result = $this->service->processFile($dto);
        $fileId = $result->id;

        // Check initial status
        $status = $this->service->getFileStatus($fileId);
        $this->assertEquals('queued', $status->status);

        // Update status to processing
        $this->service->updateFileStatus($fileId, 'processing');
        $status = $this->service->getFileStatus($fileId);
        $this->assertEquals('processing', $status->status);

        // Update status to completed
        $this->service->updateFileStatus($fileId, 'completed');
        $status = $this->service->getFileStatus($fileId);
        $this->assertEquals('completed', $status->status);
    }

    /** @test */
    public function test_download_url_generation()
    {
        $file = UploadedFile::fake()->create('test.txt', 100);

        $dto = new FileUploadDTO(
            file: $file,
            processingType: 'text_transform',
            userId: 123
        );

        $result = $this->service->processFile($dto);
        $fileId = $result->id;

        // Simulate file processing completion
        Storage::put("processed/{$fileId}_processed.txt", "Processed content");
        $this->service->updateFileStatus($fileId, 'completed', [
            'processed_file' => "{$fileId}_processed.txt"
        ]);

        $downloadUrl = $this->service->getDownloadUrl($fileId);
        $this->assertNotNull($downloadUrl);
        $this->assertStringContainsString($fileId, $downloadUrl);
    }

    /** @test */
    public function test_processing_type_validation()
    {
        $validTypes = ['text_transform', 'csv_analyze', 'json_format', 'image_resize', 'metadata'];

        foreach ($validTypes as $type) {
            $file = UploadedFile::fake()->create('test.file', 100);

            $dto = new FileUploadDTO(
                file: $file,
                processingType: $type,
                userId: 123
            );

            $result = $this->service->processFile($dto);
            $this->assertEquals($type, $result->processingType);
        }
    }

    /** @test */
    public function test_file_size_limits()
    {
        // Test file within limit (10MB)
        $normalFile = UploadedFile::fake()->create('normal.txt', 5000); // 5MB

        $dto = new FileUploadDTO(
            file: $normalFile,
            processingType: 'text_transform',
            userId: 123
        );

        $result = $this->service->processFile($dto);
        $this->assertNotNull($result);

        // Test file exceeding limit
        $largeFile = UploadedFile::fake()->create('large.txt', 15000); // 15MB

        $dto = new FileUploadDTO(
            file: $largeFile,
            processingType: 'text_transform',
            userId: 123
        );

        try {
            $this->service->processFile($dto);
            $this->fail('Should have thrown exception for large file');
        } catch (\Exception $e) {
            $this->assertStringContainsString('size', strtolower($e->getMessage()));
        }
    }

    /** @test */
    public function test_statistics_calculation()
    {
        // Create multiple files with different statuses
        $statuses = ['queued', 'processing', 'completed', 'failed'];
        $types = ['text_transform', 'csv_analyze', 'json_format'];

        foreach ($types as $type) {
            foreach ($statuses as $status) {
                $file = UploadedFile::fake()->create('test.file', 100);

                $dto = new FileUploadDTO(
                    file: $file,
                    processingType: $type,
                    userId: 123
                );

                $result = $this->service->processFile($dto);
                $this->service->updateFileStatus($result->id, $status);
            }
        }

        $stats = $this->service->getStatistics();

        $this->assertArrayHasKey('total_files', $stats);
        $this->assertArrayHasKey('by_type', $stats);
        $this->assertArrayHasKey('by_status', $stats);
        $this->assertEquals(12, $stats['total_files']); // 3 types * 4 statuses
    }
}