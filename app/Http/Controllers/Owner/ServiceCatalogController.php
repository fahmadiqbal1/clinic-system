<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\ServiceCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServiceCatalogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->only(['department', 'search', 'category']);

        // Department counts for the card overview
        $departmentCounts = ServiceCatalog::selectRaw('department, count(*) as total, sum(is_active) as active_count')
            ->groupBy('department')
            ->pluck('total', 'department')
            ->toArray();

        $activeCounts = ServiceCatalog::where('is_active', true)
            ->selectRaw('department, count(*) as cnt')
            ->groupBy('department')
            ->pluck('cnt', 'department')
            ->toArray();

        // If a department is selected, show filtered list grouped by category
        $services = null;
        $categories = [];
        if ($request->filled('department')) {
            $query = ServiceCatalog::where('department', $request->query('department'))
                ->orderBy('category')
                ->orderBy('name');

            if ($request->filled('search')) {
                $search = $request->query('search');
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%")
                      ->orWhere('category', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }

            if ($request->filled('category')) {
                $query->where('category', $request->query('category'));
            }

            $services = $query->get()->groupBy('category');

            // All categories for the filter dropdown
            $categories = ServiceCatalog::where('department', $request->query('department'))
                ->whereNotNull('category')
                ->distinct()
                ->orderBy('category')
                ->pluck('category')
                ->toArray();
        }

        return view('owner.service-catalog.index', [
            'services'         => $services,
            'filters'          => $filters,
            'departmentCounts' => $departmentCounts,
            'activeCounts'     => $activeCounts,
            'categories'       => $categories,
        ]);
    }

    public function create(): View
    {
        return view('owner.service-catalog.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'department' => 'required|string|in:lab,radiology,pharmacy,consultation',
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50|unique:service_catalog,code',
            'hs_code' => 'nullable|string|max:20',
            'description' => 'nullable|string|max:500',
            'category' => 'nullable|string|max:100',
            'price' => 'required|numeric|min:0',
            'turnaround_time' => 'nullable|string|max:100',
        ]);

        ServiceCatalog::create(array_merge($validated, ['is_active' => true]));

        return redirect()->route('owner.service-catalog.index')
            ->with('success', 'Service added to catalog.');
    }

    public function edit(ServiceCatalog $serviceCatalog): View
    {
        return view('owner.service-catalog.edit', ['service' => $serviceCatalog]);
    }

    public function update(Request $request, ServiceCatalog $serviceCatalog): RedirectResponse
    {
        $validated = $request->validate([
            'department' => 'required|string|in:lab,radiology,pharmacy,consultation',
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50|unique:service_catalog,code,' . $serviceCatalog->id,
            'hs_code' => 'nullable|string|max:20',
            'description' => 'nullable|string|max:500',
            'category' => 'nullable|string|max:100',
            'price' => 'required|numeric|min:0',
            'turnaround_time' => 'nullable|string|max:100',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->has('is_active');
        $serviceCatalog->update($validated);

        return redirect()->route('owner.service-catalog.index')
            ->with('success', 'Service catalog entry updated.');
    }

    public function destroy(ServiceCatalog $serviceCatalog): RedirectResponse
    {
        $serviceCatalog->delete();
        return redirect()->route('owner.service-catalog.index')
            ->with('success', 'Service removed from catalog.');
    }

    /**
     * Inline quick-update for price and active status (AJAX).
     */
    public function quickUpdate(Request $request, ServiceCatalog $serviceCatalog): JsonResponse
    {
        $validated = $request->validate([
            'price'     => ['sometimes', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $serviceCatalog->update($validated);

        return response()->json([
            'success' => true,
            'price'   => number_format($serviceCatalog->fresh()->price, 2),
            'message' => 'Updated successfully.',
        ]);
    }
}
