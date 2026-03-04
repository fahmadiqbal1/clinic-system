<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add change-request fields for equipment/catalog approval workflow.
     *
     * Also expands the `type` ENUM to include the new change-request types
     * required by the equipment/catalog approval workflows.
     */
    public function up(): void
    {
        // Expand the type ENUM to include the new change-request values
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE procurement_requests MODIFY COLUMN type ENUM('inventory', 'service', 'equipment_change', 'catalog_change') NOT NULL DEFAULT 'inventory'");
        }

        Schema::table('procurement_requests', function (Blueprint $table) {
            $table->json('change_payload')->nullable()->after('type');
            $table->string('change_action', 20)->nullable()->after('change_payload'); // 'create', 'update', 'delete'
            $table->string('target_model')->nullable()->after('change_action');
            $table->unsignedBigInteger('target_id')->nullable()->after('target_model');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('procurement_requests', function (Blueprint $table) {
            $table->dropColumn(['change_payload', 'change_action', 'target_model', 'target_id']);
        });

        // Revert the type ENUM back to the original values
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE procurement_requests MODIFY COLUMN type ENUM('inventory', 'service') NOT NULL DEFAULT 'inventory'");
        }
    }
};
