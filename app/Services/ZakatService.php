<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Invoice;
use App\Models\RevenueLedger;
use App\Models\ZakatTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ZakatService
{
    /**
     * Calculate zakat for the owner's net profit over a given period.
     *
     * Zakat = zakat_percentage% of Owner Net Profit (if > 0).
     *
     * Owner Net Profit = Total Revenue − Total COGS − Staff Commissions − Total Expenses
     *
     * Expenses include: salaries, rent, utilities, miscellaneous, etc.
     *
     * @param Carbon $periodStart Start of the calculation period
     * @param Carbon $periodEnd   End of the calculation period
     * @param int|null $calculatedBy User ID of the person triggering the calculation
     * @param float $zakatPercentage Zakat percentage (default 2.5% per Islamic Zakat al-Mal)
     * @param string|null $notes Optional notes
     * @return ZakatTransaction
     */
    public function calculate(
        Carbon $periodStart,
        Carbon $periodEnd,
        ?int $calculatedBy = null,
        float $zakatPercentage = 2.50,
        ?string $notes = null,
    ): ZakatTransaction {
        // Total revenue from paid invoices in period
        $totalRevenue = (float) Invoice::where('status', Invoice::STATUS_PAID)
            ->whereBetween('paid_at', [$periodStart->startOfDay(), $periodEnd->endOfDay()])
            ->sum(DB::raw('COALESCE(net_amount, total_amount - COALESCE(discount_amount, 0))'));

        // Total COGS from revenue ledger (COGS entries for paid invoices in period)
        $totalCogs = (float) RevenueLedger::whereHas('invoice', function ($q) use ($periodStart, $periodEnd) {
            $q->withTrashed()->where('status', Invoice::STATUS_PAID)
                ->whereBetween('paid_at', [$periodStart->startOfDay(), $periodEnd->endOfDay()]);
        })->where('category', 'cogs')->sum('amount');

        // Total staff commissions (debit entries that are actual commissions, not COGS or owner_remainder)
        $totalCommissions = (float) RevenueLedger::whereHas('invoice', function ($q) use ($periodStart, $periodEnd) {
            $q->withTrashed()->where('status', Invoice::STATUS_PAID)
                ->whereBetween('paid_at', [$periodStart->startOfDay(), $periodEnd->endOfDay()]);
        })
            ->where('entry_type', 'debit')
            ->where('category', 'commission')
            ->sum('amount');

        // Total expenses for the period (salaries, rent, utilities, etc.)
        $totalExpenses = (float) Expense::whereBetween('created_at', [$periodStart->startOfDay(), $periodEnd->endOfDay()])
            ->sum('cost');

        // Owner Net Profit
        $ownerNetProfit = $totalRevenue - $totalCogs - $totalCommissions - $totalExpenses;

        // Zakat: percentage of net profit if positive, 0 otherwise
        $zakatAmount = $ownerNetProfit > 0
            ? round($ownerNetProfit * $zakatPercentage / 100, 2)
            : 0;

        return ZakatTransaction::create([
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'total_revenue' => $totalRevenue,
            'total_cogs' => $totalCogs,
            'total_commissions' => $totalCommissions,
            'total_expenses' => $totalExpenses,
            'owner_net_profit' => $ownerNetProfit,
            'zakat_amount' => $zakatAmount,
            'zakat_percentage' => $zakatPercentage,
            'calculated_by' => $calculatedBy,
            'notes' => $notes,
        ]);
    }

    /**
     * Get a breakdown summary without persisting (for preview/report).
     */
    public function preview(Carbon $periodStart, Carbon $periodEnd, float $zakatPercentage = 2.50): array
    {
        $totalRevenue = (float) Invoice::where('status', Invoice::STATUS_PAID)
            ->whereBetween('paid_at', [$periodStart->startOfDay(), $periodEnd->endOfDay()])
            ->sum(DB::raw('COALESCE(net_amount, total_amount - COALESCE(discount_amount, 0))'));

        $totalCogs = (float) RevenueLedger::whereHas('invoice', function ($q) use ($periodStart, $periodEnd) {
            $q->withTrashed()->where('status', Invoice::STATUS_PAID)
                ->whereBetween('paid_at', [$periodStart->startOfDay(), $periodEnd->endOfDay()]);
        })->where('category', 'cogs')->sum('amount');

        $totalCommissions = (float) RevenueLedger::whereHas('invoice', function ($q) use ($periodStart, $periodEnd) {
            $q->withTrashed()->where('status', Invoice::STATUS_PAID)
                ->whereBetween('paid_at', [$periodStart->startOfDay(), $periodEnd->endOfDay()]);
        })
            ->where('entry_type', 'debit')
            ->where('category', 'commission')
            ->whereNotNull('user_id')
            ->sum('amount');

        $totalExpenses = (float) Expense::whereBetween('created_at', [$periodStart->startOfDay(), $periodEnd->endOfDay()])
            ->sum('cost');

        $ownerNetProfit = $totalRevenue - $totalCogs - $totalCommissions - $totalExpenses;
        $zakatAmount = $ownerNetProfit > 0
            ? round($ownerNetProfit * $zakatPercentage / 100, 2)
            : 0;

        return [
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'total_revenue' => $totalRevenue,
            'total_cogs' => $totalCogs,
            'total_commissions' => $totalCommissions,
            'total_expenses' => $totalExpenses,
            'owner_net_profit' => $ownerNetProfit,
            'zakat_percentage' => $zakatPercentage,
            'zakat_amount' => $zakatAmount,
        ];
    }
}
