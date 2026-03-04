<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\Queries\ExpenseIntelligenceQueryService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class ExpenseIntelligenceController extends Controller
{
    use AuthorizesRequests;

    protected ExpenseIntelligenceQueryService $expenseIntelligence;

    public function __construct(ExpenseIntelligenceQueryService $expenseIntelligence)
    {
        $this->expenseIntelligence = $expenseIntelligence;
    }

    /**
     * Display expense intelligence dashboard
     */
    public function index(Request $request)
    {
        $this->authorize('viewExpenseIntelligence');

        $from = $request->input('from')
            ? Carbon::createFromFormat('Y-m-d', $request->input('from'))->startOfDay()
            : Carbon::now()->startOfMonth();

        $to = $request->input('to')
            ? Carbon::createFromFormat('Y-m-d', $request->input('to'))->endOfDay()
            : Carbon::now()->endOfMonth();

        $summary = $this->expenseIntelligence->getExpenseSummary($from, $to);
        $topDescriptions = $this->expenseIntelligence->getTopExpenseDescriptions($from, $to, 10);

        return view('dashboard.expense-intelligence', [
            'summary' => $summary,
            'topDescriptions' => $topDescriptions,
            'fromDate' => $from->format('Y-m-d'),
            'toDate' => $to->format('Y-m-d'),
        ]);
    }
}
