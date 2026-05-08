<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetClinicData extends Command
{
    protected $signature   = 'clinic:reset-data {--force : Skip confirmation prompt}';
    protected $description = 'Wipe all operational/clinical data while preserving configuration and intelligence.';

    public function handle(): int
    {
        if (! $this->option('force')) {
            $this->warn('This will permanently delete ALL patients, invoices, prescriptions, visits,');
            $this->warn('appointments, expenses, payouts, audit logs, and stock movements.');
            $this->newLine();
            $this->info('Preserved: users, roles, service catalog, inventory catalog, vendors,');
            $this->info('           price lists, platform settings, rooms, contracts, credentials.');
            $this->newLine();

            if (! $this->confirm('Are you absolutely sure you want to reset all operational data?')) {
                $this->line('Aborted — nothing was changed.');
                return self::SUCCESS;
            }

            if (! $this->confirm('FINAL CONFIRMATION: this cannot be undone. Proceed?')) {
                $this->line('Aborted — nothing was changed.');
                return self::SUCCESS;
            }
        }

        $this->info('Resetting operational data…');

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        $tables = [
            // Clinical
            'triage_vitals',
            'prescription_items',
            'prescriptions',
            'visits',
            'patient_checkins',
            'appointments',
            'patients',

            // Financial transactions
            'invoice_items',
            'invoices',
            'revenue_ledgers',
            'expenses',
            'doctor_payouts',
            'zakat_transactions',
            'procurement_request_items',
            'procurement_requests',

            // Inventory stock levels (keep catalog)
            'stock_movements',

            // AI runtime & audit trail
            'ai_invocations',
            'ai_action_requests',
            'ai_analyses',
            'case_tokens',
            'soap_keywords',
            'audit_logs',

            // Attendance
            'staff_shifts',

            // Laravel internals
            'notifications',
            'jobs',
            'failed_jobs',
            'job_batches',
            'sessions',
            'cache',
            'cache_locks',
        ];

        foreach ($tables as $table) {
            if (DB::getSchemaBuilder()->hasTable($table)) {
                DB::table($table)->truncate();
                $this->line("  <fg=green>✓</> Truncated <fg=yellow>{$table}</>");
            } else {
                $this->line("  <fg=gray>– Skipped {$table} (table not found)</>");
            }
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        // Re-activate all inventory items deactivated during testing
        $reactivated = DB::table('inventory_items')->where('is_active', false)->update(['is_active' => true]);
        if ($reactivated > 0) {
            $this->line("  <fg=green>✓</> Re-activated {$reactivated} inventory item(s)");
        }

        // Reset inventory item stock to 0 (movements were wiped)
        // Items remain in catalog — just quantities are unknown, start fresh
        $this->newLine();
        $this->info('Done. All operational data has been wiped.');
        $this->info('Inventory catalog preserved (' . DB::table('inventory_items')->count() . ' items). Upload new stock via procurement or stock movements.');
        $this->newLine();
        $this->comment('Next steps:');
        $this->comment('  1. Add real patients and start booking appointments');
        $this->comment('  2. Upload fresh vendor price lists to update stock prices');
        $this->comment('  3. Run initial stock intake via Procurement → Receive Stock');

        return self::SUCCESS;
    }
}
