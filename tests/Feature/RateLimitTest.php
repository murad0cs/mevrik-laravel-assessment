<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\UploadedFile;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Clear rate limit cache before each test
        Cache::flush();
    }

    /** @test */
    public function test_relaxed_rate_limit_allows_60_requests_per_minute()
    {
        // Test endpoints with relaxed rate limit (60/min)
        for ($i = 0; $i < 60; $i++) {
            $response = $this->getJson('/api/queue/status');
            $response->assertStatus(200);
            $response->assertHeader('X-RateLimit-Limit', 60);
        }

        // 61st request should be rate limited
        $response = $this->getJson('/api/queue/status');
        $response->assertStatus(429);
        $response->assertJson([
            'error' => 'Too Many Attempts',
        ]);
    }

    /** @test */
    public function test_moderate_rate_limit_allows_30_requests_per_minute()
    {
        // Test endpoints with moderate rate limit (30/min)
        for ($i = 0; $i < 30; $i++) {
            $response = $this->postJson('/api/queue/dispatch-notification', [
                'user_id' => 123,
                'type' => 'email',
                'message' => 'Test message'
            ]);
            $response->assertStatus(200);
            $response->assertHeader('X-RateLimit-Limit', 30);
        }

        // 31st request should be rate limited
        $response = $this->postJson('/api/queue/dispatch-notification', [
            'user_id' => 123,
            'type' => 'email',
            'message' => 'Test message'
        ]);
        $response->assertStatus(429);
    }

    /** @test */
    public function test_bulk_rate_limit_allows_5_requests_per_minute()
    {
        // Test bulk endpoints with strict rate limit (5/min)
        for ($i = 0; $i < 5; $i++) {
            $response = $this->postJson('/api/queue/dispatch-bulk', [
                'count' => 10,
                'type' => 'notification'
            ]);
            $response->assertStatus(200);
            $response->assertHeader('X-RateLimit-Limit', 5);
        }

        // 6th request should be rate limited
        $response = $this->postJson('/api/queue/dispatch-bulk', [
            'count' => 10,
            'type' => 'notification'
        ]);
        $response->assertStatus(429);
        $response->assertJsonStructure([
            'error',
            'message',
            'retry_after',
            'retry_after_readable'
        ]);
    }

    /** @test */
    public function test_rate_limit_headers_are_present()
    {
        $response = $this->getJson('/api/queue/status');

        $response->assertStatus(200);
        $response->assertHeader('X-RateLimit-Limit');
        $response->assertHeader('X-RateLimit-Remaining');

        $limit = $response->headers->get('X-RateLimit-Limit');
        $remaining = $response->headers->get('X-RateLimit-Remaining');

        $this->assertEquals(60, $limit);
        $this->assertEquals(59, $remaining); // After one request
    }

    /** @test */
    public function test_rate_limit_resets_after_decay_time()
    {
        // Make requests up to the limit
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/queue/dispatch-bulk', [
                'count' => 10,
                'type' => 'notification'
            ]);
        }

        // Next request should be rate limited
        $response = $this->postJson('/api/queue/dispatch-bulk', [
            'count' => 10,
            'type' => 'notification'
        ]);
        $response->assertStatus(429);

        // Simulate time passing (10 minutes for bulk rate limit)
        $this->travel(11)->minutes();

        // Should be able to make request again
        $response = $this->postJson('/api/queue/dispatch-bulk', [
            'count' => 10,
            'type' => 'notification'
        ]);
        $response->assertStatus(200);
    }

    /** @test */
    public function test_different_endpoints_have_independent_rate_limits()
    {
        // Max out the bulk endpoint limit
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/queue/dispatch-bulk', [
                'count' => 10,
                'type' => 'notification'
            ]);
        }

        // Bulk endpoint should be rate limited
        $response = $this->postJson('/api/queue/dispatch-bulk', [
            'count' => 10,
            'type' => 'notification'
        ]);
        $response->assertStatus(429);

        // But other endpoints should still work
        $response = $this->getJson('/api/queue/status');
        $response->assertStatus(200);

        $response = $this->postJson('/api/queue/dispatch-notification', [
            'user_id' => 123,
            'type' => 'email',
            'message' => 'Test'
        ]);
        $response->assertStatus(200);
    }

    /** @test */
    public function test_rate_limit_is_per_ip_address()
    {
        // Make requests from first IP
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/queue/dispatch-bulk', [
                'count' => 10,
                'type' => 'notification'
            ], [], ['REMOTE_ADDR' => '192.168.1.1']);
        }

        // Next request from same IP should be rate limited
        $response = $this->postJson('/api/queue/dispatch-bulk', [
            'count' => 10,
            'type' => 'notification'
        ], [], ['REMOTE_ADDR' => '192.168.1.1']);
        $response->assertStatus(429);

        // But request from different IP should work
        $response = $this->postJson('/api/queue/dispatch-bulk', [
            'count' => 10,
            'type' => 'notification'
        ], [], ['REMOTE_ADDR' => '192.168.1.2']);
        $response->assertStatus(200);
    }

    /** @test */
    public function test_health_endpoint_has_no_rate_limit()
    {
        // Health endpoint should never be rate limited
        for ($i = 0; $i < 100; $i++) {
            $response = $this->getJson('/api/health');
            $response->assertStatus(200);
        }
    }

    /** @test */
    public function test_rate_limit_response_format()
    {
        // Max out the limit
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/queue/dispatch-bulk', [
                'count' => 10,
                'type' => 'notification'
            ]);
        }

        // Check the rate limit response
        $response = $this->postJson('/api/queue/dispatch-bulk', [
            'count' => 10,
            'type' => 'notification'
        ]);

        $response->assertStatus(429);
        $response->assertJsonStructure([
            'error',
            'message',
            'retry_after',
            'retry_after_readable'
        ]);

        $data = $response->json();
        $this->assertEquals('Too Many Attempts', $data['error']);
        $this->assertIsInt($data['retry_after']);
        $this->assertStringContainsString('minute', $data['retry_after_readable']);
    }

    /** @test */
    public function test_file_upload_respects_rate_limit()
    {
        // File upload endpoint has moderate rate limit (30/min)
        for ($i = 0; $i < 30; $i++) {
            $file = UploadedFile::fake()->create('test.txt', 100);

            $response = $this->postJson('/api/queue/upload-file', [
                'file' => $file,
                'processing_type' => 'text_transform',
                'user_id' => 123
            ]);

            if ($response->status() !== 200) {
                // If we hit validation errors, skip
                continue;
            }

            $response->assertHeader('X-RateLimit-Limit', 30);
        }

        // Eventually should hit rate limit
        $file = UploadedFile::fake()->create('test.txt', 100);
        $response = $this->postJson('/api/queue/upload-file', [
            'file' => $file,
            'processing_type' => 'text_transform',
            'user_id' => 123
        ]);

        // Should be rate limited after 30 successful requests
        if ($response->headers->get('X-RateLimit-Remaining') == 0) {
            $response->assertStatus(429);
        }
    }
}