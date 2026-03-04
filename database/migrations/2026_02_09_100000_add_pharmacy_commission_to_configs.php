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
        Schema::table('commission_configs', function (Blueprint $table) {
            // Add pharmacy-specific commission fields
            $table->decimal('pharmacist_percentage', 5, 2)->nullable()->after('staff_percentage');
            $table->decimal('doctor_pharmacy_commission', 5, 2)->nullable()->after('pharmacist_percentage');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('commission_configs', function (Blueprint $table) {
            $table->dropColumn(['pharmacist_percentage', 'doctor_pharmacy_commission']);
        });
    }
};
