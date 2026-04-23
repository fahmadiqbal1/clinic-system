<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->string('barcode', 100)->nullable()->after('sku');
            $table->unique('barcode');
            $table->index('barcode');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropUnique(['barcode']);
            $table->dropIndex(['barcode']);
            $table->dropColumn('barcode');
        });
    }
};
