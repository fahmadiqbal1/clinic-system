<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $defaults = [
            'ai.model.groq.model' => '',
            'ai.model.groq.key'   => '',
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
            ->whereIn('platform_name', ['ai.model.groq.model', 'ai.model.groq.key'])
            ->delete();
    }
};
