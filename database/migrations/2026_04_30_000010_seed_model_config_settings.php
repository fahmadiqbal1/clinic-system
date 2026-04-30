<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $rows = [
            ['platform_name' => 'ai.model.provider',        'meta' => json_encode(['value' => 'ollama'])],
            ['platform_name' => 'ai.model.online_model_id', 'meta' => json_encode(['value' => 'gpt-4o-mini'])],
            ['platform_name' => 'ai.model.openai_key',      'meta' => json_encode(['value' => ''])],
            ['platform_name' => 'ai.model.anthropic_key',   'meta' => json_encode(['value' => ''])],
        ];

        foreach ($rows as $row) {
            DB::table('platform_settings')->updateOrInsert(
                ['platform_name' => $row['platform_name'], 'provider' => 'model_config'],
                ['meta' => $row['meta'], 'updated_at' => now()]
            );
        }
    }

    public function down(): void
    {
        DB::table('platform_settings')
            ->where('provider', 'model_config')
            ->delete();
    }
};
