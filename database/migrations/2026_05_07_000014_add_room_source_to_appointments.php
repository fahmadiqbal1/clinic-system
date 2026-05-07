<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 10B — Add room_id, source, and pre-booking fields to appointments.
     */
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->unsignedBigInteger('room_id')->nullable()->after('doctor_id');
            $table->enum('source', ['walk_in', 'phone', 'online', 'omnidimension'])
                  ->default('walk_in')
                  ->after('room_id');
            $table->string('pre_booked_name')->nullable()->after('source');
            $table->string('pre_booked_phone')->nullable()->after('pre_booked_name');

            $table->foreign('room_id')
                  ->references('id')
                  ->on('clinic_rooms')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['room_id']);
            $table->dropColumn(['room_id', 'source', 'pre_booked_name', 'pre_booked_phone']);
        });
    }
};
