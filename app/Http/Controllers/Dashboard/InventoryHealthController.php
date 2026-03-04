<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\Queries\InventoryHealthQueryService;
use App\Support\DepartmentScope;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;

class InventoryHealthController extends Controller
{
    use AuthorizesRequests;

    protected InventoryHealthQueryService $inventoryHealth;

    public function __construct(InventoryHealthQueryService $inventoryHealth)
    {
        $this->inventoryHealth = $inventoryHealth;
    }

    /**
     * Display inventory health dashboard
     */
    public function index()
    {
        $this->authorize('viewInventoryHealth');

        // Resolve department scope for current user
        $department = DepartmentScope::resolveForUser(Auth::user());

        $itemsByDepartment = $this->inventoryHealth->getAllItemsWithStock($department);
        $belowMinimumCounts = $this->inventoryHealth->countBelowMinimumByDepartment();

        return view('dashboard.inventory-health', [
            'itemsByDepartment' => $itemsByDepartment,
            'belowMinimumCounts' => $belowMinimumCounts,
            'userDepartment' => $department,
        ]);
    }
}
