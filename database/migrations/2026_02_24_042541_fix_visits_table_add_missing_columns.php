<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            if (!Schema::hasColumn('visits', 'consultation_fee_invoice_id')) {
                $table->unsignedBigInteger('consultation_fee_invoice_id')->nullable()->after('status');
            }
            if (!Schema::hasColumn('visits', 'consultation_notes')) {
                $table->text('consultation_notes')->nullable();
            }
            if (!Schema::hasColumn('visits', 'registered_at')) {
                $table->timestamp('registered_at')->nullable();
            }
            if (!Schema::hasColumn('visits', 'triage_started_at')) {
                $table->timestamp('triage_started_at')->nullable();
            }
            if (!Schema::hasColumn('visits', 'doctor_started_at')) {
                $table->timestamp('doctor_started_at')->nullable();
            }
            if (!Schema::hasColumn('visits', 'completed_at')) {
                $table->timestamp('completed_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $columns = ['consultation_fee_invoice_id', 'consultation_notes', 'registered_at', 'triage_started_at', 'doctor_started_at', 'completed_at'];
            foreach ($columns as $col) {
                if (Schema::hasColumn('visits', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
