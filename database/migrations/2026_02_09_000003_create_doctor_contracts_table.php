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
        Schema::create('doctor_contracts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('doctor_id');
            $table->integer('version')->default(1);
            $table->longText('contract_html_snapshot');
            $table->integer('minimum_term_months')->default(12);
            $table->date('effective_from');
            $table->enum('status', ['draft', 'active', 'superseded'])->default('draft');
            $table->timestamp('signed_at')->nullable();
            $table->string('signed_ip')->nullable();
            $table->text('signed_user_agent')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamp('resignation_notice_submitted_at')->nullable();
            $table->boolean('early_exit_flag')->default(false);
            $table->timestamps();

            // Foreign keys
            $table->foreign('doctor_id')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');

            // Indexes
            $table->index('doctor_id');
            $table->index('status');
            $table->unique(['doctor_id', 'version']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doctor_contracts');
    }
};
