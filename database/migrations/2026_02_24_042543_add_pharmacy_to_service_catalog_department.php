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
            DB::statement("ALTER TABLE service_catalog MODIFY COLUMN department ENUM('lab','radiology','consultation','pharmacy')");
        }
        // SQLite uses TEXT columns with no enum constraint, so no change needed
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE service_catalog MODIFY COLUMN department ENUM('lab','radiology','consultation')");
        }
    }
};
