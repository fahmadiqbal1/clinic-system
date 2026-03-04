<?php

namespace App\Http\Controllers\Laboratory;

use App\Http\Controllers\Controller;
use App\Models\ProcurementRequest;
use App\Models\ServiceCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TestCatalogController extends Controller
{
    public function index(): View
    {
        $categories = ServiceCatalog::forDepartment('lab')
            ->orderBy('category')
            ->orderBy('name')
            ->get()
            ->groupBy('category');

        // Pending catalog change requests
        $pendingChanges = ProcurementRequest::where('type', ProcurementRequest::TYPE_CATALOG_CHANGE)
            ->where('department', 'laboratory')
            ->where('status', 'pending')
            ->with('requester')
            ->latest()
            ->get();

        return view('laboratory.catalog.index', compact('categories', 'pendingChanges'));
    }

    public function create(): View
    {
        return view('laboratory.catalog.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50|unique:service_catalog,code',
            'description' => 'nullable|string|max:500',
            'category' => 'nullable|string|max:100',
            'price' => 'required|numeric|min:0',
            'turnaround_time' => 'nullable|string|max:100',
        ]);

        /** @var \App\Models\User $user */
        $user = Auth::user();

        $payload = [
            'department' => 'lab',
            'name' => $validated['name'],
            'code' => $validated['code'] ?? null,
            'description' => $validated['description'] ?? null,
            'category' => $validated['category'] ?? null,
            'price' => $validated['price'],
            'turnaround_time' => $validated['turnaround_time'] ?? null,
            'is_active' => true,
        ];

        // Owner: direct save. Staff: submit for approval.
        if ($user->hasRole('Owner')) {
            ServiceCatalog::create($payload);

            return redirect()->route('laboratory.catalog.index')
                ->with('success', 'Test added to catalog.');
        }

        ProcurementRequest::create([
            'department' => 'laboratory',
            'type' => ProcurementRequest::TYPE_CATALOG_CHANGE,
            'change_action' => ProcurementRequest::ACTION_CREATE,
            'change_payload' => $payload,
            'target_model' => ServiceCatalog::class,
            'target_id' => null,
            'requested_by' => $user->id,
            'status' => 'pending',
            'notes' => 'New test: ' . $validated['name'],
        ]);

        return redirect()->route('laboratory.catalog.index')
            ->with('success', 'New test request submitted for owner approval.');
    }

    public function edit(ServiceCatalog $serviceCatalog): View
    {
        return view('laboratory.catalog.edit', ['service' => $serviceCatalog, 'serviceCatalog' => $serviceCatalog]);
    }

    public function update(Request $request, ServiceCatalog $serviceCatalog): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50|unique:service_catalog,code,' . $serviceCatalog->id,
            'description' => 'nullable|string|max:500',
            'category' => 'nullable|string|max:100',
            'price' => 'required|numeric|min:0',
            'turnaround_time' => 'nullable|string|max:100',
            'is_active' => 'boolean',
        ]);

        $payload = [
            'name' => $validated['name'],
            'code' => $validated['code'] ?? null,
            'description' => $validated['description'] ?? null,
            'category' => $validated['category'] ?? null,
            'price' => $validated['price'],
            'turnaround_time' => $validated['turnaround_time'] ?? null,
            'is_active' => $request->has('is_active'),
        ];

        /** @var \App\Models\User $user */
        $user = Auth::user();

        // Owner: direct update. Staff: submit for approval.
        if ($user->hasRole('Owner')) {
            $serviceCatalog->update($payload);

            return redirect()->route('laboratory.catalog.index')
                ->with('success', 'Test catalog entry updated.');
        }

        ProcurementRequest::create([
            'department' => 'laboratory',
            'type' => ProcurementRequest::TYPE_CATALOG_CHANGE,
            'change_action' => ProcurementRequest::ACTION_UPDATE,
            'change_payload' => $payload,
            'target_model' => ServiceCatalog::class,
            'target_id' => $serviceCatalog->id,
            'requested_by' => $user->id,
            'status' => 'pending',
            'notes' => 'Update test: ' . $serviceCatalog->name,
        ]);

        return redirect()->route('laboratory.catalog.index')
            ->with('success', 'Test update request submitted for owner approval.');
    }

    public function destroy(ServiceCatalog $serviceCatalog): RedirectResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // Owner: direct delete. Staff: submit for approval.
        if ($user->hasRole('Owner')) {
            $serviceCatalog->delete();
            return redirect()->route('laboratory.catalog.index')
                ->with('success', 'Test removed from catalog.');
        }

        ProcurementRequest::create([
            'department' => 'laboratory',
            'type' => ProcurementRequest::TYPE_CATALOG_CHANGE,
            'change_action' => ProcurementRequest::ACTION_DELETE,
            'change_payload' => ['name' => $serviceCatalog->name, 'code' => $serviceCatalog->code],
            'target_model' => ServiceCatalog::class,
            'target_id' => $serviceCatalog->id,
            'requested_by' => $user->id,
            'status' => 'pending',
            'notes' => 'Delete test: ' . $serviceCatalog->name,
        ]);

        return redirect()->route('laboratory.catalog.index')
            ->with('success', 'Test deletion request submitted for owner approval.');
    }
}
