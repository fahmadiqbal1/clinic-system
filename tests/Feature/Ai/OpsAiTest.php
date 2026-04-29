<?php

namespace Tests\Feature\Ai;

use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpsAiTest extends TestCase
{
    public function test_ops_ai_index_returns_404_when_flag_off(): void
    {
        $this->disableFlag('ai.ops.enabled');
        $this->actingAs($this->makeOwner())->get('/owner/ops-ai')->assertStatus(404);
    }

    public function test_ops_ai_analyse_returns_403_when_flag_off(): void
    {
        $this->disableFlag('ai.ops.enabled');
        $this->actingAs($this->makeOwner())
            ->postJson('/owner/ops-ai/analyse', [])
            ->assertStatus(403);
    }

    public function test_ops_ai_proxies_to_sidecar(): void
    {
        $this->enableFlag('ai.ops.enabled');
        Http::fake([
            '*/v1/ops/analyse' => Http::response([
                'model_id'              => 'ops:etcslv:v1',
                'prompt_hash'           => str_repeat('b', 64),
                'rationale'             => '## STATUS\nCritical\n## CRITICAL ITEMS\n- Paracetamol 500mg',
                'confidence'            => 0.81,
                'urgency'               => 'Critical',
                'critical_items'        => ['Paracetamol 500mg'],
                'action_items'          => ['1. [Critical] Reorder Paracetamol — Pharmacy — Today'],
                'requires_human_review' => true,
                'verification_issues'   => [],
            ], 200),
        ]);

        $r = $this->actingAs($this->makeOwner())
            ->postJson('/owner/ops-ai/analyse', ['domain' => 'inventory']);

        $r->assertOk()
            ->assertJsonPath('urgency', 'Critical')
            ->assertJsonPath('critical_items.0', 'Paracetamol 500mg');
    }

    public function test_ops_ai_returns_503_on_circuit_open(): void
    {
        $this->enableFlag('ai.ops.enabled');
        Cache::put('ai_sidecar:cb_open', true, 300);

        $r = $this->actingAs($this->makeOwner())
            ->postJson('/owner/ops-ai/analyse', ['domain' => 'general']);

        $r->assertStatus(503);
        Cache::forget('ai_sidecar:cb_open');
    }

    public function test_ops_ai_validates_domain(): void
    {
        $this->enableFlag('ai.ops.enabled');
        $this->actingAs($this->makeOwner())
            ->postJson('/owner/ops-ai/analyse', ['domain' => 'not-a-domain'])
            ->assertStatus(422);
    }

    private function makeOwner(): User
    {
        $owner = User::factory()->create();
        $owner->assignRole('Owner');
        return $owner;
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
