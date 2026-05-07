<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 10B — Doctor availability slots for scheduling.
     */
    public function up(): void
    {
        Schema::create('doctor_availability_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('room_id')->nullable();
            $table->date('date')->nullable()->comment('Null means recurring slot');
            $table->tinyInteger('day_of_week')->nullable()->comment('0=Sun,1=Mon...6=Sat — for recurring slots');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('slot_duration_mins')->default(30);
            $table->boolean('is_recurring')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('room_id')
                  ->references('id')
                  ->on('clinic_rooms')
                  ->nullOnDelete();

            $table->index(['doctor_id', 'date']);
            $table->index(['doctor_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_availability_slots');
    }
};
