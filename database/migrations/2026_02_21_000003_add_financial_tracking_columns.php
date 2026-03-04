<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add discount and net_amount to invoices
        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('discount_amount', 10, 2)->default(0)->after('total_amount');
            $table->unsignedBigInteger('discount_by')->nullable()->after('discount_amount');
            $table->decimal('net_amount', 10, 2)->nullable()->after('discount_by');

            $table->foreign('discount_by')->references('id')->on('users')->nullOnDelete();
        });

        // Backfill net_amount = total_amount - discount_amount for existing rows
        DB::statement('UPDATE invoices SET net_amount = total_amount - discount_amount WHERE net_amount IS NULL');

        // Add weighted average cost to inventory_items
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->decimal('weighted_avg_cost', 12, 2)->default(0)->after('selling_price');
        });

        // Add unit_cost to stock_movements for WAC tracking
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->decimal('unit_cost', 10, 2)->nullable()->after('quantity');
        });

        // Add entry_type and category to revenue_ledgers for balanced ledger
        Schema::table('revenue_ledgers', function (Blueprint $table) {
            $table->string('entry_type')->default('debit')->after('role_type'); // credit or debit
            $table->string('category')->nullable()->after('entry_type'); // revenue, cogs, commission, charity
        });

        // Change role_type from enum to varchar to support new roles (Charity, Technician)
        // SQLite doesn't support ALTER COLUMN, so we handle this gracefully
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE revenue_ledgers MODIFY COLUMN role_type VARCHAR(50)");
        }
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['discount_by']);
            $table->dropColumn(['discount_amount', 'discount_by', 'net_amount']);
        });

        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropColumn('weighted_avg_cost');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropColumn('unit_cost');
        });

        Schema::table('revenue_ledgers', function (Blueprint $table) {
            $table->dropColumn(['entry_type', 'category']);
        });
    }
};
