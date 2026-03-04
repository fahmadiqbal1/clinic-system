<?php

namespace App\Services\Queries;

use App\Models\Expense;
use Carbon\Carbon;

class ExpenseIntelligenceQueryService
{
    /**
     * Get total expenses for date range
     */
    public function getTotalExpenses(Carbon $from, Carbon $to): float
    {
        return (float) Expense::whereBetween('created_at', [$from, $to])
            ->sum('cost');
    }

    /**
     * Get expenses today
     */
    public function getTodayExpenses(): float
    {
        $today = Carbon::now()->startOfDay();
        $tomorrow = Carbon::now()->addDay()->startOfDay();

        return $this->getTotalExpenses($today, $tomorrow);
    }

    /**
     * Get expenses this month
     */
    public function getMonthExpenses(): float
    {
        $start = Carbon::now()->startOfMonth();
        $end = Carbon::now()->endOfMonth();

        return $this->getTotalExpenses($start, $end);
    }

    /**
     * Get expense breakdown by type (Inventory vs Service procurement)
     */
    public function getExpensesByType(Carbon $from, Carbon $to): array
    {
        $inventoryExpenses = Expense::whereBetween('created_at', [$from, $to])
            ->where('description', 'like', '%Procurement:%')
            ->sum('cost');

        return [
            'procurement' => (float) $inventoryExpenses,
            'other' => (float) (Expense::whereBetween('created_at', [$from, $to])->sum('cost') - $inventoryExpenses),
        ];
    }

    /**
     * Get expenses by department for date range
     */
    public function getExpensesByDepartment(Carbon $from, Carbon $to): array
    {
        $expenses = Expense::whereBetween('created_at', [$from, $to])
            ->selectRaw('department, SUM(cost) as total')
            ->groupBy('department')
            ->get();

        $result = [
            'pharmacy' => 0.0,
            'laboratory' => 0.0,
            'radiology' => 0.0,
        ];

        foreach ($expenses as $expense) {
            if (isset($result[$expense->department])) {
                $result[$expense->department] = (float) $expense->total;
            }
        }

        return $result;
    }

    /**
     * Get top expense categories
     */
    public function getTopExpenseDescriptions(Carbon $from, Carbon $to, int $limit = 10): array
    {
        return Expense::whereBetween('created_at', [$from, $to])
            ->selectRaw('description, COUNT(*) as count, SUM(cost) as total')
            ->groupBy('description')
            ->orderByDesc('total')
            ->limit($limit)
            ->pluck('total', 'description')
            ->map(fn ($total) => (float) $total)
            ->toArray();
    }

    /**
     * Get average daily expense
     */
    public function getAverageDailyExpense(Carbon $from, Carbon $to): float
    {
        $total = $this->getTotalExpenses($from, $to);
        $days = $to->diffInDays($from) + 1;

        return $days > 0 ? $total / $days : 0.0;
    }

    /**
     * Get expense summary for dashboard
     */
    public function getExpenseSummary(Carbon $from, Carbon $to): array
    {
        return [
            'total' => $this->getTotalExpenses($from, $to),
            'average_daily' => $this->getAverageDailyExpense($from, $to),
            'by_department' => $this->getExpensesByDepartment($from, $to),
            'by_type' => $this->getExpensesByType($from, $to),
            'date_range' => [
                'from' => $from->format('Y-m-d'),
                'to' => $to->format('Y-m-d'),
            ],
        ];
    }
}
