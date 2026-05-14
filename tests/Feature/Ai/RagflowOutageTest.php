<?php

namespace Tests\Feature\Ai;

use App\Models\PlatformSetting;
use App\Services\AiSidecarClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RagflowOutageTest extends TestCase
{
    /**
     * When ai.chat.enabled.doctor is ON and sidecar returns 503,
     * the AI assistant endpoint degrades gracefully (returns 503 JSON,
     * does NOT bubble an exception into the HTTP response).
     */
    public function test_ai_assistant_query_degrades_when_sidecar_down(): void
    {
        $this->enableFlag('ai.chat.enabled.doctor');

        $doctor = \App\Models\User::factory()->create();
        $doctor->assignRole('Doctor');

        // Sidecar returns 503
        Http::fake(['*' => Http::response(null, 503)]);

        $response = $this->actingAs($doctor)
            ->postJson('/ai-assistant/query', [
                'query'      => 'What is the normal range for haemoglobin?',
                'collection' => 'service_catalog',
            ]);

        $response->assertStatus(503)
                 ->assertJsonStructure(['error'])
                 ->assertJsonMissing(['exception']);
    }

    /**
     * The AI assistant endpoint returns 403 when the role flag is OFF.
     */
    public function test_ai_assistant_blocked_when_flag_off(): void
    {
        $this->disableFlag('ai.chat.enabled.doctor');

        $doctor = \App\Models\User::factory()->create();
        $doctor->assignRole('Doctor');

        $response = $this->actingAs($doctor)
            ->postJson('/ai-assistant/query', ['query' => 'Any query']);

        $response->assertStatus(403);
    }

    /**
     * When sidecar circuit-breaker is open, ragQuery throws a RuntimeException.
     * The AI assistant endpoint catches it and returns 503, not a 500.
     */
    public function test_ai_assistant_handles_circuit_open(): void
    {
        $this->enableFlag('ai.chat.enabled.owner');

        $owner = \App\Models\User::factory()->create();
        $owner->assignRole('Owner');

        // Force the circuit-breaker key open in cache
        \Illuminate\Support\Facades\Cache::put('ai_sidecar:cb_open', true, 300);

        $response = $this->actingAs($owner)
            ->postJson('/ai-assistant/query', [
                'query'      => 'What drugs require prescription?',
                'collection' => 'inventory',
            ]);

        $response->assertStatus(503)
                 ->assertJson(['error' => 'AI assistant temporarily unavailable.']);

        \Illuminate\Support\Facades\Cache::forget('ai_sidecar:cb_open');
    }

    /**
     * The /ai-assistant/flag endpoint writes an ai_action_requests row.
     */
    public function test_flag_writes_ai_action_request(): void
    {
        $this->enableFlag('ai.chat.enabled.doctor');

        $doctor = \App\Models\User::factory()->create();
        $doctor->assignRole('Doctor');

        $response = $this->actingAs($doctor)
            ->postJson('/ai-assistant/flag', [
                'query'     => 'What is the protocol for CBC?',
                'answer'    => 'CBC is ordered for…',
                'citations' => ['protocols p.3'],
            ]);

        $response->assertOk()->assertJson(['flagged' => true]);

        $this->assertDatabaseHas('ai_action_requests', [
            'requested_by_source' => 'ai_assistant',
            'proposed_action'     => 'owner_review',
            'status'              => 'pending',
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function enableFlag(string $flag): void
    {
        PlatformSetting::updateOrCreate(
            ['platform_name' => $flag, 'provider' => 'feature_flag'],
            ['meta' => ['value' => true]]
        );
    }

    private function disableFlag(string $flag): void
    {
        PlatformSetting::updateOrCreate(
            ['platform_name' => $flag, 'provider' => 'feature_flag'],
            ['meta' => ['value' => false]]
        );
    }
}
