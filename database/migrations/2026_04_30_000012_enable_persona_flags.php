<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('platform_settings')
            ->where('provider', 'feature_flag')
            ->whereIn('platform_name', ['ai.admin.enabled', 'ai.ops.enabled', 'ai.compliance.enabled'])
            ->update(['meta' => json_encode(['value' => 'true']), 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('platform_settings')
            ->where('provider', 'feature_flag')
            ->whereIn('platform_name', ['ai.admin.enabled', 'ai.ops.enabled', 'ai.compliance.enabled'])
            ->update(['meta' => json_encode(['value' => 'false']), 'updated_at' => now()]);
    }
};
