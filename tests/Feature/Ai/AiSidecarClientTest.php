<?php

namespace Tests\Feature\Ai;

use App\Models\Patient;
use App\Services\AiSidecarClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiSidecarClientTest extends TestCase
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

    public function test_consult_sends_case_token_not_raw_phi(): void
    {
        $patient = Patient::factory()->create([
            'first_name'    => 'Ali',
            'last_name'     => 'Testuser',
            'phone'         => '03009998877',
            'email'         => 'ali.testuser@example.com',
            'date_of_birth' => now()->subYears(30),
            'gender'        => 'male',
        ]);

        $captured = null;

        Http::fake(function ($request) use (&$captured) {
            $captured = $request->data();
            return Http::response([
                'model_id'              => 'medgemma:sidecar',
                'prompt_hash'           => str_repeat('a', 64),
                'rationale'             => 'Clinical assessment: stable.',
                'confidence'            => 0.80,
                'requires_human_review' => true,
                'retrieval_citations'   => [],
            ], 200);
        });

        $client = app(AiSidecarClient::class);
        $client->consult($patient);

        $this->assertNotNull($captured, 'HTTP request was not captured');
        $this->assertArrayHasKey('case_token', $captured);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $captured['case_token']);

        $encoded = json_encode($captured);
        $this->assertStringNotContainsString('Ali', $encoded);
        $this->assertStringNotContainsString('Testuser', $encoded);
        $this->assertStringNotContainsString('03009998877', $encoded);
        $this->assertStringNotContainsString('ali.testuser', $encoded);
    }

    public function test_invocation_is_logged_after_successful_call(): void
    {
        Http::fake([
            '*/v1/rag/query' => Http::response(['answer' => 'yes', 'model_id' => 'test-model'], 200),
        ]);

        $client = app(AiSidecarClient::class);
        $client->ragQuery('what is the paracetamol dosage?');

        $this->assertDatabaseHas('ai_invocations', [
            'endpoint' => '/v1/rag/query',
            'outcome'  => 'ok',
        ]);
    }

    public function test_failed_call_is_logged_as_error(): void
    {
        Http::fake(fn () => Http::response('bad gateway', 502));

        $client = app(AiSidecarClient::class);

        try {
            $client->ragQuery('test');
        } catch (\Exception) {}

        $this->assertDatabaseHas('ai_invocations', [
            'endpoint' => '/v1/rag/query',
            'outcome'  => 'error',
        ]);
    }
}
