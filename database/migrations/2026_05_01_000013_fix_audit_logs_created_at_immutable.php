<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Remove ON UPDATE CURRENT_TIMESTAMP so the hash chain's created_at stays immutable.
        // MySQL auto-adds this to the first TIMESTAMP column; it must be removed explicitly.
        DB::statement('ALTER TABLE audit_logs MODIFY COLUMN created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP');
    }

    public function down(): void
    {
        // Intentionally not restoring ON UPDATE — it breaks the hash chain.
    }
};
