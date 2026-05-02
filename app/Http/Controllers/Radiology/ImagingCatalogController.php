<?php

namespace App\Http\Controllers\Radiology;

use App\Http\Controllers\Controller;
use App\Models\ProcurementRequest;
use App\Models\ServiceCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ImagingCatalogController extends Controller
{
    public function index(): View
    {
        $services = ServiceCatalog::forDepartment('radiology')->orderBy('name')->paginate(25);

        $pendingChanges = ProcurementRequest::where('type', ProcurementRequest::TYPE_CATALOG_CHANGE)
            ->where('department', 'radiology')
            ->where('status', 'pending')
            ->with('requester')
            ->latest()
            ->get();

        return view('radiology.catalog.index', compact('services', 'pendingChanges'));
    }

    public function create(): View
    {
        return view('radiology.catalog.create');
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
            'department' => 'radiology',
            'name' => $validated['name'],
            'code' => $validated['code'] ?? null,
            'description' => $validated['description'] ?? null,
            'category' => $validated['category'] ?? null,
            'price' => $validated['price'],
            'turnaround_time' => $validated['turnaround_time'] ?? null,
            'is_active' => true,
        ];

        if ($user->hasRole('Owner')) {
            ServiceCatalog::create($payload);

            return redirect()->route('radiology.catalog.index')
                ->with('success', 'Imaging service added to catalog.');
        }

        ProcurementRequest::create([
            'department' => 'radiology',
            'type' => ProcurementRequest::TYPE_CATALOG_CHANGE,
            'change_action' => ProcurementRequest::ACTION_CREATE,
            'change_payload' => $payload,
            'target_model' => ServiceCatalog::class,
            'target_id' => null,
            'requested_by' => $user->id,
            'status' => 'pending',
            'notes' => 'New imaging service: ' . $validated['name'],
        ]);

        return redirect()->route('radiology.catalog.index')
            ->with('success', 'Imaging service request submitted for owner approval.');
    }

    public function edit(ServiceCatalog $serviceCatalog): View
    {
        return view('radiology.catalog.edit', ['service' => $serviceCatalog]);
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
            'code' => $validated['code'] ?? $serviceCatalog->code,
            'description' => $validated['description'] ?? null,
            'category' => $validated['category'] ?? null,
            'price' => $validated['price'],
            'turnaround_time' => $validated['turnaround_time'] ?? null,
            'is_active' => $request->has('is_active'),
        ];

        /** @var \App\Models\User $user */
        $user = Auth::user();

        if ($user->hasRole('Owner')) {
            $serviceCatalog->update($payload);

            return redirect()->route('radiology.catalog.index')
                ->with('success', 'Imaging catalog entry updated.');
        }

        ProcurementRequest::create([
            'department' => 'radiology',
            'type' => ProcurementRequest::TYPE_CATALOG_CHANGE,
            'change_action' => ProcurementRequest::ACTION_UPDATE,
            'change_payload' => $payload,
            'target_model' => ServiceCatalog::class,
            'target_id' => $serviceCatalog->id,
            'requested_by' => $user->id,
            'status' => 'pending',
            'notes' => 'Update imaging service: ' . $serviceCatalog->name,
        ]);

        return redirect()->route('radiology.catalog.index')
            ->with('success', 'Imaging service update request submitted for owner approval.');
    }

    public function destroy(ServiceCatalog $serviceCatalog): RedirectResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if ($user->hasRole('Owner')) {
            $serviceCatalog->delete();
            return redirect()->route('radiology.catalog.index')
                ->with('success', 'Imaging service removed from catalog.');
        }

        ProcurementRequest::create([
            'department' => 'radiology',
            'type' => ProcurementRequest::TYPE_CATALOG_CHANGE,
            'change_action' => ProcurementRequest::ACTION_DELETE,
            'change_payload' => ['name' => $serviceCatalog->name, 'code' => $serviceCatalog->code],
            'target_model' => ServiceCatalog::class,
            'target_id' => $serviceCatalog->id,
            'requested_by' => $user->id,
            'status' => 'pending',
            'notes' => 'Delete imaging service: ' . $serviceCatalog->name,
        ]);

        return redirect()->route('radiology.catalog.index')
            ->with('success', 'Imaging service deletion request submitted for owner approval.');
    }
}
