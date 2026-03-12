<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->string('batch_number')->nullable()->after('unit_cost');
            $table->date('expiry_date')->nullable()->after('batch_number');
            $table->string('manufacturer')->nullable()->after('expiry_date');
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropColumn(['batch_number', 'expiry_date', 'manufacturer']);
        });
    }
};
