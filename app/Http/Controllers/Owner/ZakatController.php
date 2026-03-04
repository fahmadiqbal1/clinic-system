<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\ZakatTransaction;
use App\Services\ZakatService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ZakatController extends Controller
{
    /**
     * Show the Zakat management page with preview and history.
     */
    public function index(Request $request): View
    {
        $from = $request->query('from', Carbon::now()->startOfYear()->format('Y-m-d'));
        $to = $request->query('to', Carbon::now()->format('Y-m-d'));

        $zakatService = new ZakatService();
        $preview = $zakatService->preview(
            Carbon::parse($from),
            Carbon::parse($to),
            (float) $request->query('zakat_percentage', 2.5)
        );

        $history = ZakatTransaction::with('calculator')
            ->latest()
            ->paginate(10);

        return view('owner.zakat.index', [
            'from' => $from,
            'to' => $to,
            'zakat_percentage' => $request->query('zakat_percentage', 2.5),
            'preview' => $preview,
            'history' => $history,
        ]);
    }

    /**
     * Calculate and persist a new zakat transaction.
     */
    public function calculate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
            'zakat_percentage' => 'required|numeric|min:0.01|max:100',
            'notes' => 'nullable|string|max:500',
        ]);

        $zakatService = new ZakatService();
        $transaction = $zakatService->calculate(
            Carbon::parse($validated['period_start']),
            Carbon::parse($validated['period_end']),
            $request->user()->id,
            (float) $validated['zakat_percentage'],
            $validated['notes'] ?? null
        );

        return redirect()->route('owner.zakat.index')
            ->with('success', 'Zakat calculated: ' . number_format($transaction->zakat_amount, 2) . ' for period ' . $validated['period_start'] . ' to ' . $validated['period_end']);
    }
}
