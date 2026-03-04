<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\Queries\InventoryHealthQueryService;
use App\Support\DepartmentScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class LowStockAlertController extends Controller
{
    use AuthorizesRequests;

    protected InventoryHealthQueryService $inventoryHealth;

    public function __construct(InventoryHealthQueryService $inventoryHealth)
    {
        $this->inventoryHealth = $inventoryHealth;
    }

    /**
     * Display low-stock alert view
     */
    public function index()
    {
        $this->authorize('viewLowStockAlerts');

        // Resolve department scope for current user
        $department = DepartmentScope::resolveForUser(Auth::user());

        $belowMinimumItems = $this->inventoryHealth->getItemsBelowMinimum($department);

        return view('dashboard.low-stock-alerts', [
            'items' => $belowMinimumItems,
            'userDepartment' => $department,
        ]);
    }
}
