<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    /**
     * List all expenses with filtering.
     */
    public function index(Request $request): View
    {
        $query = Expense::with(['patient', 'invoice', 'creator'])
            ->orderByDesc('created_at');

        // Date filtering
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->query('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->query('to'));
        }

        // Department filtering
        if ($request->filled('department')) {
            $query->where('department', $request->query('department'));
        }

        // Source filtering (manual vs procurement)
        if ($request->query('source') === 'manual') {
            $query->whereNull('invoice_id');
        } elseif ($request->query('source') === 'procurement') {
            $query->whereNotNull('invoice_id');
        }

        // Category filtering
        if ($request->filled('category')) {
            $query->where('category', $request->query('category'));
        }

        $totalFiltered = (clone $query)->sum('cost');

        $expenses = $query->paginate(25)->withQueryString();

        return view('owner.expenses.index', [
            'expenses' => $expenses,
            'totalFiltered' => $totalFiltered,
            'filters' => $request->only(['from', 'to', 'department', 'source', 'category']),
        ]);
    }

    /**
     * Show form to create a manual expense.
     */
    public function create(): View
    {
        return view('owner.expenses.create');
    }

    /**
     * Store a manual expense.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'department' => ['required', 'string', 'in:lab,radiology,pharmacy,consultation,general'],
            'category' => ['required', 'string', 'in:fixed,variable'],
            'description' => ['required', 'string', 'max:500'],
            'cost' => ['required', 'numeric', 'min:0.01', 'max:9999999'],
        ]);

        Expense::create([
            'department' => $validated['department'],
            'category' => $validated['category'],
            'description' => $validated['description'],
            'cost' => $validated['cost'],
            'created_by' => Auth::id(),
        ]);

        return redirect()->route('owner.expenses.index')
            ->with('success', 'Expense recorded successfully.');
    }

    /**
     * Show form to edit an expense.
     */
    public function edit(Expense $expense): View
    {
        // Only allow editing manual expenses (no invoice link)
        if ($expense->invoice_id) {
            abort(403, 'Procurement-linked expenses cannot be edited.');
        }

        return view('owner.expenses.edit', [
            'expense' => $expense,
        ]);
    }

    /**
     * Update a manual expense.
     */
    public function update(Request $request, Expense $expense): RedirectResponse
    {
        if ($expense->invoice_id) {
            abort(403, 'Procurement-linked expenses cannot be edited.');
        }

        $validated = $request->validate([
            'department' => ['required', 'string', 'in:lab,radiology,pharmacy,consultation,general'],
            'category' => ['required', 'string', 'in:fixed,variable'],
            'description' => ['required', 'string', 'max:500'],
            'cost' => ['required', 'numeric', 'min:0.01', 'max:9999999'],
        ]);

        $expense->update($validated);

        return redirect()->route('owner.expenses.index')
            ->with('success', 'Expense updated successfully.');
    }

    /**
     * Delete a manual expense.
     */
    public function destroy(Expense $expense): RedirectResponse
    {
        if ($expense->invoice_id) {
            abort(403, 'Procurement-linked expenses cannot be deleted.');
        }

        $expense->delete();

        return redirect()->route('owner.expenses.index')
            ->with('success', 'Expense deleted successfully.');
    }
}
