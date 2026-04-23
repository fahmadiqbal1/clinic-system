<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $db       = config('database.connections.mysql.database');
        $password = env('CLINIC_RO_PASSWORD', '');

        if (empty($password)) {
            // Log a warning but don't fail migration — developer must set CLINIC_RO_PASSWORD in .env.
            \Illuminate\Support\Facades\Log::warning(
                'Phase 0: CLINIC_RO_PASSWORD is not set in .env. ' .
                'clinic_ro MySQL user was NOT created. Set the env var and re-run this migration.'
            );
            return;
        }

        // Create the read-only sidecar user (safe to re-run: IF NOT EXISTS).
        DB::unprepared("CREATE USER IF NOT EXISTS 'clinic_ro'@'localhost' IDENTIFIED BY '{$password}'");
        DB::unprepared("CREATE USER IF NOT EXISTS 'clinic_ro'@'%' IDENTIFIED BY '{$password}'");

        // SELECT on full tables the sidecar needs for trend/forecast queries.
        DB::unprepared("GRANT SELECT ON `{$db}`.`audit_logs` TO 'clinic_ro'@'localhost'");
        DB::unprepared("GRANT SELECT ON `{$db}`.`audit_logs` TO 'clinic_ro'@'%'");

        DB::unprepared("GRANT SELECT ON `{$db}`.`service_catalog` TO 'clinic_ro'@'localhost'");
        DB::unprepared("GRANT SELECT ON `{$db}`.`service_catalog` TO 'clinic_ro'@'%'");

        // inventory_items: non-financial columns only (exclude purchase_price, selling_price, weighted_avg_cost).
        $inventoryCols = implode(', ', [
            'id', 'department', 'name', 'chemical_formula', 'sku', 'barcode',
            'unit', 'quantity_in_stock', 'minimum_stock_level',
            'requires_prescription', 'is_active', 'created_at', 'updated_at', 'deleted_at',
        ]);
        DB::unprepared("GRANT SELECT ({$inventoryCols}) ON `{$db}`.`inventory_items` TO 'clinic_ro'@'localhost'");
        DB::unprepared("GRANT SELECT ({$inventoryCols}) ON `{$db}`.`inventory_items` TO 'clinic_ro'@'%'");

        // ai_invocations table is created in Phase 2 — grant is added there.

        DB::unprepared('FLUSH PRIVILEGES');
    }

    public function down(): void
    {
        DB::unprepared("DROP USER IF EXISTS 'clinic_ro'@'localhost'");
        DB::unprepared("DROP USER IF EXISTS 'clinic_ro'@'%'");
        DB::unprepared('FLUSH PRIVILEGES');
    }
};
