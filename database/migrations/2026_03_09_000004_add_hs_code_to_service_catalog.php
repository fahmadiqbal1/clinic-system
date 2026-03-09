<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_catalog', function (Blueprint $table) {
            $table->string('hs_code', 20)->nullable()->after('code')
                  ->comment('Harmonized System code for FBR IRIS submission (e.g. 9018.90.10 for medical services)');
        });
    }

    public function down(): void
    {
        Schema::table('service_catalog', function (Blueprint $table) {
            $table->dropColumn('hs_code');
        });
    }
};
