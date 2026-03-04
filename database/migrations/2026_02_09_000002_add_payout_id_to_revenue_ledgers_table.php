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
            $table->unsignedBigInteger('payout_id')->nullable()->after('paid_by');
            
            // Foreign key
            $table->foreign('payout_id')->references('id')->on('doctor_payouts')->onDelete('restrict');
            
            // Index for payout queries
            $table->index('payout_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('revenue_ledgers', function (Blueprint $table) {
            $table->dropForeign(['payout_id']);
            $table->dropIndex(['payout_id']);
            $table->dropColumn('payout_id');
        });
    }
};
