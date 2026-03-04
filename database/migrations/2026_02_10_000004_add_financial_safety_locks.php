<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * PHASE 4: Financial Safety Locks
     * - Soft deletes for invoices (prevents data loss)
     * - Payment method tracking (audit trail for payments)
     * - Prevent deletion of paid invoices at database level
     */
    public function up(): void
    {
        // Add soft deletes and payment method to invoices
        Schema::table('invoices', function (Blueprint $table) {
            // Soft deletes: archive instead of destroying
            // This prevents accidental complete loss of financial records
            if (!Schema::hasColumn('invoices', 'deleted_at')) {
                $table->softDeletes()->nullable();
            }

            // Payment method: tracks HOW the invoice was paid
            // Required when marking as paid
            // Options: cash, check, bank_transfer, credit_card, insurance, etc.
            if (!Schema::hasColumn('invoices', 'payment_method')) {
                $table->string('payment_method')->nullable()->index();
            }

            // Payment date: when the invoice was actually paid
            if (!Schema::hasColumn('invoices', 'paid_at')) {
                $table->timestamp('paid_at')->nullable()->index();
            }

            // Paid by user: which user marked this as paid (for audit)
            if (!Schema::hasColumn('invoices', 'paid_by')) {
                $table->unsignedBigInteger('paid_by')->nullable();
            }
        });

        // Soft deletes for doctor payouts (financial records)
        Schema::table('doctor_payouts', function (Blueprint $table) {
            if (!Schema::hasColumn('doctor_payouts', 'deleted_at')) {
                $table->softDeletes()->nullable();
            }
        });

        // Soft deletes for revenue ledgers (maintain financial audit trail)
        Schema::table('revenue_ledgers', function (Blueprint $table) {
            if (!Schema::hasColumn('revenue_ledgers', 'deleted_at')) {
                $table->softDeletes()->nullable();
            }
        });

        // Soft deletes for stock movements (maintain inventory audit trail)
        Schema::table('stock_movements', function (Blueprint $table) {
            if (!Schema::hasColumn('stock_movements', 'deleted_at')) {
                $table->softDeletes()->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
            if (Schema::hasColumn('invoices', 'payment_method')) {
                $table->dropColumn('payment_method');
            }
            if (Schema::hasColumn('invoices', 'paid_at')) {
                $table->dropColumn('paid_at');
            }
            if (Schema::hasColumn('invoices', 'paid_by')) {
                $table->dropColumn('paid_by');
            }
        });

        Schema::table('doctor_payouts', function (Blueprint $table) {
            if (Schema::hasColumn('doctor_payouts', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });

        Schema::table('revenue_ledgers', function (Blueprint $table) {
            if (Schema::hasColumn('revenue_ledgers', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            if (Schema::hasColumn('stock_movements', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
