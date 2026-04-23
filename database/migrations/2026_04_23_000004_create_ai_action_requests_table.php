<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_action_requests', function (Blueprint $table) {
            $table->id();
            $table->char('case_token', 64)->nullable()->index();
            $table->string('requested_by_source');
            $table->string('target_type');
            $table->unsignedBigInteger('target_id');
            $table->string('proposed_action');
            $table->json('proposed_payload');
            $table->enum('status', ['pending', 'approved', 'rejected', 'expired'])
                  ->default('pending')
                  ->index();
            $table->foreignId('approver_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('decided_at')->nullable();
            $table->index(['status', 'created_at']);
            $table->index(['target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_action_requests');
    }
};
