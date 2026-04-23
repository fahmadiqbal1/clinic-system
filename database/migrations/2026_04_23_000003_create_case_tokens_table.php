<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_tokens', function (Blueprint $table) {
            $table->char('token', 64)->primary();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index('patient_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_tokens');
    }
};
