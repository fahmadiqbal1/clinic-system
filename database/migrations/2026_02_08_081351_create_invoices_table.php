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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->nullable()->constrained('patients')->cascadeOnDelete();
            $table->enum('patient_type', ['clinic', 'walk_in'])->default('clinic');
            $table->enum('department', ['lab', 'radiology', 'pharmacy', 'consultation']);
            $table->string('service_name');
            $table->decimal('total_amount', 10, 2);
            $table->foreignId('prescribing_doctor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('referrer_name')->nullable();
            $table->decimal('referrer_percentage', 5, 2)->nullable();
            $table->enum('status', ['unpaid', 'paid'])->default('unpaid');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
