<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds composite indexes for common query patterns identified
     * during architectural audit (department-first work queues,
     * discount approval queue, financial reporting).
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Department work queues (Lab/Radiology/Pharmacy filter by department first)
            $table->index(['department', 'status', 'created_at'], 'idx_invoices_dept_status_created');

            // Discount approval queue on Owner dashboard
            $table->index(['discount_status', 'created_at'], 'idx_invoices_discount_status');

            // Performer lookup for commission calculations
            $table->index('performed_by_user_id', 'idx_invoices_performer');

            // Patient invoice history (patient detail views)
            $table->index(['patient_id', 'created_at'], 'idx_invoices_patient_created');

            // Financial reporting by payment date
            $table->index(['paid_at', 'department'], 'idx_invoices_paid_dept');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('idx_invoices_dept_status_created');
            $table->dropIndex('idx_invoices_discount_status');
            $table->dropIndex('idx_invoices_performer');
            $table->dropIndex('idx_invoices_patient_created');
            $table->dropIndex('idx_invoices_paid_dept');
        });
    }
};
