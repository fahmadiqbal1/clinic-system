<?php

namespace App\Http\Controllers;

use App\Models\ProcurementRequest;
use App\Models\InventoryItem;
use App\Models\User;
use App\Models\Vendor;
use App\Notifications\ProcurementAwaitingApproval;
use App\Services\AiProcurementApprovalService;
use App\Support\DepartmentScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
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
        $vendors        = Vendor::where('is_approved', true)->orderBy('name')->get();

        return view('procurement.create', compact('inventoryItems', 'userDepartment', 'vendors'));
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
            'type'       => 'required|in:inventory,service,new_item_request',
            'notes'      => 'nullable|string',
        ];

        $requestType = $request->input('type');

        if ($requestType === 'inventory') {
            $rules['items']                            = 'required|array|min:1';
            $rules['items.*.inventory_item_id']        = 'required|exists:inventory_items,id';
            $rules['items.*.quantity_requested']       = 'required|integer|min:1';
            $rules['items.*.quoted_unit_price']        = 'nullable|numeric|min:0';
        } elseif ($requestType === 'service') {
            $rules['items']                            = 'required|array|min:1';
            $rules['items.*.service_name']             = 'required|string|max:255';
            $rules['items.*.quantity_requested']       = 'required|integer|min:1';
            $rules['items.*.unit_price']               = 'required|numeric|min:0.01';
        } elseif ($requestType === 'new_item_request') {
            $rules['new_items']                        = 'required|array|min:1';
            $rules['new_items.*.name']                 = 'required|string|max:255';
            $rules['new_items.*.manufacturer']         = 'required|string|max:255';
            $rules['new_items.*.unit']                 = 'nullable|string|max:50';
            $rules['new_items.*.pack_size']            = 'nullable|string|max:100';
            $rules['new_items.*.qty']                  = 'required|integer|min:1';
            $rules['new_items.*.unit_price']           = 'nullable|numeric|min:0';
            $rules['vendor_id']                        = 'nullable|exists:vendors,id';
            // Mandatory price checklist — hard gate
            $rules['price_checklist']                  = 'required|file|mimes:pdf,csv,txt|max:10240';
        }

        $validated = $request->validate($rules);

        // Force department for dept-specific roles (prevent manipulation)
        $userDepartment = DepartmentScope::resolveForUser(Auth::user());
        if ($userDepartment) {
            $validated['department'] = $userDepartment;
        }

        $procData = [
            'department'   => $validated['department'],
            'type'         => $validated['type'],
            'requested_by' => Auth::user()->id,
            'status'       => 'pending',
            'notes'        => $validated['notes'] ?? null,
        ];

        if ($requestType === 'new_item_request') {
            // Store checklist file
            $checklistPath = $request->file('price_checklist')->store('procurement/checklists', 'public');
            $procData['price_list_path']    = $checklistPath;
            $procData['vendor_id']          = $validated['vendor_id'] ?? null;
            $procData['change_payload']     = array_values($validated['new_items']);
        }

        $proc = ProcurementRequest::create($procData);

        // Attach items
        if ($requestType === 'inventory') {
            foreach ($validated['items'] as $item) {
                $proc->items()->create([
                    'inventory_item_id'  => $item['inventory_item_id'],
                    'quantity_requested' => $item['quantity_requested'],
                    'quoted_unit_price'  => isset($item['quoted_unit_price']) && $item['quoted_unit_price'] > 0
                        ? (float) $item['quoted_unit_price']
                        : null,
                ]);
            }
        } elseif ($requestType === 'service') {
            foreach ($validated['items'] as $item) {
                $proc->items()->create([
                    'inventory_item_id'  => null,
                    'quantity_requested' => $item['quantity_requested'],
                    'unit_price'         => $item['unit_price'],
                ]);
            }
        }
        // new_item_request: items are in change_payload; no ProcurementRequestItem rows needed

        // Run AI approval gate (auto-approves if cost < 25K and no duplicates)
        if (in_array($requestType, ['inventory', 'new_item_request'])) {
            app(AiProcurementApprovalService::class)->evaluate($proc->fresh());
            // Reload to get updated status after AI evaluation
            $proc->refresh();
        }

        // Notify owners only if still pending after AI evaluation
        if ($proc->status === 'pending') {
            $owners = User::role('Owner')->get();
            foreach ($owners as $owner) {
                $owner->notify(new ProcurementAwaitingApproval(
                    $proc->id,
                    $proc->department,
                    $proc->type,
                    Auth::user()->name,
                ));
            }
        }

        $message = $proc->status === 'approved'
            ? 'Procurement request auto-approved by AI (cost < PKR 25,000).'
            : 'Procurement request submitted and awaiting approval.';

        return redirect()->route('procurement.show', $proc)->with('success', $message);
    }
}
