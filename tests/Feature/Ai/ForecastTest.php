<?php

namespace Tests\Feature\Ai;

use App\Services\AiSidecarClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ForecastTest extends TestCase
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

    public function test_forecast_revenue_returns_structured_data(): void
    {
        Http::fake([
            '*/v1/forecast/revenue' => Http::response([
                'forecast'     => [
                    ['day' => '2026-04-20', 'events' => 42, 'projected' => false],
                    ['day' => '2026-04-21', 'events' => 38, 'projected' => true],
                ],
                'model_id'     => 'revenue-ses-v1',
                'generated_at' => now()->toIso8601String(),
            ], 200),
        ]);

        $client = app(AiSidecarClient::class);
        $result = $client->forecastRevenue(['days_ahead' => 7]);

        $this->assertArrayHasKey('forecast', $result);
        $this->assertArrayHasKey('model_id', $result);
        $this->assertSame('revenue-ses-v1', $result['model_id']);
        $this->assertCount(2, $result['forecast']);

        $this->assertDatabaseHas('ai_invocations', [
            'endpoint' => '/v1/forecast/revenue',
            'outcome'  => 'ok',
        ]);
    }

    public function test_forecast_inventory_returns_structured_data(): void
    {
        Http::fake([
            '*/v1/forecast/inventory' => Http::response([
                'forecast'     => [
                    ['id' => 1, 'name' => 'Paracetamol 500mg', 'status' => 'critical',
                     'quantity_in_stock' => 0, 'minimum_stock_level' => 100],
                    ['id' => 2, 'name' => 'Gloves (L)', 'status' => 'warning',
                     'quantity_in_stock' => 5, 'minimum_stock_level' => 10],
                ],
                'model_id'     => 'inventory-threshold-v1',
                'generated_at' => now()->toIso8601String(),
            ], 200),
        ]);

        $client = app(AiSidecarClient::class);
        $result = $client->forecastInventory(['days_ahead' => 30]);

        $this->assertArrayHasKey('forecast', $result);
        $this->assertSame('inventory-threshold-v1', $result['model_id']);

        $statuses = array_column($result['forecast'], 'status');
        $this->assertContains('critical', $statuses);
        $this->assertContains('warning', $statuses);

        $this->assertDatabaseHas('ai_invocations', [
            'endpoint' => '/v1/forecast/inventory',
            'outcome'  => 'ok',
        ]);
    }

    public function test_forecast_revenue_throws_when_circuit_open(): void
    {
        Cache::put('ai_sidecar:cb_open', true, 300);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/circuit open/i');

        app(AiSidecarClient::class)->forecastRevenue();

        Http::assertNothingSent();
    }

    public function test_forecast_inventory_throws_when_circuit_open(): void
    {
        Cache::put('ai_sidecar:cb_open', true, 300);

        $this->expectException(\RuntimeException::class);

        app(AiSidecarClient::class)->forecastInventory();
    }

    public function test_forecast_revenue_logs_error_on_sidecar_failure(): void
    {
        Http::fake(fn () => Http::response('service unavailable', 503));

        try {
            app(AiSidecarClient::class)->forecastRevenue();
        } catch (\Exception) {}

        $this->assertDatabaseHas('ai_invocations', [
            'endpoint' => '/v1/forecast/revenue',
            'outcome'  => 'error',
        ]);
    }
}
