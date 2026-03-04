<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_compensations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('department'); // pharmacy, laboratory, radiology, consultation
            $table->enum('compensation_type', ['commission', 'fixed']);
            $table->decimal('percentage', 5, 2)->nullable(); // for commission type
            $table->decimal('fixed_amount', 12, 2)->nullable(); // for fixed salary type
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'department']);
            $table->index('department');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_compensations');
    }
};
