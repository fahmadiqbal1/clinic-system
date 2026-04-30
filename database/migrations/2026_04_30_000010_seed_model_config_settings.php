<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the model_config rows in platform_settings.
 * Every row here maps 1-to-1 with a sidecar env var via _DB_TO_ENV in health.py.
 * Add rows here when adding a new provider — no PHP code changes needed.
 */
return new class extends Migration
{
    public function up(): void
    {
        $defaults = [
            // Active provider
            'ai.model.provider'        => 'ollama',
            // Ollama
            'ai.model.ollama.url'      => 'http://127.0.0.1:8081',
            'ai.model.ollama.model'    => '',
            // OpenAI
            'ai.model.openai.base_url' => 'https://api.openai.com/v1',
            'ai.model.openai.model'    => '',
            'ai.model.openai.key'      => '',
            // Anthropic
            'ai.model.anthropic.model' => '',
            'ai.model.anthropic.key'   => '',
            // Hugging Face
            'ai.model.hf.base_url'     => 'https://api-inference.huggingface.co/v1',
            'ai.model.hf.model'        => '',
            'ai.model.hf.key'          => '',
        ];

        foreach ($defaults as $name => $value) {
            DB::table('platform_settings')->updateOrInsert(
                ['platform_name' => $name, 'provider' => 'model_config'],
                ['meta' => json_encode(['value' => $value]), 'updated_at' => now()]
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
