<?php

namespace Tests\Feature\Ai;

use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminAiTest extends TestCase
{
    public function test_admin_ai_index_returns_404_when_flag_off(): void
    {
        $this->disableFlag('ai.admin.enabled');
        $owner = $this->makeOwner();

        $this->actingAs($owner)->get('/owner/admin-ai')->assertStatus(404);
    }

    public function test_admin_ai_analyse_returns_403_when_flag_off(): void
    {
        $this->disableFlag('ai.admin.enabled');
        $owner = $this->makeOwner();

        $this->actingAs($owner)
            ->postJson('/owner/admin-ai/analyse', [])
            ->assertStatus(403);
    }

    public function test_admin_ai_analyse_proxies_to_sidecar_when_flag_on(): void
    {
        $this->enableFlag('ai.admin.enabled');
        $owner = $this->makeOwner();

        Http::fake([
            '*/v1/admin/analyse' => Http::response([
                'model_id'              => 'admin:etcslv:v1',
                'prompt_hash'           => str_repeat('a', 64),
                'rationale'             => '## FINDING ...',
                'confidence'            => 0.72,
                'priority'              => 'High',
                'requires_human_review' => true,
                'action_items'          => ['1. [High] Review FBR queue — Owner — EOD'],
                'verification_issues'   => [],
            ], 200),
        ]);

        $r = $this->actingAs($owner)
            ->postJson('/owner/admin-ai/analyse', ['query_type' => 'fbr_status']);

        $r->assertOk()->assertJsonPath('priority', 'High');
    }

    public function test_admin_ai_analyse_returns_503_on_circuit_open(): void
    {
        $this->enableFlag('ai.admin.enabled');
        $owner = $this->makeOwner();

        Cache::put('ai_sidecar:cb_open', true, 300);

        $r = $this->actingAs($owner)
            ->postJson('/owner/admin-ai/analyse', ['query_type' => 'general']);

        $r->assertStatus(503)
            ->assertJson(['error' => 'Administrative AI temporarily unavailable.']);

        Cache::forget('ai_sidecar:cb_open');
    }

    public function test_admin_ai_validates_query_type(): void
    {
        $this->enableFlag('ai.admin.enabled');
        $owner = $this->makeOwner();

        $this->actingAs($owner)
            ->postJson('/owner/admin-ai/analyse', ['query_type' => 'invalid'])
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
