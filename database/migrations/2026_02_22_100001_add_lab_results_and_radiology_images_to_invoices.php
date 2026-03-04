<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Structured lab results: array of {test_name, result, unit, reference_range}
            $table->json('lab_results')->nullable()->after('report_text');

            // Radiology image paths stored as JSON array of filenames
            $table->json('radiology_images')->nullable()->after('lab_results');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['lab_results', 'radiology_images']);
        });
    }
};
