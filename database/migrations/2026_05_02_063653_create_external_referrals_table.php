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
        Schema::create('external_referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('external_lab_id')->constrained('external_labs');
            $table->foreignId('referred_by_user_id')->constrained('users');
            $table->foreignId('approved_by_id')->nullable()->constrained('users');
            $table->string('test_name');
            $table->string('department')->default('lab');
            $table->text('clinical_notes')->nullable();
            $table->string('reason')->nullable();
            $table->decimal('patient_price', 10, 2)->nullable();
            $table->decimal('commission_pct', 5, 2)->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'sent', 'completed'])->default('pending');
            $table->text('owner_notes')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'department']);
            $table->index('patient_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('external_referrals');
    }
};
