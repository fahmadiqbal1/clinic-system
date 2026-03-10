<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add soft deletes to models that should never lose data.
 * 
 * In healthcare context, medical records must be retained
 * for compliance (typically 5+ years). Soft deletes ensure
 * data is never truly deleted.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Patients - medical records retention
        Schema::table('patients', function (Blueprint $table) {
            $table->softDeletes();
        });

        // Inventory items - audit trail for stock
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->softDeletes();
        });

        // Procurement requests - financial audit trail
        Schema::table('procurement_requests', function (Blueprint $table) {
            $table->softDeletes();
        });

        // Triage vitals - medical records
        Schema::table('triage_vitals', function (Blueprint $table) {
            $table->softDeletes();
        });

        // Prescriptions - medical records
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->softDeletes();
        });

        // AI analyses - medical records
        Schema::table('ai_analyses', function (Blueprint $table) {
            $table->softDeletes();
        });

        // Visits - patient history
        Schema::table('visits', function (Blueprint $table) {
            $table->softDeletes();
        });

        // Staff contracts - employment records
        Schema::table('staff_contracts', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('procurement_requests', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('triage_vitals', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('prescriptions', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('ai_analyses', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('visits', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('staff_contracts', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
