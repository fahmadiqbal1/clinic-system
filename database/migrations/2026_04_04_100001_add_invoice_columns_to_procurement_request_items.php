<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_request_items', function (Blueprint $table) {
            $table->integer('quantity_invoiced')->nullable()->after('quoted_unit_price');
            $table->decimal('unit_price_invoiced', 10, 2)->nullable()->after('quantity_invoiced');
        });
    }

    public function down(): void
    {
        Schema::table('procurement_request_items', function (Blueprint $table) {
            $table->dropColumn(['quantity_invoiced', 'unit_price_invoiced']);
        });
    }
};
