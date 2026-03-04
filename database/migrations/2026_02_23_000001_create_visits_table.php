<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('doctor_id')->nullable();
            $table->unsignedBigInteger('triage_nurse_id')->nullable();
            $table->dateTime('visit_date');
            $table->enum('status', ['registered', 'triage', 'with_doctor', 'completed', 'cancelled'])->default('registered');
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('visits');
    }
};
