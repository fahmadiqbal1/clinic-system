<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->string('cnic', 15)->nullable()->after('phone')
                  ->comment('Computerised National Identity Card number (e.g. 12345-1234567-1) — required for FBR buyer identification');
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn('cnic');
        });
    }
};
