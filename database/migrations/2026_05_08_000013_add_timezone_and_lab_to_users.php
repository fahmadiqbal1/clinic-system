<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('timezone', 60)->default('Asia/Karachi')->after('specialty');
            $table->foreignId('external_lab_id')->nullable()->constrained('external_labs')->nullOnDelete()->after('timezone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['external_lab_id']);
            $table->dropColumn(['timezone', 'external_lab_id']);
        });
    }
};
