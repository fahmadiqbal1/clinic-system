<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commission_configs', function (Blueprint $table) {
            $table->boolean('is_default')->default(false)->after('is_active');
        });

        // Backfill: rows with user_id IS NULL are defaults
        DB::table('commission_configs')
            ->whereNull('user_id')
            ->update(['is_default' => true]);

        // Drop old unique index that only covered (service_type, user_id)
        // and replace with proper constraints
        Schema::table('commission_configs', function (Blueprint $table) {
            $table->dropUnique(['service_type', 'user_id']);
        });

        // New constraint: unique(service_type, role, user_id) — prevents duplicate per-user rows
        Schema::table('commission_configs', function (Blueprint $table) {
            $table->unique(['service_type', 'role', 'user_id'], 'cc_service_role_user_unique');
        });
    }

    public function down(): void
    {
        Schema::table('commission_configs', function (Blueprint $table) {
            $table->dropUnique('cc_service_role_user_unique');
            $table->unique(['service_type', 'user_id']);
            $table->dropColumn('is_default');
        });
    }
};
