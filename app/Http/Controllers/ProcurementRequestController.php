<?php

namespace App\Http\Controllers;

use App\Models\ProcurementRequest;
use App\Models\InventoryItem;
use App\Models\User;
use App\Notifications\ProcurementAwaitingApproval;
use App\Support\DepartmentScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class ProcurementRequestController extends Controller
{
    use AuthorizesRequests;

    /**
     * List all procurement requests (with role-based filtering)
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', ProcurementRequest::class);

        $query = ProcurementRequest::with('requester', 'approver', 'items.inventoryItem');

        // Filter by department for non-Owner, non-Receptionist roles
        $userDepartment = DepartmentScope::resolveForUser(Auth::user());
        if ($userDepartment) {
            $query->where('department', $userDepartment);
        }

        $requests = $query->latest()->paginate();

        return view('procurement.index', compact('requests'));
    }

    /**
     * Show a specific procurement request
     */
    public function show(ProcurementRequest $request)
    {
        $this->authorize('view', $request);

        $request->load('requester', 'approver', 'items.inventoryItem');

        return view('procurement.show', compact('request'));
    }

    /**
     * Create new procurement request (GET form)
     */
    public function create()
    {
        $this->authorize('create', ProcurementRequest::class);

        // Determine user's department from role
        $userDepartment = DepartmentScope::resolveForUser(Auth::user());

        // Load inventory items — filtered to user's department for dept-specific roles
        $query = InventoryItem::where('is_active', true);
        if ($userDepartment) {
            $query->where('department', $userDepartment);
        }
        $inventoryItems = $query->orderBy('department')->orderBy('name')->get();

        return view('procurement.create', compact('inventoryItems', 'userDepartment'));
    }

    /**
     * Store a new procurement request
     */
    public function store(Request $request)
    {
        $this->authorize('create', ProcurementRequest::class);

        // Base validation rules
        $rules = [
            'department' => 'required|in:pharmacy,laboratory,radiology',
            'type' => 'required|in:inventory,service',
            'notes' => 'nullable|string',
        ];

        // Type-specific validation
        $requestType = $request->input('type');

        if ($requestType === 'inventory') {
            // Inventory procurements require items with inventory_item_id
            $rules['items'] = 'required|array|min:1';
            $rules['items.*.inventory_item_id'] = 'required|exists:inventory_items,id';
            $rules['items.*.quantity_requested'] = 'required|integer|min:1';
        } elseif ($requestType === 'service') {
            // Service procurements are work orders: item name, quantity, unit_price only
            $rules['items'] = 'required|array|min:1';
            $rules['items.*.service_name'] = 'required|string|max:255';
            $rules['items.*.quantity_requested'] = 'required|integer|min:1';
            $rules['items.*.unit_price'] = 'required|numeric|min:0.01';
        }

        $validated = $request->validate($rules);

        // Force department for dept-specific roles (prevent manipulation)
        $userDepartment = DepartmentScope::resolveForUser(Auth::user());
        if ($userDepartment) {
            $validated['department'] = $userDepartment;
        }

        $proc = ProcurementRequest::create([
            'department' => $validated['department'],
            'type' => $validated['type'],
            'requested_by' => Auth::user()->id,
            'status' => 'pending',
            'notes' => $validated['notes'],
        ]);

        // Attach items with domain-invariant enforcement
        if ($requestType === 'inventory') {
            foreach ($validated['items'] as $item) {
                $proc->items()->create([
                    'inventory_item_id' => $item['inventory_item_id'],
                    'quantity_requested' => $item['quantity_requested'],
                ]);
            }
        } elseif ($requestType === 'service') {
            // Service items: no inventory_item_id, but unit_price is set upfront
            foreach ($validated['items'] as $item) {
                $proc->items()->create([
                    'inventory_item_id' => null, // Enforce null for services
                    'quantity_requested' => $item['quantity_requested'],
                    'unit_price' => $item['unit_price'],
                ]);
            }
        }

        // Notify all owners about the new procurement request
        $owners = User::role('Owner')->get();
        foreach ($owners as $owner) {
            $owner->notify(new ProcurementAwaitingApproval(
                $proc->id,
                $proc->department,
                $proc->type,
                Auth::user()->name,
            ));
        }

        return redirect()->route('procurement.show', $proc)
            ->with('success', 'Procurement request created successfully.');
    }
}
