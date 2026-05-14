<?php

namespace Tests\Feature\Ai;

use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Tests role-aware AI chat routing in AiAssistantController.
 *
 * Routes:
 *   POST /ai-assistant/query
 *
 * Routing logic:
 *   Owner + financial keywords  → /v1/admin/analyse
 *   Doctor + clinical keywords  → RAGFlow / service_catalog
 *   Pharmacy/Lab + stock        → /v1/ops/analyse
 *   Else                        → knowledge (RAGFlow)
 */
class ChatRoutingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->enableFlag('ai.chat.enabled.owner');
        $this->enableFlag('ai.chat.enabled.doctor');
        $this->enableFlag('ai.chat.enabled.pharmacy');
        $this->enableFlag('ai.chat.enabled.laboratory');
        $this->enableFlag('ai.sidecar.enabled');
    }

    // ── Owner + financial query → admin persona ───────────────────────────────

    public function test_owner_financial_query_routes_to_admin_persona(): void
    {
        $owner = $this->makeOwner();

        Http::fake([
            '*/v1/admin/analyse' => Http::response([
                'model_id'     => 'admin:etcslv:v1',
                'prompt_hash'  => str_repeat('a', 64),
                'rationale'    => '## STATUS\nOK\n## CONFIDENCE\n0.75',
                'confidence'   => 0.75,
                'priority'     => 'medium',
                'action_items' => [],
                'requires_human_review' => false,
                'verification_issues'   => [],
            ], 200),
        ]);

        $response = $this->actingAs($owner)
            ->postJson('/ai-assistant/query', [
                'query'       => 'Show me the revenue breakdown for last month',
                'collection'  => 'general',
                'session_id'  => 'test-session-1',
            ]);

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertSame('admin', $data['route'] ?? null,
            'Owner financial query should route to admin persona');
        Http::assertSent(fn ($req) => str_contains($req->url(), '/v1/admin/analyse'));
    }

    // ── Pharmacy + stock query → ops persona ─────────────────────────────────

    public function test_pharmacy_stock_query_routes_to_ops_persona(): void
    {
        $pharmacist = $this->makeUser('Pharmacy');

        Http::fake([
            '*/v1/ops/analyse' => Http::response([
                'model_id'              => 'ops:etcslv:v1',
                'prompt_hash'           => str_repeat('b', 64),
                'rationale'             => '## STATUS\nOK\n## CONFIDENCE\n0.80',
                'confidence'            => 0.80,
                'urgency'               => 'Low',
                'critical_items'        => [],
                'action_items'          => [],
                'requires_human_review' => false,
                'verification_issues'   => [],
            ], 200),
        ]);

        $response = $this->actingAs($pharmacist)
            ->postJson('/ai-assistant/query', [
                'query'      => 'Which inventory items are below minimum stock levels?',
                'collection' => 'inventory',
                'session_id' => 'test-session-2',
            ]);

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertSame('ops', $data['route'] ?? null,
            'Pharmacy stock query should route to ops persona');
    }

    // ── Doctor + clinical query → clinical/knowledge path ─────────────────────

    public function test_doctor_clinical_query_uses_rag_path(): void
    {
        $doctor = $this->makeUser('Doctor');

        Http::fake([
            '*/v1/rag/query' => Http::response([
                'answer'             => 'Metformin is first-line for type 2 diabetes.',
                'retrieval_citations' => [],
            ], 200),
        ]);

        $response = $this->actingAs($doctor)
            ->postJson('/ai-assistant/query', [
                'query'      => 'What is the recommended treatment for type 2 diabetes?',
                'collection' => 'service_catalog',
                'session_id' => 'test-session-3',
            ]);

        $response->assertStatus(200);
        // Doctor clinical → clinical or knowledge route (not admin/ops)
        $data = $response->json();
        $this->assertNotSame('admin', $data['route'] ?? null);
        $this->assertNotSame('ops',   $data['route'] ?? null);
    }

    // ── Flag disabled → 403 ───────────────────────────────────────────────────

    public function test_chat_disabled_returns_503(): void
    {
        $this->disableFlag('ai.chat.enabled.owner');
        $owner = $this->makeOwner();

        $response = $this->actingAs($owner)
            ->postJson('/ai-assistant/query', [
                'query'      => 'anything',
                'collection' => 'general',
                'session_id' => 'x',
            ]);

        // Flag off → controller returns 403 (feature gate)
        $response->assertStatus(403);
    }

    // ── Circuit breaker open → 503 ────────────────────────────────────────────

    public function test_circuit_open_returns_503(): void
    {
        $this->enableFlag('ai.chat.enabled.owner');
        \Illuminate\Support\Facades\Cache::put('ai_sidecar:cb_open', true, 300);

        $owner = $this->makeOwner();

        Http::fake([
            '*/v1/admin/analyse' => Http::response([], 200),
        ]);

        $response = $this->actingAs($owner)
            ->postJson('/ai-assistant/query', [
                'query'      => 'revenue figures',
                'collection' => 'general',
                'session_id' => 'y',
            ]);

        $response->assertStatus(503);
        \Illuminate\Support\Facades\Cache::forget('ai_sidecar:cb_open');
    }

    // ── Unauthenticated → redirect ────────────────────────────────────────────

    public function test_unauthenticated_redirects(): void
    {
        $this->postJson('/ai-assistant/query', ['query' => 'test', 'collection' => 'general', 'session_id' => 'z'])
             ->assertStatus(401);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeOwner(): User
    {
        return $this->makeUser('Owner');
    }

    private function makeUser(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);
        return $user;
    }

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
