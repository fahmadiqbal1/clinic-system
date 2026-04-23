<?php

namespace Tests\Feature\Ai;

use App\Services\AiSidecarClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CircuitBreakerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('clinic.case_token_secret', 'test-secret-32-chars-long-minimum');
        Config::set('clinic.sidecar_jwt_secret', 'test-jwt-secret-32-chars-long-min');
        Config::set('clinic.sidecar_url', 'http://sidecar-test:8001');
        Cache::flush();
    }

    public function test_circuit_opens_after_three_consecutive_failures(): void
    {
        Http::fake(fn () => Http::response('service unavailable', 503));

        $client = app(AiSidecarClient::class);
        $this->assertFalse($client->isCircuitOpen());

        for ($i = 0; $i < 3; $i++) {
            try {
                $client->ragQuery('test query');
            } catch (\Exception) {
                // expected
            }
        }

        $this->assertTrue($client->isCircuitOpen());
    }

    public function test_open_circuit_rejects_immediately_without_http_call(): void
    {
        Cache::put('ai_sidecar:cb_open', true, 300);

        Http::fake(); // no fake routes registered — would fail if HTTP is called

        $client = app(AiSidecarClient::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/circuit open/i');

        $client->ragQuery('test');

        Http::assertNothingSent();
    }

    public function test_successful_call_resets_failure_counter(): void
    {
        Cache::put('ai_sidecar:cb_failures', 2, 60);

        Http::fake(['*/v1/rag/query' => Http::response(['answer' => 'ok', 'model_id' => 'test'], 200)]);

        $client = app(AiSidecarClient::class);
        $client->ragQuery('test query');

        $this->assertFalse($client->isCircuitOpen());
        $this->assertNull(Cache::get('ai_sidecar:cb_failures'));
    }

    public function test_circuit_does_not_open_before_threshold(): void
    {
        Http::fake(fn () => Http::response('error', 503));

        $client = app(AiSidecarClient::class);

        for ($i = 0; $i < 2; $i++) {
            try {
                $client->ragQuery('test');
            } catch (\Exception) {}
        }

        $this->assertFalse($client->isCircuitOpen());
    }
}
