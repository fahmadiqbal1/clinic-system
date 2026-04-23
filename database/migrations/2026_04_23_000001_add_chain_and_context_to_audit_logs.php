<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->char('prev_hash', 64)->nullable()->after('ip_address');
            $table->char('row_hash', 64)->notNull()->default('')->after('prev_hash');
            $table->string('user_agent', 512)->nullable()->after('row_hash');
            $table->string('session_id', 64)->nullable()->after('user_agent');
            $table->index('row_hash', 'audit_logs_row_hash_index');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('audit_logs_row_hash_index');
            $table->dropColumn(['prev_hash', 'row_hash', 'user_agent', 'session_id']);
        });
    }
};
