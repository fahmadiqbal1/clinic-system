<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\User;
use App\Notifications\GenericOwnerAlert;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Autonomous FBR compliance watchdog.
 *
 * Before each nightly FBR batch, this command scans all paid invoices that
 * are not yet submitted to FBR and quarantines any that fail validation:
 *   - Missing patient CNIC (required for FBR since Jan 2025)
 *   - Zero or negative net amount
 *   - Missing department code
 *
 * Quarantined invoices have fbr_status set to 'quarantined' so the FbrService
 * skips them. The owner receives a single digest notification listing the count.
 *
 * Schedule: daily at 22:00 (before the overnight FBR batch submission).
 * Usage: php artisan fbr:watchdog [--dry-run]
 */
class FbrWatchdogCommand extends Command
{
    protected $signature   = 'fbr:watchdog {--dry-run : Report issues without quarantining}';
    protected $description = 'Validate paid invoices before FBR submission; quarantine non-compliant ones.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // Paid invoices not yet submitted or quarantined
        $candidates = Invoice::where('status', 'paid')
            ->whereIn('fbr_status', ['not_submitted', null, ''])
            ->select(['id', 'patient_id', 'net_amount', 'department', 'fbr_status'])
            ->with('patient:id,cnic')
            ->get();

        $quarantined = [];
        $reasons     = [];

        foreach ($candidates as $invoice) {
            $issues = $this->validate($invoice);
            if (empty($issues)) {
                continue;
            }

            $quarantined[] = $invoice->id;
            $reasons[$invoice->id] = $issues;

            if (! $dryRun) {
                $invoice->update(['fbr_status' => 'quarantined']);
            }
        }

        $count = count($quarantined);

        AuditLog::log('fbr.watchdog_run', 'Invoice', 0, null, [
            'candidates'   => $candidates->count(),
            'quarantined'  => $count,
            'dry_run'      => $dryRun,
            'invoice_ids'  => array_slice($quarantined, 0, 20),
        ]);

        if ($count > 0) {
            $action  = $dryRun ? 'would quarantine' : 'quarantined';
            $message = "FBR Watchdog: {$action} {$count} invoice(s) for non-compliance "
                     . "(missing CNIC, zero amount, or missing department). "
                     . "Review at /owner/fbr-settings before tonight's FBR batch.";

            User::role('Owner')->get()->each(
                fn (User $owner) => $owner->notify(
                    new GenericOwnerAlert($message, 'bi-shield-exclamation', 'warning', '/owner/fbr-settings', 'FBR Compliance Watchdog')
                )
            );

            if ($this->getOutput()->isVerbose()) {
                foreach ($reasons as $id => $issues) {
                    $this->warn("Invoice #{$id}: " . implode(', ', $issues));
                }
            }
        }

        $prefix = $dryRun ? '[dry-run] ' : '';
        $this->info("{$prefix}FBR watchdog complete — candidates: {$candidates->count()}, quarantined: {$count}.");

        return self::SUCCESS;
    }

    private function validate(Invoice $invoice): array
    {
        $issues = [];

        $cnic = $invoice->patient?->cnic;
        if (empty($cnic) || strlen(preg_replace('/\D/', '', $cnic)) < 13) {
            $issues[] = 'missing_cnic';
        }

        if (($invoice->net_amount ?? 0) <= 0) {
            $issues[] = 'zero_or_negative_amount';
        }

        if (empty($invoice->department)) {
            $issues[] = 'missing_department';
        }

        return $issues;
    }
}
