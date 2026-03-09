<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // These columns chain after each other; all three are added atomically in this migration.
            // visit_id is guaranteed to exist (added in 2026_02_23_000003).
            $table->unsignedBigInteger('fbr_invoice_seq')->nullable()->after('fbr_qr_code')
                  ->comment('Auto-incrementing FBR invoice sequence number per POSID');
            $table->text('fbr_signature')->nullable()->after('fbr_invoice_seq')
                  ->comment('HMAC-SHA256 digital signature of the FBR submission payload');
            $table->json('fbr_response')->nullable()->after('fbr_signature')
                  ->comment('Full FBR IRIS API response archived for 5-year compliance record keeping');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['fbr_invoice_seq', 'fbr_signature', 'fbr_response']);
        });
    }
};
