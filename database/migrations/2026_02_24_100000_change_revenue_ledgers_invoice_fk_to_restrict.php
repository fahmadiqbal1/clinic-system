<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Change revenue_ledgers.invoice_id FK from cascadeOnDelete to restrictOnDelete.
 *
 * Paid invoices with ledger entries must never be deletable — the cascade
 * would silently destroy the entire financial audit trail.
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            // SQLite does not enforce FK constraints by default and does not
            // support ALTER TABLE ... DROP FOREIGN KEY. Skip on SQLite.
            return;
        }

        Schema::table('revenue_ledgers', function (Blueprint $table) {
            $table->dropForeign(['invoice_id']);
            $table->foreign('invoice_id')
                ->references('id')
                ->on('invoices')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            return;
        }

        Schema::table('revenue_ledgers', function (Blueprint $table) {
            $table->dropForeign(['invoice_id']);
            $table->foreign('invoice_id')
                ->references('id')
                ->on('invoices')
                ->cascadeOnDelete();
        });
    }
};
