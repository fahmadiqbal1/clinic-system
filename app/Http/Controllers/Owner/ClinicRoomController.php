<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\ClinicRoom;
use App\Services\AuditableService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ClinicRoomController extends Controller
{
    private const TYPES = ['gp', 'consultant', 'dental', 'aesthetics', 'procedure', 'other'];

    public function index(): View
    {
        $rooms = ClinicRoom::orderBy('sort_order')->orderBy('name')->get();

        return view('owner.rooms.index', compact('rooms'));
    }

    public function create(): View
    {
        return view('owner.rooms.create', ['types' => self::TYPES]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'            => ['required', 'string', 'max:100', 'unique:clinic_rooms,name'],
            'type'            => ['required', 'in:' . implode(',', self::TYPES)],
            'specialty'       => ['nullable', 'string', 'max:100'],
            'equipment_notes' => ['nullable', 'string'],
            'sort_order'      => ['nullable', 'integer'],
            'is_active'       => ['nullable', 'boolean'],
        ]);

        $room = ClinicRoom::create([
            'name'            => $validated['name'],
            'type'            => $validated['type'],
            'specialty'       => $validated['specialty'] ?? null,
            'equipment_notes' => $validated['equipment_notes'] ?? null,
            'sort_order'      => $validated['sort_order'] ?? 0,
            'is_active'       => isset($validated['is_active']) ? (bool) $validated['is_active'] : true,
        ]);

        AuditableService::logCreate($room, 'clinic_room');

        return redirect()->route('owner.rooms.index')
            ->with('success', "Room \"{$room->name}\" created successfully.");
    }

    public function edit(ClinicRoom $room): View
    {
        return view('owner.rooms.edit', ['room' => $room, 'types' => self::TYPES]);
    }

    public function update(Request $request, ClinicRoom $room): RedirectResponse
    {
        $validated = $request->validate([
            'name'            => ['required', 'string', 'max:100', 'unique:clinic_rooms,name,' . $room->id],
            'type'            => ['required', 'in:' . implode(',', self::TYPES)],
            'specialty'       => ['nullable', 'string', 'max:100'],
            'equipment_notes' => ['nullable', 'string'],
            'sort_order'      => ['nullable', 'integer'],
            'is_active'       => ['nullable', 'boolean'],
        ]);

        $before = $room->toArray();

        $room->update([
            'name'            => $validated['name'],
            'type'            => $validated['type'],
            'specialty'       => $validated['specialty'] ?? null,
            'equipment_notes' => $validated['equipment_notes'] ?? null,
            'sort_order'      => $validated['sort_order'] ?? 0,
            'is_active'       => isset($validated['is_active']) ? (bool) $validated['is_active'] : false,
        ]);

        AuditableService::logUpdate($room, 'clinic_room', [
            'before' => $before,
            'after'  => $room->getChanges(),
        ]);

        return redirect()->route('owner.rooms.index')
            ->with('success', "Room \"{$room->name}\" updated successfully.");
    }

    public function destroy(ClinicRoom $room): RedirectResponse
    {
        // Check for any future non-terminal appointments linked to this room
        $hasFutureAppointments = DB::table('appointments')
            ->where('room_id', $room->id)
            ->where('scheduled_at', '>=', now())
            ->whereNotIn('status', ['cancelled', 'completed'])
            ->exists();

        if ($hasFutureAppointments) {
            return redirect()->back()
                ->with('error', "Cannot delete \"{$room->name}\" — it has upcoming appointments. Reassign or cancel them first.");
        }

        AuditableService::logDelete($room, 'clinic_room');
        $room->delete();

        return redirect()->route('owner.rooms.index')
            ->with('success', "Room \"{$room->name}\" deleted.");
    }
}
