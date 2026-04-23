<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_invocations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->char('case_token', 64)->nullable();
            $table->string('endpoint', 64);
            $table->char('prompt_hash', 64);
            $table->json('retrieval_doc_ids')->nullable();
            $table->string('model_id', 128)->nullable();
            $table->unsignedInteger('latency_ms');
            $table->string('outcome', 16); // ok | error | open
            $table->char('prev_hash', 64)->nullable();
            $table->char('row_hash', 64)->default('');
            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at');
            $table->index(['endpoint', 'created_at']);
            $table->index('case_token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_invocations');
    }
};
