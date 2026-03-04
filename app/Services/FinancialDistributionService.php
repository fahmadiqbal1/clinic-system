<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\CommissionConfig;
use App\Models\Invoice;
use App\Models\RevenueLedger;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class FinancialDistributionService
{
    /**
     * Distribute profit for a paid invoice.
     * Unified actor-based engine — ONE algorithm for ALL departments.
     * Commission is based on who PERFORMED the service, not scanned from all configs.
     * All departments use profit = revenue − COGS.
     * Zakat is NOT part of per-invoice distribution (period-based accounting).
     *
     * @throws InvalidArgumentException if invoice is invalid or misconfigured
     * @throws RuntimeException if distribution integrity fails
     */
    public function distribute(Invoice $invoice): void
    {
        // Idempotency guard: prevent double distribution
        if ($invoice->revenueLedgers()->exists()) {
            return;
        }

        // Must be paid
        if ($invoice->status !== Invoice::STATUS_PAID) {
            throw new InvalidArgumentException('Commission distribution requires a fully paid invoice.');
        }

        $effectiveRevenue = $invoice->effective_revenue;

        if ($effectiveRevenue <= 0) {
            throw new InvalidArgumentException('Invoice must have positive net revenue after discount.');
        }

        DB::transaction(function () use ($invoice) {
            // Lock invoice row to prevent concurrent distribution
            $locked = Invoice::where('id', $invoice->id)->lockForUpdate()->first();

            // Re-check idempotency inside transaction
            if ($locked->revenueLedgers()->exists()) {
                return;
            }

            $entries = $this->calculateDistribution($invoice);

            $this->validateBalance($entries, $invoice);

            // Snapshot distribution — frozen at payment time, never recalculated
            $this->storeDistributionSnapshot($invoice, $entries);

            $this->createBalancedLedgerEntries($invoice, $entries);
        });
    }

    /**
     * UNIFIED distribution calculation for ALL departments.
     *
     * Algorithm:
     *   1. Revenue credit = effective_revenue
     *   2. COGS debit (if any items exist)
     *   3. Profit = revenue − COGS
     *   4. If profit ≤ 0: no commissions, owner absorbs remainder
     *   5. If profit > 0:
     *      a. Performer commission: performed_by_user_id with compensation_type check
     *      b. Doctor referral commission: prescribing_doctor_id (non-consultation only)
     *      c. External referrer commission (if referrer_name exists)
     *      d. Validate sum ≤ 100%
     *      e. Owner absorbs remainder of profit
     */
    public function calculateDistribution(Invoice $invoice): array
    {
        $revenue = $invoice->effective_revenue;
        $cogs = $invoice->total_cogs;
        $profit = $revenue - $cogs;
        $department = $invoice->department;
        $serviceType = $department;

        $entries = [];

        // ─── 1. Revenue credit ───
        $entries[] = [
            'role_type' => 'Revenue',
            'entry_type' => 'credit',
            'category' => 'revenue',
            'user_id' => null,
            'percentage' => 100,
            'amount' => $revenue,
        ];

        // ─── 2. COGS debit (if any) ───
        if ($cogs > 0) {
            $entries[] = [
                'role_type' => 'COGS',
                'entry_type' => 'debit',
                'category' => 'cogs',
                'user_id' => null,
                'percentage' => round($cogs / $revenue * 100, 2),
                'amount' => $cogs,
            ];
        }

        // ─── 3. If profit ≤ 0: no commissions, owner absorbs remainder ───
        // When COGS >= revenue, the owner entry absorbs the gap so debits = credits.
        if ($profit <= 0) {
            // Owner remainder = revenue - cogs (will be 0 or negative, but we need
            // it as a debit to balance the ledger). When profit == 0 there's nothing
            // to add. When profit < 0 (loss), we still have credits=revenue and
            // debits=cogs, so we need a negative owner entry to balance.
            $ownerRemainder = $revenue - $cogs;
            if ($ownerRemainder != 0) {
                $entries[] = [
                    'role_type' => 'Owner',
                    'entry_type' => 'debit',
                    'category' => 'owner_remainder',
                    'user_id' => null,
                    'percentage' => round($ownerRemainder / $revenue * 100, 2),
                    'amount' => $ownerRemainder,
                ];
            }
            return $entries;
        }

        // ─── 4. Profit > 0: Calculate commissions ───
        $totalCommissionPct = 0;
        $commissionAmountsSum = 0;

        // ─── 4a. Performer commission ───
        $performerPct = 0;
        $performerId = $invoice->performed_by_user_id;
        $performerRole = User::performerRoleForDepartment($department);

        if ($performerId) {
            $performer = User::find($performerId);

            if ($performer && $performer->earnsCommission()) {
                $performerPct = $performer->commissionRateFor($serviceType);
            }
        }

        if ($performerPct > 0) {
            $roleLabel = ucfirst($performerRole);
            $commissionAmount = round($profit * $performerPct / 100, 2);
            $entries[] = [
                'role_type' => $roleLabel,
                'entry_type' => 'debit',
                'category' => 'commission',
                'user_id' => $performerId,
                'percentage' => $performerPct,
                'amount' => $commissionAmount,
                'gross_profit' => $profit,
            ];
            $totalCommissionPct += $performerPct;
            $commissionAmountsSum += $commissionAmount;
        }

        // ─── 4b. Doctor referral commission (non-consultation only) ───
        // Uses role='doctor' explicitly — NOT performerRoleForDepartment() which
        // would resolve to 'technician'/'pharmacist' and return the wrong rate.
        $doctorReferralPct = 0;
        if ($department !== 'consultation' && $invoice->prescribing_doctor_id) {
            $doctor = User::find($invoice->prescribing_doctor_id);

            if ($doctor && $doctor->earnsCommission()) {
                // Resolve via CommissionConfig with role='doctor' (referral rate)
                $doctorReferralPct = CommissionConfig::resolveRate($serviceType, 'doctor', $doctor->id);

                // Fall back to per-user column rates if no CommissionConfig entry
                if ($doctorReferralPct <= 0) {
                    $doctorReferralPct = match ($serviceType) {
                        'lab'       => (float) ($doctor->commission_lab ?? 0),
                        'radiology' => (float) ($doctor->commission_radiology ?? 0),
                        'pharmacy'  => (float) ($doctor->commission_pharmacy ?? 0),
                        default     => 0,
                    };
                }
            }

            if ($doctorReferralPct > 0) {
                $commissionAmount = round($profit * $doctorReferralPct / 100, 2);
                $entries[] = [
                    'role_type' => 'Doctor',
                    'entry_type' => 'debit',
                    'category' => 'commission',
                    'user_id' => $invoice->prescribing_doctor_id,
                    'percentage' => $doctorReferralPct,
                    'amount' => $commissionAmount,
                    'gross_profit' => $profit,
                    'is_prescribed' => true,
                ];
                $totalCommissionPct += $doctorReferralPct;
                $commissionAmountsSum += $commissionAmount;
            }
        }

        // ─── 4c. External referrer (not a system user) ───
        $referrerPct = 0;
        if ($invoice->referrer_name && $invoice->referrer_percentage > 0) {
            $referrerPct = (float) $invoice->referrer_percentage;
            $commissionAmount = round($profit * $referrerPct / 100, 2);
            $entries[] = [
                'role_type' => 'Referrer',
                'entry_type' => 'debit',
                'category' => 'commission',
                'user_id' => null,
                'percentage' => $referrerPct,
                'amount' => $commissionAmount,
                'gross_profit' => $profit,
            ];
            $totalCommissionPct += $referrerPct;
            $commissionAmountsSum += $commissionAmount;
        }

        // ─── 4d. Validate total commissions ≤ 100% of profit ───
        if ($totalCommissionPct > 100) {
            throw new InvalidArgumentException(
                "Commission misconfiguration: {$department} percentages exceed 100% of profit " .
                "(performer={$performerPct}%, doctor_referral={$doctorReferralPct}%, referrer={$referrerPct}%, total={$totalCommissionPct}%)"
            );
        }

        // ─── 4e. Owner absorbs remainder — calculated as exact remainder to prevent rounding imbalance ───
        $ownerAmount = $revenue - $cogs - $commissionAmountsSum;
        $ownerProfitPct = 100 - $totalCommissionPct;
        if ($ownerAmount > 0) {
            $entries[] = [
                'role_type' => 'Owner',
                'entry_type' => 'debit',
                'category' => 'owner_remainder',
                'user_id' => null,
                'percentage' => $ownerProfitPct,
                'amount' => round($ownerAmount, 2),
                'gross_profit' => $profit,
            ];
        }

        return $entries;
    }

    /**
     * Store immutable distribution snapshot on invoice.
     * Frozen at payment time — never recalculated after payment.
     * Includes actor information for auditability.
     */
    protected function storeDistributionSnapshot(Invoice $invoice, array $entries): void
    {
        $snapshot = [
            'frozen_at' => now()->toIso8601String(),
            'engine_version' => '2.0-actor-based',
            'effective_revenue' => $invoice->effective_revenue,
            'total_cogs' => $invoice->total_cogs,
            'profit' => $invoice->profit,
            'performed_by_user_id' => $invoice->performed_by_user_id,
            'created_by_user_id' => $invoice->created_by_user_id,
            'entries' => array_map(fn ($e) => [
                'role_type' => $e['role_type'],
                'entry_type' => $e['entry_type'],
                'category' => $e['category'] ?? null,
                'user_id' => $e['user_id'] ?? null,
                'percentage' => $e['percentage'],
                'amount' => $e['amount'],
            ], $entries),
        ];

        // DELIBERATE RAW DB BYPASS: Direct DB update to bypass the paid-invoice
        // immutability guard in the Eloquent model. This is safe because the
        // snapshot is written as part of the payment transaction itself.
        // WARNING: Do NOT copy this pattern for other fields — use Eloquent instead.
        DB::table('invoices')
            ->where('id', $invoice->id)
            ->update(['distribution_snapshot' => json_encode($snapshot)]);
    }

    /**
     * Add performer commission to an already-distributed invoice.
     *
     * Called when lab/rad staff complete work on an upfront-paid invoice.
     * The initial distribution (at payment time) included revenue, COGS,
     * doctor referral commission, and owner remainder — but NOT the performer
     * commission (performer was unknown). This method supplements the existing
     * entries by adding the performer's commission and reducing the owner's share.
     */
    public function distributePerformerCommission(Invoice $invoice): void
    {
        if (!$invoice->revenueLedgers()->exists()) {
            throw new InvalidArgumentException('Initial distribution must exist before adding performer commission.');
        }

        $performerId = $invoice->performed_by_user_id;
        if (!$performerId) {
            throw new InvalidArgumentException('performed_by_user_id must be set.');
        }

        $performer = User::find($performerId);
        $department = $invoice->department;
        $profit = $invoice->profit;

        // No commission to add if no profit or performer doesn't earn commission
        if ($profit <= 0 || !$performer || !$performer->earnsCommission()) {
            return;
        }

        $performerRole = User::performerRoleForDepartment($department);
        $performerPct = $performer->commissionRateFor($department);

        if ($performerPct <= 0) {
            return;
        }

        DB::transaction(function () use ($invoice, $performerId, $performerRole, $performerPct, $profit) {
            $locked = Invoice::where('id', $invoice->id)->lockForUpdate()->first();
            $roleLabel = ucfirst($performerRole);

            // Idempotency: if performer commission already exists, skip
            if (RevenueLedger::where('invoice_id', $invoice->id)
                ->where('role_type', $roleLabel)
                ->where('category', 'commission')
                ->exists()) {
                return;
            }

            $commissionAmount = round($profit * $performerPct / 100, 2);

            // Create performer commission entry
            RevenueLedger::create([
                'invoice_id' => $invoice->id,
                'user_id' => $performerId,
                'role_type' => $roleLabel,
                'entry_type' => 'debit',
                'category' => 'commission',
                'percentage' => $performerPct,
                'amount' => $commissionAmount,
                'commission_amount' => $commissionAmount,
                'gross_profit' => $profit,
                'is_prescribed' => false,
            ]);

            // Reduce owner remainder to maintain balanced ledger
            $ownerEntry = RevenueLedger::where('invoice_id', $invoice->id)
                ->where('category', 'owner_remainder')
                ->first();

            if ($ownerEntry) {
                $newAmount = round((float) $ownerEntry->amount - $commissionAmount, 2);
                $newPct = (float) $ownerEntry->percentage - $performerPct;

                if ($newAmount > 0.01) {
                    $ownerEntry->update([
                        'amount' => $newAmount,
                        'percentage' => max(0, $newPct),
                    ]);
                } else {
                    $ownerEntry->forceDelete();
                }
            }

            // Update distribution snapshot with current state
            $freshInvoice = $invoice->fresh();
            $currentEntries = $freshInvoice->revenueLedgers()->get()->map(fn ($l) => [
                'role_type' => $l->role_type,
                'entry_type' => $l->entry_type,
                'category' => $l->category,
                'user_id' => $l->user_id,
                'percentage' => (float) $l->percentage,
                'amount' => (float) $l->amount,
            ])->toArray();

            $this->storeDistributionSnapshot($freshInvoice, $currentEntries);

            // Audit log
            AuditLog::create([
                'user_id' => $performerId,
                'action' => 'performer_commission_added',
                'auditable_type' => Invoice::class,
                'auditable_id' => $invoice->id,
                'before_state' => null,
                'after_state' => json_encode([
                    'performer_id' => $performerId,
                    'role' => $roleLabel,
                    'percentage' => $performerPct,
                    'amount' => $commissionAmount,
                    'department' => $freshInvoice->department,
                ]),
                'ip_address' => request()?->ip(),
                'created_at' => now(),
            ]);
        });
    }

    /**
     * Validate balanced ledger: total debits must equal total credits.
     *
     * @throws RuntimeException if ledger is imbalanced
     */
    private function validateBalance(array $entries, Invoice $invoice): void
    {
        $credits = 0;
        $debits = 0;

        foreach ($entries as $entry) {
            if ($entry['entry_type'] === 'credit') {
                $credits += $entry['amount'];
            } else {
                $debits += $entry['amount'];
            }
        }

        // Allow for small rounding tolerance (1 cent)
        if (abs($credits - $debits) > 0.01) {
            throw new RuntimeException(
                "Ledger imbalance detected for invoice #{$invoice->id}: " .
                "credits={$credits}, debits={$debits}, diff=" . abs($credits - $debits)
            );
        }
    }

    /**
     * Create balanced ledger entries for the invoice.
     */
    private function createBalancedLedgerEntries(Invoice $invoice, array $entries): void
    {
        foreach ($entries as $entry) {
            RevenueLedger::create([
                'invoice_id' => $invoice->id,
                'user_id' => $entry['user_id'] ?? null,
                'role_type' => $entry['role_type'],
                'entry_type' => $entry['entry_type'],
                'category' => $entry['category'] ?? null,
                'percentage' => $entry['percentage'],
                'amount' => $entry['amount'],
                'commission_amount' => ($entry['entry_type'] === 'debit' && !in_array($entry['category'], ['cogs', 'owner_remainder']))
                    ? $entry['amount'] : null,
                'net_cost' => ($entry['category'] === 'cogs') ? $entry['amount'] : null,
                'gross_profit' => $entry['gross_profit'] ?? null,
                'is_prescribed' => $entry['is_prescribed'] ?? false,
            ]);
        }

        // Audit log
        AuditLog::create([
            'user_id' => $invoice->paid_by,
            'action' => 'commission_distributed',
            'auditable_type' => Invoice::class,
            'auditable_id' => $invoice->id,
            'before_state' => null,
            'after_state' => json_encode([
                'invoice_id' => $invoice->id,
                'department' => $invoice->department,
                'effective_revenue' => $invoice->effective_revenue,
                'profit' => $invoice->profit,
                'performed_by' => $invoice->performed_by_user_id,
                'entries_count' => count($entries),
            ]),
            'ip_address' => request()?->ip(),
            'created_at' => now(),
        ]);
    }
}
