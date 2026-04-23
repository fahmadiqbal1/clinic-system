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
        Schema::table('patients', function (Blueprint $table) {
            $table->text('cnic')->nullable()->change();
        });

        // chief_complaint is encrypted but stored as varchar(255) — expand to text
        Schema::table('triage_vitals', function (Blueprint $table) {
            $table->text('chief_complaint')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Cannot safely revert encrypted columns without data loss
    }
};
