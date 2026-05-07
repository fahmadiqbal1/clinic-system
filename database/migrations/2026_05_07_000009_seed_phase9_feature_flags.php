<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase 9 feature flags.
 *
 * GraphRAG, smart prescriptions, Livewire components, and Reverb WebSockets
 * are all gated behind flags. All default OFF — the owner enables them
 * individually via /owner/platform-settings once infrastructure is ready.
 * Watchdog flags default ON as they are purely read/notify (no side-effects).
 */
return new class extends Migration
{
    private array $flags = [
        'ai.graphrag.enabled'            => ['value' => false, 'label' => 'GraphRAG — Knowledge Graph + RAG combined retrieval'],
        'ai.smart_prescriptions.enabled' => ['value' => false, 'label' => 'Smart Prescription Suggestions (graph-powered)'],
        'ui.livewire.pharmacy_barcode'   => ['value' => false, 'label' => 'Pharmacy Barcode Dispense (Livewire)'],
        'ui.livewire.triage_queue'       => ['value' => false, 'label' => 'Triage Real-Time Queue (Livewire)'],
        'ui.reverb.enabled'              => ['value' => false, 'label' => 'Laravel Reverb WebSockets'],
        'ai.fbr_watchdog.enabled'        => ['value' => true,  'label' => 'FBR Compliance Watchdog (auto-quarantine)'],
        'ai.revenue_leakage.enabled'     => ['value' => true,  'label' => 'Revenue Leakage Watchdog (unbilled consultation detection)'],
    ];

    public function up(): void
    {
        foreach ($this->flags as $name => $meta) {
            DB::table('platform_settings')->upsert(
                [
                    'platform_name' => $name,
                    'provider'      => 'feature_flag',
                    'status'        => 'disconnected',
                    'meta'          => json_encode($meta),
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
