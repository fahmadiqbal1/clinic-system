<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('visit_id')->nullable()->after('patient_id');
        });
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->unsignedBigInteger('visit_id')->nullable()->after('patient_id');
        });
        Schema::table('triage_vitals', function (Blueprint $table) {
            $table->unsignedBigInteger('visit_id')->nullable()->after('patient_id');
        });
    }
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('visit_id');
        });
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->dropColumn('visit_id');
        });
        Schema::table('triage_vitals', function (Blueprint $table) {
            $table->dropColumn('visit_id');
        });
    }
};
