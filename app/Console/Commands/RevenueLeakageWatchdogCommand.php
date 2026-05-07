<?php

namespace App\Console\Commands;

use App\Models\AiActionRequest;
use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\GenericOwnerAlert;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Autonomous revenue leakage watchdog.
 *
 * Detects consultations where an AI analysis was run (meaning a doctor completed
 * the visit) but no consultation line item appears on the linked invoice.
 * These are potential unbilled services — the most common source of revenue
 * leakage in busy outpatient departments.
 *
 * Logic:
 *   1. Find ai_analyses rows in the last N days that have an invoice_id
 *   2. LEFT JOIN invoice_items where item_type = 'consultation'
 *   3. Flag any where no consultation item exists on that invoice
 *
 * Results are written to ai_action_requests (owner reviews in the dashboard)
 * and a digest notification is sent.
 *
 * Schedule: daily at 07:30 (after overnight AI monitor, before clinic opens).
 * Usage: php artisan revenue:leakage-watchdog [--days=7]
 */
class RevenueLeakageWatchdogCommand extends Command
{
    protected $signature   = 'revenue:leakage-watchdog {--days=7 : Look-back window in days}';
    protected $description = 'Detect consultations with AI analyses but no billing line item (unbilled services).';

    public function handle(): int
    {
        $days = (int) $this->option('days');

        $leaks = DB::select(
            "SELECT aa.id AS analysis_id, aa.invoice_id, aa.patient_id,
                    aa.created_at AS analysis_at,
                    i.department,
                    i.status AS invoice_status
             FROM ai_analyses aa
             INNER JOIN invoices i ON i.id = aa.invoice_id
             LEFT JOIN invoice_items ii
                    ON ii.invoice_id = aa.invoice_id
                   AND ii.item_type = 'consultation'
             WHERE aa.created_at >= NOW() - INTERVAL ? DAY
               AND aa.invoice_id IS NOT NULL
               AND ii.id IS NULL
               AND aa.deleted_at IS NULL
             ORDER BY aa.created_at DESC
             LIMIT 50",
            [$days]
        );

        $count = count($leaks);

        AuditLog::log('revenue.leakage_watchdog_run', 'AiAnalysis', 0, null, [
            'days'       => $days,
            'leaks_found' => $count,
        ]);

        if ($count > 0) {
            // Write a single ai_action_request for the owner dashboard
            AiActionRequest::create([
                'requested_by_source' => 'revenue_leakage_watchdog',
                'target_type'         => 'Invoice',
                'target_id'           => 0,
                'proposed_action'     => 'review_unbilled_consultations',
                'proposed_payload'    => [
                    'period_days'   => $days,
                    'leaks_count'   => $count,
                    'invoice_ids'   => array_column($leaks, 'invoice_id'),
                    'run_at'        => now()->toIso8601String(),
                ],
                'status'     => 'pending',
                'created_at' => now(),
            ]);

            $message = "Revenue Leakage: {$count} consultation(s) in the last {$days} day(s) "
                     . "have an AI analysis but no billing line item. "
                     . "Potential unbilled services — review at /owner/revenue-ledger.";

            User::role('Owner')->get()->each(
                fn (User $owner) => $owner->notify(
                    new GenericOwnerAlert($message, 'bi-currency-dollar', 'danger', '/owner/revenue-ledger', 'Revenue Leakage Alert')
                )
            );
        }

        $this->info("Leakage watchdog complete — period: {$days}d, unbilled consultations: {$count}.");

        return self::SUCCESS;
    }
}
