<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE vendor_price_lists MODIFY COLUMN status ENUM('pending','processing','extracted','applied','flagged','failed','rejected') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE vendor_price_lists MODIFY COLUMN status ENUM('pending','processing','extracted','applied','flagged','failed') NOT NULL DEFAULT 'pending'");
    }
};
