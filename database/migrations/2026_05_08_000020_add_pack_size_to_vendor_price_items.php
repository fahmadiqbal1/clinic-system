<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add pack_size to vendor_price_items
        Schema::table('vendor_price_items', function (Blueprint $table) {
            $table->string('pack_size')->nullable()->after('sku_detected');
        });

        // Extend vendor_price_lists.status enum to include pending_sidecar
        DB::statement("ALTER TABLE vendor_price_lists MODIFY COLUMN status ENUM(
            'pending','processing','extracted','applied','flagged','failed','pending_sidecar'
        ) NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        Schema::table('vendor_price_items', function (Blueprint $table) {
            $table->dropColumn('pack_size');
        });

        DB::statement("ALTER TABLE vendor_price_lists MODIFY COLUMN status ENUM(
            'pending','processing','extracted','applied','flagged','failed'
        ) NOT NULL DEFAULT 'pending'");
    }
};
