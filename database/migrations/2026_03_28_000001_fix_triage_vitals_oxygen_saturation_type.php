<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('triage_vitals', function (Blueprint $table) {
            // Change integer to decimal(4,1) to support values like 98.5
            $table->decimal('oxygen_saturation', 4, 1)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('triage_vitals', function (Blueprint $table) {
            $table->integer('oxygen_saturation')->nullable()->change();
        });
    }
};
