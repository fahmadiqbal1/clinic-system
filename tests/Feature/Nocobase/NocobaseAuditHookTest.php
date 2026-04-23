<?php

namespace Tests\Feature\Nocobase;

use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class NocobaseAuditHookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-nocobase-webhook-secret-32ch';

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('clinic.nocobase_webhook_secret', self::SECRET);
    }

    private function sign(string $body): string
    {
        return 'sha256=' . hash_hmac('sha256', $body, self::SECRET);
    }

    private function payload(string $table = 'equipment', string $event = 'afterCreate'): array
    {
        return [
            'table'  => $table,
            'event'  => $event,
            'record' => ['id' => 42, 'name' => 'Logiq S7', 'serial_number' => 'GE-2024-001'],
        ];
    }

    public function test_valid_hmac_writes_audit_log(): void
    {
        $body = json_encode($this->payload());

        $response = $this->postJson('/api/nocobase/audit-hook', $this->payload(), [
            'X-NocoBase-Signature' => $this->sign($body),
        ]);

        $response->assertStatus(200)->assertJson(['status' => 'logged']);

        $this->assertDatabaseHas('audit_logs', [
            'action'         => 'nocobase.equipment.afterCreate',
            'auditable_type' => 'Nocobase',
            'auditable_id'   => 42,
        ]);
    }

    public function test_invalid_hmac_returns_401(): void
    {
        $response = $this->postJson('/api/nocobase/audit-hook', $this->payload(), [
            'X-NocoBase-Signature' => 'sha256=deadbeef',
        ]);

        $response->assertStatus(401);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_missing_signature_header_returns_401(): void
    {
        $response = $this->postJson('/api/nocobase/audit-hook', $this->payload());

        $response->assertStatus(401);
    }

    public function test_valid_hmac_missing_table_returns_422(): void
    {
        $payload = ['event' => 'afterCreate', 'record' => ['id' => 1]];
        $body    = json_encode($payload);

        $response = $this->postJson('/api/nocobase/audit-hook', $payload, [
            'X-NocoBase-Signature' => $this->sign($body),
        ]);

        $response->assertStatus(422);
    }

    public function test_audit_action_follows_nocobase_table_event_pattern(): void
    {
        $payload = $this->payload('service_history', 'afterUpdate');
        $body    = json_encode($payload);

        $this->postJson('/api/nocobase/audit-hook', $payload, [
            'X-NocoBase-Signature' => $this->sign($body),
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'nocobase.service_history.afterUpdate',
        ]);
    }
}
