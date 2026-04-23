<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * All AI/admin feature flags default to OFF.
     * Owner enables them role-by-role via the settings UI.
     */
    private array $flags = [
        'ai.sidecar.enabled'            => 'AI Sidecar (FastAPI)',
        'ai.ragflow.enabled'            => 'RAGFlow Document RAG',
        'ai.chat.enabled.owner'         => 'AI Chat — Owner',
        'ai.chat.enabled.doctor'        => 'AI Chat — Doctor',
        'ai.chat.enabled.laboratory'    => 'AI Chat — Laboratory',
        'ai.chat.enabled.radiology'     => 'AI Chat — Radiology',
        'ai.chat.enabled.pharmacy'      => 'AI Chat — Pharmacy',
        'admin.nocobase.enabled'        => 'NocoBase Admin',
        'ai.gitnexus.enabled'           => 'GitNexus Architecture View',
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
