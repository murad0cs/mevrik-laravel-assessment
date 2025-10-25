<?php

namespace Tests\Unit;

use App\Jobs\WriteLogJob;
use Tests\TestCase;

class WriteLogJobTest extends TestCase
{
    /**
     * Test job can be instantiated.
     */
    public function test_job_can_be_instantiated(): void
    {
        $data = [
            'message' => 'Test log message',
            'context' => ['key' => 'value'],
        ];

        $job = new WriteLogJob($data, 'info');

        $this->assertInstanceOf(WriteLogJob::class, $job);
    }

    /**
     * Test job has correct properties.
     */
    public function test_job_has_correct_properties(): void
    {
        $data = [
            'message' => 'Test log message',
        ];

        $job = new WriteLogJob($data, 'error');

        $this->assertEquals(3, $job->tries);
    }

    /**
     * Test job can be serialized.
     */
    public function test_job_can_be_serialized(): void
    {
        $data = [
            'message' => 'Test log message',
        ];

        $job = new WriteLogJob($data);
        $serialized = serialize($job);

        $this->assertIsString($serialized);
        $this->assertNotEmpty($serialized);
    }

    /**
     * Test job defaults to info level.
     */
    public function test_job_defaults_to_info_level(): void
    {
        $data = [
            'message' => 'Test log message',
        ];

        $job = new WriteLogJob($data);

        $reflection = new \ReflectionClass($job);
        $property = $reflection->getProperty('level');
        $property->setAccessible(true);

        $this->assertEquals('info', $property->getValue($job));
    }
}
