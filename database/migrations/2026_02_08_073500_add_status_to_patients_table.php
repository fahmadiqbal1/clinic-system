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
        Schema::table('patients', function (Blueprint $table) {
            $table->string('status')->default('registered');
            $table->timestamp('registered_at')->nullable();
            $table->timestamp('triage_started_at')->nullable();
            $table->timestamp('doctor_started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn(['status', 'registered_at', 'triage_started_at', 'doctor_started_at', 'completed_at']);
        });
    }
};
