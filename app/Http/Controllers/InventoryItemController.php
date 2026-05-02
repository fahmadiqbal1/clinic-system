<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\StockMovement;
use App\Support\DepartmentScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class InventoryItemController extends Controller
{
    /**
     * List inventory items, scoped by user's department.
     */
    public function index(Request $request): View|JsonResponse
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

        // Status filters
        if ($request->filled('status')) {
            match ($request->query('status')) {
                'active' => $query->where('is_active', true),
                'inactive' => $query->where('is_active', false),
                'rx' => $query->where('requires_prescription', true),
                default => null,
            };
        }

        // Search (including barcode)
        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('barcode', 'like', "%{$search}%")
                  ->orWhere('chemical_formula', 'like', "%{$search}%");
            });
        }

        // Sorting
        if ($request->filled('sort')) {
            match ($request->query('sort')) {
                'name_desc' => $query->reorder('name', 'desc'),
                'price_asc' => $query->reorder('selling_price', 'asc'),
                'price_desc' => $query->reorder('selling_price', 'desc'),
                default => null, // default name asc already set
            };
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

        // JSON response for AJAX live search - return rendered HTML partial
        if ($request->wantsJson()) {
            return response()->json([
                'html' => view('inventory._table', [
                    'items' => $items,
                    'userDepartment' => $department,
                ])->render(),
            ]);
        }

        // Stats for the stats bar
        $allItemsQuery = InventoryItem::query();
        if ($department) {
            $allItemsQuery->where('department', $department);
        }
        $totalItems = $allItemsQuery->count();
        $activeItems = (clone $allItemsQuery)->where('is_active', true)->count();

        $allStockLevels = StockMovement::query()
            ->when($department, fn($q) => $q->whereHas('inventoryItem', fn($q2) => $q2->where('department', $department)))
            ->groupBy('inventory_item_id')
            ->selectRaw('inventory_item_id, SUM(quantity) as total_stock')
            ->pluck('total_stock', 'inventory_item_id');

        $lowStockCount = InventoryItem::query()
            ->when($department, fn($q) => $q->where('department', $department))
            ->where('is_active', true)
            ->get()
            ->filter(fn($item) => ($allStockLevels[$item->id] ?? 0) <= $item->minimum_stock_level)
            ->count();

        $deptCounts = InventoryItem::query()
            ->selectRaw("department, COUNT(*) as cnt")
            ->groupBy('department')
            ->pluck('cnt', 'department');

        return view('inventory.index', [
            'items' => $items,
            'userDepartment' => $department,
            'filters' => $request->only(['department', 'search', 'status', 'sort']),
            'stats' => [
                'total' => $totalItems,
                'active' => $activeItems,
                'low_stock' => $lowStockCount,
                'departments' => $deptCounts,
            ],
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
            'name'                 => ['required', 'string', 'max:255'],
            'manufacturer'         => ['nullable', 'string', 'max:255'],
            'manufacturer_tag'     => ['nullable', 'string', 'max:8'],
            'department'           => ['required', 'string', 'in:pharmacy,laboratory,radiology'],
            'chemical_formula'     => ['nullable', 'string', 'max:255'],
            'sku'                  => ['nullable', 'string', 'max:100', 'unique:inventory_items,sku'],
            'barcode'              => ['nullable', 'string', 'max:100', 'unique:inventory_items,barcode'],
            'unit'                 => ['required', 'string', 'max:50'],
            'minimum_stock_level'  => ['required', 'integer', 'min:0'],
            'purchase_price'       => ['required', 'numeric', 'min:0'],
            'selling_price'        => ['required', 'numeric', 'min:0'],
            'requires_prescription' => ['boolean'],
            'is_active'            => ['boolean'],
        ]);

        $validated['requires_prescription'] = $request->has('requires_prescription');
        $validated['is_active'] = $request->has('is_active') || !$request->exists('is_active');

        // Auto-derive manufacturer_tag if manufacturer supplied but tag not
        if (!empty($validated['manufacturer']) && empty($validated['manufacturer_tag'])) {
            $validated['manufacturer_tag'] = InventoryItem::deriveManufacturerTag($validated['manufacturer']);
        }

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
        $currentStock = StockMovement::where('inventory_item_id', $inventoryItem->id)->sum('quantity');
        $recentMovements = StockMovement::where('inventory_item_id', $inventoryItem->id)
            ->with('creator')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        return view('inventory.edit', [
            'item' => $inventoryItem,
            'userDepartment' => $userDepartment,
            'currentStock' => $currentStock,
            'recentMovements' => $recentMovements,
        ]);
    }

    /**
     * Update an inventory item (Owner only).
     */
    public function update(Request $request, InventoryItem $inventoryItem): RedirectResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // Pharmacist can only update selling_price
        if ($user->hasRole('Pharmacy') && !$user->hasRole('Owner')) {
            $validated = $request->validate([
                'selling_price' => ['required', 'numeric', 'min:0'],
            ]);
            $inventoryItem->update($validated);

            return redirect()->route('inventory.index', ['department' => $inventoryItem->department])
                ->with('success', 'Selling price updated successfully.');
        }

        $validated = $request->validate([
            'name'                 => ['required', 'string', 'max:255'],
            'manufacturer'         => ['nullable', 'string', 'max:255'],
            'manufacturer_tag'     => ['nullable', 'string', 'max:8'],
            'department'           => ['required', 'string', 'in:pharmacy,laboratory,radiology'],
            'chemical_formula'     => ['nullable', 'string', 'max:255'],
            'sku'                  => ['nullable', 'string', 'max:100', 'unique:inventory_items,sku,' . $inventoryItem->id],
            'barcode'              => ['nullable', 'string', 'max:100', 'unique:inventory_items,barcode,' . $inventoryItem->id],
            'unit'                 => ['required', 'string', 'max:50'],
            'minimum_stock_level'  => ['required', 'integer', 'min:0'],
            'purchase_price'       => ['required', 'numeric', 'min:0'],
            'selling_price'        => ['required', 'numeric', 'min:0'],
            'requires_prescription' => ['boolean'],
            'is_active'            => ['boolean'],
        ]);

        if (!empty($validated['manufacturer']) && empty($validated['manufacturer_tag'])) {
            $validated['manufacturer_tag'] = InventoryItem::deriveManufacturerTag($validated['manufacturer']);
        }

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

    /**
     * AJAX barcode lookup — returns JSON item data.
     */
    public function barcodeLookup(Request $request): JsonResponse
    {
        $code = $request->query('code');
        if (!$code) {
            return response()->json(['found' => false]);
        }

        $item = InventoryItem::where('barcode', $code)->where('is_active', true)->first();
        if (!$item) {
            return response()->json(['found' => false]);
        }

        $currentStock = StockMovement::where('inventory_item_id', $item->id)->sum('quantity');

        return response()->json([
            'found' => true,
            'item' => [
                'id' => $item->id,
                'name' => $item->name,
                'barcode' => $item->barcode,
                'sku' => $item->sku,
                'unit' => $item->unit,
                'department' => $item->department,
                'selling_price' => $item->selling_price,
                'purchase_price' => $item->purchase_price,
                'weighted_avg_cost' => $item->weighted_avg_cost,
                'current_stock' => $currentStock,
                'minimum_stock_level' => $item->minimum_stock_level,
                'requires_prescription' => $item->requires_prescription,
            ],
        ]);
    }

    /**
     * AJAX quick update — inline price/status edits from index page.
     */
    public function quickUpdate(Request $request, InventoryItem $inventoryItem): JsonResponse
    {
        $validated = $request->validate([
            'selling_price' => ['sometimes', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $inventoryItem->update($validated);

        return response()->json(['success' => true, 'item' => $inventoryItem->fresh()]);
    }

    /**
     * Show stock adjustment form.
     */
    public function showAdjust(InventoryItem $inventoryItem): View
    {
        $currentStock = StockMovement::where('inventory_item_id', $inventoryItem->id)->sum('quantity');
        $recentMovements = StockMovement::where('inventory_item_id', $inventoryItem->id)
            ->with('creator')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return view('inventory.adjust', [
            'item' => $inventoryItem,
            'currentStock' => $currentStock,
            'recentMovements' => $recentMovements,
        ]);
    }

    /**
     * Process stock adjustment.
     */
    public function storeAdjust(Request $request, InventoryItem $inventoryItem): RedirectResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'not_in:0'],
            'reason' => ['required', 'string', 'in:breakage,expiry,theft,spillage,physical_count,returned,other'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $currentStock = StockMovement::where('inventory_item_id', $inventoryItem->id)->sum('quantity');
        $newStock = $currentStock + $validated['quantity'];

        if ($newStock < 0) {
            return back()->withErrors(['quantity' => 'Adjustment would result in negative stock (' . $newStock . '). Current stock is ' . $currentStock . '.'])->withInput();
        }

        StockMovement::create([
            'inventory_item_id' => $inventoryItem->id,
            'type' => 'adjustment',
            'quantity' => $validated['quantity'],
            'unit_cost' => $inventoryItem->weighted_avg_cost,
            'reference_type' => 'manual',
            'notes' => ucfirst($validated['reason']) . ($validated['notes'] ? ': ' . $validated['notes'] : ''),
            'created_by' => Auth::id(),
        ]);

        return redirect()->route('inventory.index', ['department' => $inventoryItem->department])
            ->with('success', 'Stock adjusted for "' . $inventoryItem->name . '". New stock: ' . $newStock . '.');
    }
}
