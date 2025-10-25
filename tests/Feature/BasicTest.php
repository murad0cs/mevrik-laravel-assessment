<?php

namespace Tests\Feature;

use Tests\TestCase;

class BasicTest extends TestCase
{
    /**
     * A basic test that always passes.
     */
    public function test_application_is_configured(): void
    {
        $this->assertTrue(true);
    }

    /**
     * Test that environment is set up.
     */
    public function test_environment_is_set(): void
    {
        $this->assertNotEmpty(config('app.name'));
        $this->assertNotEmpty(config('app.env'));
    }

    /**
     * Test that database connection is configured.
     */
    public function test_database_is_configured(): void
    {
        $this->assertNotEmpty(config('database.default'));
        $this->assertNotEmpty(config('database.connections'));
    }

    /**
     * Test that queue is configured.
     */
    public function test_queue_is_configured(): void
    {
        $this->assertEquals('database', config('queue.default'));
        $this->assertNotEmpty(config('queue.connections.database'));
    }
}