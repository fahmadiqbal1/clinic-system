<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE triage_vitals MODIFY COLUMN priority ENUM('low','normal','high','urgent','critical','emergency') DEFAULT 'normal'");
        }
        // SQLite uses TEXT columns with no enum constraint, so no change needed
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE triage_vitals MODIFY COLUMN priority ENUM('normal','urgent','emergency') DEFAULT 'normal'");
        }
    }
};
