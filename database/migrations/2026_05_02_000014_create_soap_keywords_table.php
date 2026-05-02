<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('soap_keywords', function (Blueprint $table) {
            $table->id();
            $table->enum('section', ['S', 'O', 'A', 'P']);
            $table->string('display_text', 255);
            $table->string('canonical_key', 255);
            $table->string('specialty', 100)->nullable();
            // No FK — 0 is not valid; null = global, doctor id = doctor-specific.
            // Uniqueness is enforced at the application layer (MariaDB 10.4 treats
            // NULL != NULL in unique indexes, so a DB-level unique constraint that
            // includes a nullable column cannot prevent duplicate global rows).
            $table->unsignedBigInteger('doctor_id')->nullable();
            $table->unsignedInteger('usage_count')->default(0);
            $table->timestamps();

            $table->index(['section', 'doctor_id']);
            $table->index(['section', 'canonical_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('soap_keywords');
    }
};
