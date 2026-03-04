<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\StockMovement;
use App\Support\DepartmentScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class InventoryItemController extends Controller
{
    /**
     * List inventory items, scoped by user's department.
     */
    public function index(Request $request): View
    {
        $user = Auth::user();
        $department = DepartmentScope::resolveForUser($user);

        $query = InventoryItem::query()->orderBy('name');

        // Department scoping
        if ($department) {
            $query->where('department', $department);
        } elseif ($request->filled('department')) {
            $query->where('department', $request->query('department'));
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('chemical_formula', 'like', "%{$search}%");
            });
        }

        $items = $query->paginate(25)->withQueryString();

        // Batch-load stock levels in a single query instead of N+1
        $stockLevels = StockMovement::whereIn('inventory_item_id', $items->pluck('id'))
            ->groupBy('inventory_item_id')
            ->selectRaw('inventory_item_id, SUM(quantity) as total_stock')
            ->pluck('total_stock', 'inventory_item_id');

        $items->getCollection()->transform(function ($item) use ($stockLevels) {
            $item->current_stock = $stockLevels[$item->id] ?? 0;
            return $item;
        });

        return view('inventory.index', [
            'items' => $items,
            'userDepartment' => $department,
            'filters' => $request->only(['department', 'search']),
        ]);
    }

    /**
     * Show form to create a new inventory item.
     */
    public function create(): View
    {
        $userDepartment = DepartmentScope::resolveForUser(Auth::user());

        return view('inventory.create', compact('userDepartment'));
    }

    /**
     * Store a new inventory item (Owner only).
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'department' => ['required', 'string', 'in:pharmacy,laboratory,radiology'],
            'chemical_formula' => ['nullable', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:100', 'unique:inventory_items,sku'],
            'unit' => ['required', 'string', 'max:50'],
            'minimum_stock_level' => ['required', 'integer', 'min:0'],
            'purchase_price' => ['required', 'numeric', 'min:0'],
            'selling_price' => ['required', 'numeric', 'min:0'],
            'requires_prescription' => ['boolean'],
            'is_active' => ['boolean'],
        ]);

        $validated['requires_prescription'] = $request->has('requires_prescription');
        $validated['is_active'] = $request->has('is_active') || !$request->exists('is_active');

        // Force department for dept-specific roles
        $userDepartment = DepartmentScope::resolveForUser(Auth::user());
        if ($userDepartment) {
            $validated['department'] = $userDepartment;
        }

        InventoryItem::create($validated);

        return redirect()->route('inventory.index', ['department' => $validated['department']])
            ->with('success', 'Inventory item "' . $validated['name'] . '" created successfully.');
    }

    /**
     * Show form to edit an inventory item.
     */
    public function edit(InventoryItem $inventoryItem): View
    {
        $userDepartment = DepartmentScope::resolveForUser(Auth::user());

        return view('inventory.edit', [
            'item' => $inventoryItem,
            'userDepartment' => $userDepartment,
        ]);
    }

    /**
     * Update an inventory item (Owner only).
     */
    public function update(Request $request, InventoryItem $inventoryItem): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'department' => ['required', 'string', 'in:pharmacy,laboratory,radiology'],
            'chemical_formula' => ['nullable', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:100', 'unique:inventory_items,sku,' . $inventoryItem->id],
            'unit' => ['required', 'string', 'max:50'],
            'minimum_stock_level' => ['required', 'integer', 'min:0'],
            'purchase_price' => ['required', 'numeric', 'min:0'],
            'selling_price' => ['required', 'numeric', 'min:0'],
            'requires_prescription' => ['boolean'],
            'is_active' => ['boolean'],
        ]);

        $validated['requires_prescription'] = $request->has('requires_prescription');
        $validated['is_active'] = $request->has('is_active');

        // Force department for dept-specific roles
        $userDepartment = DepartmentScope::resolveForUser(Auth::user());
        if ($userDepartment) {
            $validated['department'] = $userDepartment;
        }

        $inventoryItem->update($validated);

        return redirect()->route('inventory.index', ['department' => $inventoryItem->department])
            ->with('success', 'Inventory item "' . $inventoryItem->name . '" updated successfully.');
    }
}
