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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action'); // e.g., 'invoice_status_changed', 'consultation_notes_saved', etc.
            $table->string('auditable_type'); // Model class name
            $table->unsignedBigInteger('auditable_id');
            $table->json('before_state')->nullable(); // State before change
            $table->json('after_state')->nullable(); // State after change
            $table->string('ip_address')->nullable();
            $table->timestamp('created_at')->useCurrent(); // no ON UPDATE — hash chain depends on immutable created_at

            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['user_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
