<?php

namespace Tests\Feature\Ai;

use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ComplianceAiTest extends TestCase
{
    public function test_compliance_index_returns_404_when_flag_off(): void
    {
        $this->disableFlag('ai.compliance.enabled');
        $this->actingAs($this->makeOwner())->get('/owner/compliance-ai')->assertStatus(404);
    }

    public function test_compliance_run_returns_403_when_flag_off(): void
    {
        $this->disableFlag('ai.compliance.enabled');
        $this->actingAs($this->makeOwner())
            ->postJson('/owner/compliance-ai/run', [])
            ->assertStatus(403);
    }

    public function test_compliance_run_writes_action_request_on_escalation(): void
    {
        $this->enableFlag('ai.compliance.enabled');
        Http::fake([
            '*/v1/compliance/analyse' => Http::response([
                'model_id'              => 'compliance:etcslv:v1',
                'prompt_hash'           => str_repeat('c', 64),
                'rationale'             => '## AUDIT STATUS\nFAIL\n## CERTIFICATION READINESS\nNON_COMPLIANT',
                'confidence'            => 0.92,
                'status'                => 'NON_COMPLIANT',
                'escalation_pending'    => true,
                'evidence_refs'         => ['audit:verify-chain', 'chunk=500'],
                'requires_human_review' => true,
                'verification_issues'   => [],
            ], 200),
        ]);

        $r = $this->actingAs($this->makeOwner())
            ->postJson('/owner/compliance-ai/run', ['scope' => 'audit_chain']);

        $r->assertOk()->assertJsonPath('status', 'NON_COMPLIANT');

        $this->assertDatabaseHas('ai_action_requests', [
            'requested_by_source' => 'compliance_ai',
            'target_type'         => 'ComplianceFinding',
            'status'              => 'pending',
        ]);
    }

    public function test_compliance_run_does_not_write_action_request_when_compliant(): void
    {
        $this->enableFlag('ai.compliance.enabled');
        Http::fake([
            '*/v1/compliance/analyse' => Http::response([
                'model_id'              => 'compliance:etcslv:v1',
                'prompt_hash'           => str_repeat('d', 64),
                'rationale'             => '## AUDIT STATUS\nPASS',
                'confidence'            => 0.95,
                'status'                => 'COMPLIANT',
                'escalation_pending'    => false,
                'evidence_refs'         => [],
                'requires_human_review' => true,
                'verification_issues'   => [],
            ], 200),
        ]);

        $r = $this->actingAs($this->makeOwner())
            ->postJson('/owner/compliance-ai/run', ['scope' => 'full']);

        $r->assertOk()->assertJsonPath('status', 'COMPLIANT');

        $this->assertDatabaseMissing('ai_action_requests', [
            'requested_by_source' => 'compliance_ai',
        ]);
    }

    public function test_compliance_run_returns_503_on_circuit_open(): void
    {
        $this->enableFlag('ai.compliance.enabled');
        Cache::put('ai_sidecar:cb_open', true, 300);

        $r = $this->actingAs($this->makeOwner())
            ->postJson('/owner/compliance-ai/run', []);

        $r->assertStatus(503);
        Cache::forget('ai_sidecar:cb_open');
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
