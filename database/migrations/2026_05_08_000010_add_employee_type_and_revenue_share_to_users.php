<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('employee_type', ['gp', 'specialist', 'staff'])->default('staff')->after('compensation_type');
            $table->boolean('revenue_share_enabled')->default(false)->after('base_salary');
            $table->decimal('revenue_share_percentage', 5, 2)->default(2.00)->after('revenue_share_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['employee_type', 'revenue_share_enabled', 'revenue_share_percentage']);
        });
    }
};
