<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\StockMovement;
use App\Support\DepartmentScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class StockMovementController extends Controller
{
    /**
     * List stock movement history, scoped by user's department (read-only).
     */
    public function index(Request $request): View
    {
        $user = Auth::user();
        $department = DepartmentScope::resolveForUser($user);

        $query = StockMovement::with(['inventoryItem', 'creator'])
            ->orderByDesc('created_at');

        // Department scoping via inventory item
        if ($department) {
            $query->whereHas('inventoryItem', function ($q) use ($department) {
                $q->where('department', $department);
            });
        } elseif ($request->filled('department')) {
            $query->whereHas('inventoryItem', function ($q) use ($request) {
                $q->where('department', $request->query('department'));
            });
        }

        // Item filtering
        if ($request->filled('item_id')) {
            $query->where('inventory_item_id', $request->query('item_id'));
        }

        // Type filtering
        if ($request->filled('type')) {
            $query->where('type', $request->query('type'));
        }

        // Date filtering
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->query('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->query('to'));
        }

        $movements = $query->paginate(25)->withQueryString();

        // Get items for filter dropdown (scoped by department)
        $itemQuery = InventoryItem::query()->orderBy('name');
        if ($department) {
            $itemQuery->where('department', $department);
        }
        $items = $itemQuery->get();

        return view('stock-movements.index', [
            'movements' => $movements,
            'items' => $items,
            'userDepartment' => $department,
            'filters' => $request->only(['department', 'item_id', 'type', 'from', 'to']),
        ]);
    }
}
