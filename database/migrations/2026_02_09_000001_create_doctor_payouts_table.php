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
        Schema::create('doctor_payouts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('doctor_id');
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('total_amount', 15, 2);
            $table->decimal('paid_amount', 15, 2);
            $table->enum('status', ['pending', 'confirmed'])->default('pending');
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('confirmed_by')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->unsignedBigInteger('correction_of_id')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('doctor_id')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('confirmed_by')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('correction_of_id')->references('id')->on('doctor_payouts')->onDelete('restrict');

            // Indexes
            $table->index('doctor_id');
            $table->index('status');
            $table->index('period_start');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doctor_payouts');
    }
};
