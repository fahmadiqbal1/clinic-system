<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('fbr_invoice_number')->nullable()->after('visit_id');
            $table->string('fbr_status')->nullable()->after('fbr_invoice_number')
                  ->comment('null=not_submitted, pending, submitted, failed');
            $table->timestamp('fbr_submitted_at')->nullable()->after('fbr_status');
            $table->string('fbr_irn', 500)->nullable()->after('fbr_submitted_at')
                  ->comment('Invoice Reference Number returned by FBR IRIS');
            $table->text('fbr_qr_code')->nullable()->after('fbr_irn')
                  ->comment('QR code data string for FBR invoice verification');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'fbr_invoice_number',
                'fbr_status',
                'fbr_submitted_at',
                'fbr_irn',
                'fbr_qr_code',
            ]);
        });
    }
};
