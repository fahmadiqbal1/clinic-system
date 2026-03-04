<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Actor tracking: who created and who performed the service
            $table->foreignId('created_by_user_id')->nullable()->after('paid_by')
                ->constrained('users')->nullOnDelete();
            $table->foreignId('performed_by_user_id')->nullable()->after('created_by_user_id')
                ->constrained('users')->nullOnDelete();

            // Discount approval workflow
            $table->string('discount_status', 20)->default('none')->after('discount_reason');
            $table->foreignId('discount_requested_by')->nullable()->after('discount_status')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('discount_requested_at')->nullable()->after('discount_requested_by');
            $table->foreignId('discount_approved_by')->nullable()->after('discount_requested_at')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('discount_approved_at')->nullable()->after('discount_approved_by');
        });

        // Backfill: consultation performed_by = prescribing_doctor_id
        DB::table('invoices')
            ->where('department', 'consultation')
            ->whereNotNull('prescribing_doctor_id')
            ->whereNull('performed_by_user_id')
            ->update(['performed_by_user_id' => DB::raw('prescribing_doctor_id')]);

        // Backfill: existing discounts get discount_status = approved
        DB::table('invoices')
            ->where('discount_amount', '>', 0)
            ->whereNotNull('discount_by')
            ->update([
                'discount_status' => 'approved',
                'discount_approved_by' => DB::raw('discount_by'),
                'discount_approved_at' => DB::raw('updated_at'),
            ]);
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['created_by_user_id']);
            $table->dropForeign(['performed_by_user_id']);
            $table->dropForeign(['discount_requested_by']);
            $table->dropForeign(['discount_approved_by']);
            $table->dropColumn([
                'created_by_user_id',
                'performed_by_user_id',
                'discount_status',
                'discount_requested_by',
                'discount_requested_at',
                'discount_approved_by',
                'discount_approved_at',
            ]);
        });
    }
};
