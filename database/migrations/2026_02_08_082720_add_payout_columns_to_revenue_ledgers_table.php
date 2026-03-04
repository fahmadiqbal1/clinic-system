<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('revenue_ledgers', function (Blueprint $table) {
            $table->enum('payout_status', ['pending', 'paid'])->default('pending')->after('amount');
            $table->timestamp('paid_at')->nullable()->after('payout_status');
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete()->after('paid_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('revenue_ledgers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('paid_by');
            $table->dropColumn(['paid_at', 'payout_status']);
        });
    }
};
