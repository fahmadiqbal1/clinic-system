<?php

namespace App\Http\Controllers\Radiology;

use App\Http\Controllers\Controller;
use App\Models\Equipment;
use App\Models\ProcurementRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class EquipmentController extends Controller
{
    public function index(): View
    {
        $equipment = Equipment::forDepartment('radiology')->orderBy('name')->paginate(25);

        $pendingChanges = ProcurementRequest::where('type', ProcurementRequest::TYPE_EQUIPMENT_CHANGE)
            ->where('department', 'radiology')
            ->where('status', 'pending')
            ->with('requester')
            ->latest()
            ->get();

        return view('radiology.equipment.index', compact('equipment', 'pendingChanges'));
    }

    public function create(): View
    {
        return view('radiology.equipment.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'model' => 'nullable|string|max:255',
            'serial_number' => 'nullable|string|max:255',
            'status' => 'required|string|in:operational,maintenance,out_of_service',
            'last_maintenance_date' => 'nullable|date',
            'next_maintenance_date' => 'nullable|date',
            'notes' => 'nullable|string|max:500',
        ]);

        /** @var \App\Models\User $user */
        $user = Auth::user();

        if ($user->hasRole('Owner')) {
            Equipment::create(array_merge($validated, [
                'department' => 'radiology',
                'is_active' => true,
            ]));

            return redirect()->route('radiology.equipment.index')
                ->with('success', 'Equipment added successfully.');
        }

        ProcurementRequest::create([
            'department' => 'radiology',
            'type' => ProcurementRequest::TYPE_EQUIPMENT_CHANGE,
            'change_action' => ProcurementRequest::ACTION_CREATE,
            'change_payload' => array_merge($validated, ['department' => 'radiology', 'is_active' => true]),
            'target_model' => Equipment::class,
            'target_id' => null,
            'requested_by' => $user->id,
            'status' => 'pending',
            'notes' => 'New equipment: ' . $validated['name'],
        ]);

        return redirect()->route('radiology.equipment.index')
            ->with('success', 'Equipment request submitted for owner approval.');
    }

    public function edit(Equipment $equipment): View
    {
        return view('radiology.equipment.edit', compact('equipment'));
    }

    public function update(Request $request, Equipment $equipment): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'model' => 'nullable|string|max:255',
            'serial_number' => 'nullable|string|max:255',
            'status' => 'required|string|in:operational,maintenance,out_of_service',
            'last_maintenance_date' => 'nullable|date',
            'next_maintenance_date' => 'nullable|date',
            'notes' => 'nullable|string|max:500',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->has('is_active');

        /** @var \App\Models\User $user */
        $user = Auth::user();

        if ($user->hasRole('Owner')) {
            $equipment->update($validated);

            return redirect()->route('radiology.equipment.index')
                ->with('success', 'Equipment updated successfully.');
        }

        ProcurementRequest::create([
            'department' => 'radiology',
            'type' => ProcurementRequest::TYPE_EQUIPMENT_CHANGE,
            'change_action' => ProcurementRequest::ACTION_UPDATE,
            'change_payload' => $validated,
            'target_model' => Equipment::class,
            'target_id' => $equipment->id,
            'requested_by' => $user->id,
            'status' => 'pending',
            'notes' => 'Update equipment: ' . $equipment->name,
        ]);

        return redirect()->route('radiology.equipment.index')
            ->with('success', 'Equipment update request submitted for owner approval.');
    }
}
