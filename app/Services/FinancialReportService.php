<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Invoice;
use App\Models\RevenueLedger;
use Carbon\Carbon;

class FinancialReportService
{
    /**
     * Get total revenue from paid invoices (sum of credit entries with category=revenue).
     */
    public function getRevenueBetween(\DateTimeInterface $from, \DateTimeInterface $to): float
    {
        return (float) RevenueLedger::where('entry_type', 'credit')
            ->where('category', 'revenue')
            ->whereHas('invoice', function ($query) {
                // Use withTrashed as defense-in-depth: even if a paid invoice
                // is somehow soft-deleted, its revenue should remain in reports.
                $query->withTrashed()->where('status', Invoice::STATUS_PAID);
            })
            ->whereBetween('created_at', [$from, $to])
            ->sum('amount');
    }

    public function getExpensesBetween(\DateTimeInterface $from, \DateTimeInterface $to): float
    {
        return (float) Expense::whereBetween('created_at', [$from, $to])
            ->sum('cost');
    }

    public function getNetProfitBetween(\DateTimeInterface $from, \DateTimeInterface $to): float
    {
        $revenue = $this->getRevenueBetween($from, $to);
        $expenses = $this->getExpensesBetween($from, $to);

        // Subtract commissions and COGS recorded as debit entries in the revenue ledger
        $commissions = (float) RevenueLedger::where('entry_type', 'debit')
            ->where('category', 'commission')
            ->whereBetween('created_at', [$from, $to])
            ->sum('amount');

        $cogs = (float) RevenueLedger::where('entry_type', 'debit')
            ->where('category', 'cogs')
            ->whereBetween('created_at', [$from, $to])
            ->sum('amount');

        return $revenue - $expenses - $commissions - $cogs;
    }

    public function getDepartmentBreakdown(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        $breakdown = [];

        // Expenses per department
        $expenses = Expense::whereBetween('created_at', [$from, $to])
            ->groupBy('department')
            ->selectRaw('department, SUM(cost) as total_cost')
            ->pluck('total_cost', 'department');

        $expenses->each(function ($cost, $department) use (&$breakdown) {
            $breakdown[$department] = [
                'expenses' => (float) $cost,
                'revenue' => 0.0,
            ];
        });

        // Revenue per department from paid invoices (post-discount)
        $revenues = Invoice::where('status', Invoice::STATUS_PAID)
            ->whereBetween('paid_at', [$from, $to])
            ->groupBy('department')
            ->selectRaw('department, SUM(COALESCE(net_amount, total_amount - COALESCE(discount_amount, 0))) as total_revenue')
            ->pluck('total_revenue', 'department');

        $revenues->each(function ($rev, $department) use (&$breakdown) {
            if (!isset($breakdown[$department])) {
                $breakdown[$department] = ['expenses' => 0.0, 'revenue' => 0.0];
            }
            $breakdown[$department]['revenue'] = (float) $rev;
        });

        return $breakdown;
    }

    /**
     * Comprehensive Department P&L with revenue, COGS, commissions, expenses, profit.
     */
    public function getDepartmentPnl(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        $departments = ['consultation', 'lab', 'radiology', 'pharmacy', 'general'];
        $pnl = [];

        foreach ($departments as $dept) {
            $pnl[$dept] = [
                'revenue' => 0.0,
                'cogs' => 0.0,
                'commissions' => 0.0,
                'expenses_fixed' => 0.0,
                'expenses_variable' => 0.0,
                'expenses_procurement' => 0.0,
                'net_profit' => 0.0,
            ];
        }

        // Revenue per department from paid invoices
        $revenues = Invoice::where('status', Invoice::STATUS_PAID)
            ->whereBetween('paid_at', [$from, $to])
            ->groupBy('department')
            ->selectRaw('department, SUM(COALESCE(net_amount, total_amount - COALESCE(discount_amount, 0))) as total_revenue')
            ->pluck('total_revenue', 'department');

        $revenues->each(function ($rev, $dept) use (&$pnl) {
            if (isset($pnl[$dept])) $pnl[$dept]['revenue'] = (float) $rev;
        });

        // COGS per department (debit entries with category=cogs, joined via invoice)
        $cogsData = RevenueLedger::where('entry_type', 'debit')
            ->where('category', 'cogs')
            ->whereHas('invoice', fn ($q) => $q->where('status', Invoice::STATUS_PAID))
            ->whereBetween('revenue_ledgers.created_at', [$from, $to])
            ->join('invoices', 'revenue_ledgers.invoice_id', '=', 'invoices.id')
            ->groupBy('invoices.department')
            ->selectRaw('invoices.department, SUM(revenue_ledgers.amount) as total_cogs')
            ->pluck('total_cogs', 'department');

        $cogsData->each(function ($cogs, $dept) use (&$pnl) {
            if (isset($pnl[$dept])) $pnl[$dept]['cogs'] = (float) $cogs;
        });

        // Commissions per department (debit entries with category=commission, joined via invoice)
        $commData = RevenueLedger::where('entry_type', 'debit')
            ->where('category', 'commission')
            ->whereHas('invoice', fn ($q) => $q->where('status', Invoice::STATUS_PAID))
            ->whereBetween('revenue_ledgers.created_at', [$from, $to])
            ->join('invoices', 'revenue_ledgers.invoice_id', '=', 'invoices.id')
            ->groupBy('invoices.department')
            ->selectRaw('invoices.department, SUM(revenue_ledgers.amount) as total_comm')
            ->pluck('total_comm', 'department');

        $commData->each(function ($comm, $dept) use (&$pnl) {
            if (isset($pnl[$dept])) $pnl[$dept]['commissions'] = (float) $comm;
        });

        // Expenses per department, split by category
        $expData = Expense::whereBetween('created_at', [$from, $to])
            ->groupBy('department', 'category')
            ->selectRaw('department, category, SUM(cost) as total_cost')
            ->get();

        foreach ($expData as $row) {
            $dept = $row->department;
            if (!isset($pnl[$dept])) continue;

            match ($row->category) {
                'fixed' => $pnl[$dept]['expenses_fixed'] = (float) $row->total_cost,
                'procurement' => $pnl[$dept]['expenses_procurement'] = (float) $row->total_cost,
                default => $pnl[$dept]['expenses_variable'] += (float) $row->total_cost,
            };
        }

        // Calculate net profit per department
        foreach ($pnl as $dept => &$data) {
            $totalExpenses = $data['expenses_fixed'] + $data['expenses_variable'] + $data['expenses_procurement'];
            $data['total_expenses'] = $totalExpenses;
            $data['net_profit'] = $data['revenue'] - $data['cogs'] - $data['commissions'] - $totalExpenses;
        }

        return $pnl;
    }

    /**
     * Get doctor revenue only from paid invoices.
     */
    public function getDoctorBreakdown(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        $breakdown = [];

        $doctorRevenue = RevenueLedger::where('role_type', 'Doctor')
            ->whereHas('invoice', function ($query) {
                $query->withTrashed()->where('status', Invoice::STATUS_PAID);
            })
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('user_id')
            ->selectRaw('user_id, SUM(amount) as total_amount')
            ->with('user')
            ->get();

        $doctorRevenue->each(function ($revenue) use (&$breakdown) {
            if ($revenue->user) {
                $breakdown[$revenue->user->id] = [
                    'name' => $revenue->user->name,
                    'revenue' => (float) $revenue->total_amount,
                ];
            }
        });

        return $breakdown;
    }

    public function getPendingPayouts(): float
    {
        return (float) RevenueLedger::where('category', 'commission')
            ->whereNull('payout_id')
            ->sum('amount');
    }

    public function getPendingPayoutsCount(): int
    {
        return RevenueLedger::where('category', 'commission')
            ->whereNull('payout_id')
            ->count();
    }

    public function getTodayRevenue(): float
    {
        $today = Carbon::today();
        return $this->getRevenueBetween($today, $today->copy()->endOfDay());
    }

    public function getTodayExpenses(): float
    {
        $today = Carbon::today();
        return $this->getExpensesBetween($today, $today->copy()->endOfDay());
    }

    public function getTodayNetProfit(): float
    {
        $today = Carbon::today();
        return $this->getNetProfitBetween($today, $today->copy()->endOfDay());
    }
}
