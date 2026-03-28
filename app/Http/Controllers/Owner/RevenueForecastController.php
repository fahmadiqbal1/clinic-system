<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RevenueForecastController extends Controller
{
    public function index(): View
    {
        // Last 12 weeks paid invoices by week + department
        $rawData = DB::table('invoices')
            ->where('status', 'paid')
            ->whereNotNull('paid_at')
            ->where('paid_at', '>=', now()->subWeeks(12))
            ->selectRaw("DATE_FORMAT(paid_at, '%x-%v') as yw, SUM(total_amount) as revenue, department, COUNT(*) as visits")
            ->groupBy('yw', 'department')
            ->orderBy('yw')
            ->get();

        // Aggregate by week
        $weeklyTotals = [];
        $deptBreakdown = [];
        foreach ($rawData as $row) {
            $weeklyTotals[$row->yw] = ($weeklyTotals[$row->yw] ?? 0) + (float) $row->revenue;
            $deptBreakdown[$row->department] = ($deptBreakdown[$row->department] ?? 0) + (float) $row->revenue;
        }
        ksort($weeklyTotals);

        $weekLabels = [];
        $weekRevenue = [];
        foreach ($weeklyTotals as $yw => $total) {
            [$year, $week] = explode('-', (string) $yw);
            $dt = new \DateTime();
            $dt->setISODate((int) $year, (int) $week);
            $weekLabels[] = $dt->format('d M');
            $weekRevenue[] = round($total, 2);
        }

        // 4-week moving average forecast
        $lastFour = array_slice($weekRevenue, -4);
        $avgLastFour = count($lastFour) > 0 ? array_sum($lastFour) / count($lastFour) : 0;
        $forecast = [];
        $forecastLabels = [];
        $lastDt = new \DateTime();
        for ($i = 1; $i <= 4; $i++) {
            $lastDt->modify('+1 week');
            $forecastLabels[] = $lastDt->format('d M') . ' (proj)';
            $forecast[] = round($avgLastFour, 2);
        }

        $avgWeekly = count($weekRevenue) > 0 ? array_sum($weekRevenue) / count($weekRevenue) : 0;
        $bestWeek  = count($weekRevenue) > 0 ? max($weekRevenue) : 0;
        $projectedNext = round($avgLastFour, 2);

        // Per-department per-week breakdown for comparison table
        $deptWeekly = [];
        foreach ($rawData as $row) {
            $deptWeekly[$row->department][$row->yw] = (float) $row->revenue;
        }

        // Build dept comparison: last week vs 4-week avg
        $allWeeks = array_keys($weeklyTotals);
        $lastWk   = end($allWeeks);
        $prevFour = array_slice($allWeeks, -5, 4); // weeks before the last

        $deptComparison = [];
        foreach ($deptWeekly as $dept => $weeks) {
            $lastWeekRev  = $weeks[$lastWk] ?? 0;
            $prevRevs     = array_filter(array_map(fn($w) => $weeks[$w] ?? null, $prevFour), fn($v) => $v !== null);
            $fourWkAvg    = count($prevRevs) > 0 ? array_sum($prevRevs) / count($prevRevs) : 0;
            $change       = $fourWkAvg > 0 ? (($lastWeekRev - $fourWkAvg) / $fourWkAvg) * 100 : 0;
            $deptComparison[] = [
                'dept'       => ucfirst($dept),
                'last_week'  => round($lastWeekRev, 0),
                'four_wk_avg'=> round($fourWkAvg, 0),
                'change_pct' => round($change, 1),
            ];
        }
        // Sort by last_week desc by default
        usort($deptComparison, fn($a, $b) => $b['last_week'] <=> $a['last_week']);

        return view('owner.revenue-forecast', compact(
            'weekLabels', 'weekRevenue', 'forecast', 'forecastLabels',
            'deptBreakdown', 'avgWeekly', 'bestWeek', 'projectedNext',
            'deptComparison'
        ));
    }
}
