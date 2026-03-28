<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add missing indexes identified during stress test:
 *  - visits.doctor_id          (used in all doctor queue queries)
 *  - visits.triage_nurse_id    (used in triage dashboard)
 *  - appointments.booked_by    (foreign key without index)
 *  - invoice_items.service_catalog_id (foreign key without index)
 *  - revenue_ledgers.role_type (filtered in financial reports)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            if (!$this->hasIndex('visits', 'visits_doctor_id_index')) {
                $table->index('doctor_id');
            }
            if (!$this->hasIndex('visits', 'visits_triage_nurse_id_index')) {
                $table->index('triage_nurse_id');
            }
        });

        Schema::table('appointments', function (Blueprint $table) {
            if (!$this->hasIndex('appointments', 'appointments_booked_by_index')) {
                $table->index('booked_by');
            }
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            if (!$this->hasIndex('invoice_items', 'invoice_items_service_catalog_id_index')) {
                $table->index('service_catalog_id');
            }
        });

        Schema::table('revenue_ledgers', function (Blueprint $table) {
            if (!$this->hasIndex('revenue_ledgers', 'revenue_ledgers_role_type_index')) {
                $table->index('role_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->dropIndex(['doctor_id']);
            $table->dropIndex(['triage_nurse_id']);
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex(['booked_by']);
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropIndex(['service_catalog_id']);
        });

        Schema::table('revenue_ledgers', function (Blueprint $table) {
            $table->dropIndex(['role_type']);
        });
    }

    private function hasIndex(string $table, string $index): bool
    {
        return collect(\Illuminate\Support\Facades\Schema::getIndexes($table))
            ->pluck('name')
            ->contains($index);
    }
};
