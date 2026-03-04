<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->decimal('line_cogs', 10, 2)->default(0)->after('line_total');
        });

        // Backfill: line_cogs = cost_price * quantity
        DB::table('invoice_items')->update([
            'line_cogs' => DB::raw('cost_price * quantity'),
        ]);
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn('line_cogs');
        });
    }
};
