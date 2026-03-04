<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('doctor_payouts', function (Blueprint $table) {
            // Payout type: 'commission' (daily, doctors) or 'monthly' (salary + commission, other staff)
            $table->string('payout_type', 20)->default('commission')->after('status');

            // Approval workflow: null (no approval needed — doctors) or pending/approved/rejected
            $table->string('approval_status', 20)->nullable()->after('payout_type');
            $table->unsignedBigInteger('approved_by')->nullable()->after('approval_status');
            $table->timestamp('approved_at')->nullable()->after('approved_by');

            // Monthly payouts include salary component
            $table->decimal('salary_amount', 15, 2)->default(0)->after('paid_amount');

            $table->foreign('approved_by')->references('id')->on('users')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('doctor_payouts', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropColumn(['payout_type', 'approval_status', 'approved_by', 'approved_at', 'salary_amount']);
        });
    }
};
