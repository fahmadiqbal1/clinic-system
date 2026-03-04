<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add receipt_invoice_path to procurement_requests.
     * Stores the file path of the supplier invoice uploaded when receiving delivery.
     */
    public function up(): void
    {
        Schema::table('procurement_requests', function (Blueprint $table) {
            $table->string('receipt_invoice_path')->nullable()->after('notes');
            $table->timestamp('received_at')->nullable()->after('receipt_invoice_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('procurement_requests', function (Blueprint $table) {
            $table->dropColumn(['receipt_invoice_path', 'received_at']);
        });
    }
};
