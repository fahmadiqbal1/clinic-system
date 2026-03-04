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
        Schema::create('commission_configs', function (Blueprint $table) {
            $table->id();
            $table->enum('department', ['lab', 'radiology', 'pharmacy', 'consultation'])->unique();
            $table->decimal('owner_percentage', 5, 2);
            $table->decimal('staff_percentage', 5, 2)->nullable();
            $table->decimal('doctor_percentage', 5, 2)->nullable();
            $table->boolean('allow_referrer')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commission_configs');
    }
};
