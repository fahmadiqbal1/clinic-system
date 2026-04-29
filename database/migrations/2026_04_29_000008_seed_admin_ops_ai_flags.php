<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Phase 8 — Administrative & Operations AI feature flags.
     * All default OFF. Owner enables individually after sidecar smoke test.
     */
    private array $flags = [
        'ai.admin.enabled'      => 'AI — Administrative persona (revenue / discount / FBR / payout)',
        'ai.ops.enabled'        => 'AI — Operations persona (inventory / procurement / queue health)',
        'ai.compliance.enabled' => 'AI — Compliance persona (audit chain / PHI access / SOC2)',
    ];

    public function up(): void
    {
        foreach ($this->flags as $name => $label) {
            DB::table('platform_settings')->upsert(
                [
                    'platform_name' => $name,
                    'provider'      => 'feature_flag',
                    'status'        => 'disconnected',
                    'meta'          => json_encode(['value' => false, 'label' => $label]),
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ],
                ['platform_name'],
                ['meta', 'updated_at']
            );
        }
    }

    public function down(): void
    {
        DB::table('platform_settings')
            ->where('provider', 'feature_flag')
            ->whereIn('platform_name', array_keys($this->flags))
            ->delete();
    }
};
