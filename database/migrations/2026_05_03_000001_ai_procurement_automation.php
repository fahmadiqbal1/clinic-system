<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // inventory_items — manufacturer identity + stale-item tracking
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->string('manufacturer')->nullable()->after('name');
            $table->string('manufacturer_tag', 8)->nullable()->after('manufacturer');
            $table->timestamp('last_stocked_at')->nullable()->after('weighted_avg_cost');
        });

        // Drop any pre-existing soft-duplicate index before adding the unique constraint
        // (safe to ignore if it doesn't exist)
        try {
            Schema::table('inventory_items', function (Blueprint $table) {
                $table->unique(['name', 'manufacturer', 'department'], 'idx_item_identity');
            });
        } catch (\Exception $e) {
            // Index already exists — continue
        }

        // procurement_requests — AI approval fields, receipt deadline, checklist metadata
        Schema::table('procurement_requests', function (Blueprint $table) {
            // Extend type enum to include new_item_request
            // MySQL: use a raw ALTER to change the ENUM column
            \DB::statement("ALTER TABLE procurement_requests MODIFY COLUMN type ENUM(
                'inventory','service','equipment_change','catalog_change','price_list','new_item_request'
            ) NOT NULL DEFAULT 'inventory'");

            $table->timestamp('ai_approved_at')->nullable()->after('received_at');
            $table->text('ai_approval_reason')->nullable()->after('ai_approved_at');
            $table->timestamp('receipt_deadline_at')->nullable()->after('ai_approval_reason');
            $table->timestamp('receipt_overdue_notified_at')->nullable()->after('receipt_deadline_at');
            $table->date('checklist_date')->nullable()->after('receipt_overdue_notified_at');
            $table->string('checklist_supplier')->nullable()->after('checklist_date');
        });

        // vendors — checklist freshness tracking
        Schema::table('vendors', function (Blueprint $table) {
            $table->date('last_checklist_date')->nullable()->after('is_approved');
            $table->date('checklist_valid_until')->nullable()->after('last_checklist_date');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            // Drop unique index first
            $table->dropUnique('idx_item_identity');
            $table->dropColumn(['manufacturer', 'manufacturer_tag', 'last_stocked_at']);
        });

        Schema::table('procurement_requests', function (Blueprint $table) {
            \DB::statement("ALTER TABLE procurement_requests MODIFY COLUMN type ENUM(
                'inventory','service','equipment_change','catalog_change','price_list'
            ) NOT NULL DEFAULT 'inventory'");

            $table->dropColumn([
                'ai_approved_at', 'ai_approval_reason',
                'receipt_deadline_at', 'receipt_overdue_notified_at',
                'checklist_date', 'checklist_supplier',
            ]);
        });

        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn(['last_checklist_date', 'checklist_valid_until']);
        });
    }
};
