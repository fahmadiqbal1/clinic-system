<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add service_catalog_id to invoices for linking to the catalog
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('service_catalog_id')->nullable()->after('service_name')
                ->constrained('service_catalog')->nullOnDelete();
            $table->foreignId('prescription_id')->nullable()->after('service_catalog_id')
                ->constrained('prescriptions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['service_catalog_id']);
            $table->dropForeign(['prescription_id']);
            $table->dropColumn(['service_catalog_id', 'prescription_id']);
        });
    }
};
