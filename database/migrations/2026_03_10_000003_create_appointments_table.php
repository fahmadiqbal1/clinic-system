<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the appointments table for scheduling system.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->onDelete('cascade');
            $table->foreignId('doctor_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('booked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('scheduled_at');
            $table->dateTime('ended_at')->nullable();
            $table->enum('type', ['first_visit', 'follow_up', 'consultation', 'procedure', 'emergency'])->default('consultation');
            $table->enum('status', ['scheduled', 'confirmed', 'in_progress', 'completed', 'cancelled', 'no_show'])->default('scheduled');
            $table->string('reason', 500)->nullable();
            $table->text('notes')->nullable();
            $table->string('cancellation_reason', 500)->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('reminder_sent')->default(false);
            $table->timestamp('reminder_sent_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes for common queries
            $table->index('scheduled_at');
            $table->index(['doctor_id', 'scheduled_at']);
            $table->index(['patient_id', 'scheduled_at']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
