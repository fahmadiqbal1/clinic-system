<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinic_rooms', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->enum('type', ['gp', 'consultant', 'dental', 'aesthetics', 'procedure', 'other'])->default('other');
            $table->string('specialty')->nullable();
            $table->text('equipment_notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_rooms');
    }
};
