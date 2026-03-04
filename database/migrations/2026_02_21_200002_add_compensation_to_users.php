<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('compensation_type', 20)->default('commission')->after('is_active');
            $table->decimal('base_salary', 10, 2)->nullable()->after('compensation_type');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['compensation_type', 'base_salary']);
        });
    }
};
