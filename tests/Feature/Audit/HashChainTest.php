<?php

namespace Tests\Feature\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HashChainTest extends TestCase
{
    use RefreshDatabase;

    public function test_log_writes_prev_hash_and_row_hash(): void
    {
        $first = AuditLog::log('test_action', 'App\Models\User', 1);

        $this->assertNotEmpty($first->row_hash);
        $this->assertSame('', $first->prev_hash);
        $this->assertSame(64, strlen($first->row_hash));
    }

    public function test_second_log_chains_from_first(): void
    {
        $first  = AuditLog::log('first_action', 'App\Models\User', 1);
        $second = AuditLog::log('second_action', 'App\Models\User', 2);

        $this->assertSame($first->row_hash, $second->prev_hash);
    }

    public function test_row_hash_is_deterministic(): void
    {
        $log = AuditLog::log('deterministic_test', 'App\Models\Patient', 99);

        $canonical = AuditLog::canonicalJson([
            'user_id'        => $log->user_id,
            'action'         => $log->action,
            'auditable_type' => $log->auditable_type,
            'auditable_id'   => $log->auditable_id,
            'before_state'   => $log->before_state,
            'after_state'    => $log->after_state,
            'ip_address'     => $log->ip_address,
            'user_agent'     => $log->user_agent,
            'session_id'     => $log->session_id,
            'created_at'     => $log->created_at->format('Y-m-d H:i:s'),
        ]);

        $expectedHash = hash('sha256', ($log->prev_hash ?? '') . '|' . $canonical);

        $this->assertSame($expectedHash, $log->row_hash);
    }

    public function test_verify_chain_passes_on_intact_chain(): void
    {
        AuditLog::log('action_a', 'App\Models\User', 1);
        AuditLog::log('action_b', 'App\Models\Invoice', 10);
        AuditLog::log('action_c', 'App\Models\Patient', 5);

        $this->artisan('audit:verify-chain')->assertSuccessful();
    }

    public function test_verify_chain_fails_when_row_hash_is_tampered(): void
    {
        $first  = AuditLog::log('action_x', 'App\Models\User', 1);
        $second = AuditLog::log('action_y', 'App\Models\User', 2);

        // The UPDATE trigger correctly blocks direct tampering of existing rows.
        // Simulate a chain-break by INSERTing a third row whose prev_hash is valid
        // but whose row_hash has been forged — as if an attacker added a record
        // with a falsified hash outside the normal log() path.
        DB::table('audit_logs')->insert([
            'user_id'        => null,
            'action'         => 'forged_action',
            'auditable_type' => 'App\Models\User',
            'auditable_id'   => 99,
            'before_state'   => null,
            'after_state'    => null,
            'ip_address'     => null,
            'user_agent'     => null,
            'session_id'     => null,
            'prev_hash'      => $second->row_hash,
            'row_hash'       => str_repeat('0', 64), // intentionally wrong
            'created_at'     => now()->format('Y-m-d H:i:s'),
        ]);

        $this->artisan('audit:verify-chain')->assertFailed();
    }
}
