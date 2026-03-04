<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add database integrity constraints:
     * - Indexes on frequently queried columns
     * - NOT NULL constraints on required fields
     * - Foreign key constraints for referential integrity
     */
    public function up(): void
    {
        // Invoices table - Add indexes and constraints
        Schema::table('invoices', function (Blueprint $table) {
            // Status transitions are frequent queries
            if (!$this->indexExists('invoices', 'status')) {
                $table->index('status');
            }
            
            // Filtering by patient
            if (!$this->indexExists('invoices', 'patient_id')) {
                $table->index('patient_id');
            }
            
            // Filtering by department (lab/radiology/pharmacy)
            if (!$this->indexExists('invoices', 'department')) {
                $table->index('department');
            }
            
            // Filtering by doctor
            if (!$this->indexExists('invoices', 'prescribing_doctor_id')) {
                $table->index('prescribing_doctor_id');
            }
            
            // Temporal queries (recent invoices)
            if (!$this->indexExists('invoices', 'created_at')) {
                $table->index('created_at');
            }
            
            // Composite index for common queries: pending invoices by department
            if (!$this->indexExists('invoices', 'status_department')) {
                $table->index(['status', 'department'], 'status_department');
            }
        });

        // Stock Movements table - Add indexes for atomicity tracking
        Schema::table('stock_movements', function (Blueprint $table) {
            // Tracking stock for an item
            if (!$this->indexExists('stock_movements', 'inventory_item_id')) {
                $table->index('inventory_item_id');
            }
            
            // Temporal ordering (important for stock calculation)
            if (!$this->indexExists('stock_movements', 'created_at')) {
                $table->index('created_at');
            }
            
            // Reverse lookup from invoice
            if (!$this->indexExists('stock_movements', 'reference')) {
                $table->index(['reference_type', 'reference_id'], 'reference');
            }
            
            // Composite index for stock calculation: (item, type, created_at)
            if (!$this->indexExists('stock_movements', 'item_type_date')) {
                $table->index(['inventory_item_id', 'type', 'created_at'], 'item_type_date');
            }
        });

        // Inventory Items table - Add indexes
        Schema::table('inventory_items', function (Blueprint $table) {
            // Department-scoped queries
            if (!$this->indexExists('inventory_items', 'department')) {
                $table->index('department');
            }
            
            // Name searches
            if (!$this->indexExists('inventory_items', 'name')) {
                $table->index('name');
            }
        });

        // Patients table - Add indexes
        Schema::table('patients', function (Blueprint $table) {
            // Doctor lookups (doctor has many patients)
            if (!$this->indexExists('patients', 'doctor_id')) {
                $table->index('doctor_id');
            }
            
            // Status filtering (with_doctor, completed, etc)
            if (!$this->indexExists('patients', 'status')) {
                $table->index('status');
            }
            
            // Temporal queries
            if (!$this->indexExists('patients', 'created_at')) {
                $table->index('created_at');
            }
        });

        // Users table - Add indexes
        Schema::table('users', function (Blueprint $table) {
            // Active status filtering
            if (!$this->indexExists('users', 'is_active')) {
                $table->index('is_active');
            }
        });

        // Revenue Ledgers table - Add indexes for financial reports
        Schema::table('revenue_ledgers', function (Blueprint $table) {
            // Filtering by invoice
            if (!$this->indexExists('revenue_ledgers', 'invoice_id')) {
                $table->index('invoice_id');
            }
            
            // Filtering by user (commission calculations)
            if (!$this->indexExists('revenue_ledgers', 'user_id')) {
                $table->index('user_id');
            }
            
            // Filtering by payout
            if (!$this->indexExists('revenue_ledgers', 'payout_id')) {
                $table->index('payout_id');
            }
            
            // Temporal queries (revenue reports by date)
            if (!$this->indexExists('revenue_ledgers', 'created_at')) {
                $table->index('created_at');
            }
        });

        // Doctor Payouts table - Add indexes
        Schema::table('doctor_payouts', function (Blueprint $table) {
            // Filtering by doctor
            if (!$this->indexExists('doctor_payouts', 'doctor_id')) {
                $table->index('doctor_id');
            }
            
            // Status filtering (pending, approved, paid)
            if (!$this->indexExists('doctor_payouts', 'status')) {
                $table->index('status');
            }
            
            // Temporal queries
            if (!$this->indexExists('doctor_payouts', 'created_at')) {
                $table->index('created_at');
            }
        });

        // Doctor Contracts table - Add indexes
        Schema::table('doctor_contracts', function (Blueprint $table) {
            // Filtering by doctor
            if (!$this->indexExists('doctor_contracts', 'doctor_id')) {
                $table->index('doctor_id');
            }
            
            // Active contract lookup
            if (!$this->indexExists('doctor_contracts', 'status')) {
                $table->index('status');
            }
        });
    }

    /**
     * Check if an index exists on a table.
     */
    private function indexExists(string $table, string $indexName): bool
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            // SQLite: Check sqlite_master table
            $indexes = DB::select("SELECT name FROM sqlite_master WHERE type='index' AND tbl_name=?", [$table]);
            foreach ($indexes as $index) {
                if ($index->name === $table . '_' . $indexName . '_index' || 
                    $index->name === $indexName ||
                    strpos($index->name, $indexName) !== false) {
                    return true;
                }
            }
            return false;
        } else {
            // MySQL/PostgreSQL: Use information schema
            $result = DB::select("SELECT COUNT(*) as count FROM information_schema.STATISTICS WHERE table_schema=? AND table_name=? AND index_name=?", 
                [DB::connection()->getDatabaseName(), $table, $table . '_' . $indexName . '_index']);
            return (count($result) > 0 && $result[0]->count > 0);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop indexes from invoices - only if they exist
        Schema::table('invoices', function (Blueprint $table) {
            if ($this->indexExists('invoices', 'status')) {
                $table->dropIndex(['status']);
            }
            if ($this->indexExists('invoices', 'patient_id')) {
                $table->dropIndex(['patient_id']);
            }
            if ($this->indexExists('invoices', 'department')) {
                $table->dropIndex(['department']);
            }
            if ($this->indexExists('invoices', 'prescribing_doctor_id')) {
                $table->dropIndex(['prescribing_doctor_id']);
            }
            if ($this->indexExists('invoices', 'created_at')) {
                $table->dropIndex(['created_at']);
            }
            if ($this->indexExists('invoices', 'status_department')) {
                $table->dropIndex('status_department');
            }
        });

        // Drop indexes from stock_movements
        Schema::table('stock_movements', function (Blueprint $table) {
            if ($this->indexExists('stock_movements', 'inventory_item_id')) {
                $table->dropIndex(['inventory_item_id']);
            }
            if ($this->indexExists('stock_movements', 'created_at')) {
                $table->dropIndex(['created_at']);
            }
            if ($this->indexExists('stock_movements', 'reference')) {
                $table->dropIndex('reference');
            }
            if ($this->indexExists('stock_movements', 'item_type_date')) {
                $table->dropIndex('item_type_date');
            }
        });

        // Drop indexes from inventory_items
        Schema::table('inventory_items', function (Blueprint $table) {
            if ($this->indexExists('inventory_items', 'department')) {
                $table->dropIndex(['department']);
            }
            if ($this->indexExists('inventory_items', 'name')) {
                $table->dropIndex(['name']);
            }
        });

        // Drop indexes from patients
        Schema::table('patients', function (Blueprint $table) {
            if ($this->indexExists('patients', 'doctor_id')) {
                $table->dropIndex(['doctor_id']);
            }
            if ($this->indexExists('patients', 'status')) {
                $table->dropIndex(['status']);
            }
            if ($this->indexExists('patients', 'created_at')) {
                $table->dropIndex(['created_at']);
            }
        });

        // Drop indexes from users
        Schema::table('users', function (Blueprint $table) {
            if ($this->indexExists('users', 'is_active')) {
                $table->dropIndex(['is_active']);
            }
        });

        // Drop indexes from revenue_ledgers
        Schema::table('revenue_ledgers', function (Blueprint $table) {
            if ($this->indexExists('revenue_ledgers', 'invoice_id')) {
                $table->dropIndex(['invoice_id']);
            }
            if ($this->indexExists('revenue_ledgers', 'user_id')) {
                $table->dropIndex(['user_id']);
            }
            if ($this->indexExists('revenue_ledgers', 'payout_id')) {
                $table->dropIndex(['payout_id']);
            }
            if ($this->indexExists('revenue_ledgers', 'created_at')) {
                $table->dropIndex(['created_at']);
            }
        });

        // Drop indexes from doctor_payouts
        Schema::table('doctor_payouts', function (Blueprint $table) {
            if ($this->indexExists('doctor_payouts', 'doctor_id')) {
                $table->dropIndex(['doctor_id']);
            }
            if ($this->indexExists('doctor_payouts', 'status')) {
                $table->dropIndex(['status']);
            }
            if ($this->indexExists('doctor_payouts', 'created_at')) {
                $table->dropIndex(['created_at']);
            }
        });

        // Drop indexes from doctor_contracts
        Schema::table('doctor_contracts', function (Blueprint $table) {
            if ($this->indexExists('doctor_contracts', 'doctor_id')) {
                $table->dropIndex(['doctor_id']);
            }
            if ($this->indexExists('doctor_contracts', 'status')) {
                $table->dropIndex(['status']);
            }
        });
    }
};
