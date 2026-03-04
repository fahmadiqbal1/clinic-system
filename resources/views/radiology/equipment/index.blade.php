@extends('layouts.app')
@section('title', 'Radiology Equipment — ' . config('app.name'))

@section('content')
<div class="container mt-4">
    <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2 fade-in">
        <div>
            <h2 class="mb-1"><i class="bi bi-tools me-2" style="color:var(--accent-warning);"></i>Radiology Equipment</h2>
            <p class="page-subtitle mb-0">Manage imaging instruments & maintenance schedules</p>
        </div>
        <a href="{{ route('radiology.equipment.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle me-1"></i>Add Equipment</a>
    </div>

    {{-- Pending Equipment Change Requests --}}
    @if(isset($pendingChanges) && $pendingChanges->count() > 0)
    <div class="glass-card p-3 mb-4 fade-in" style="border-left:3px solid var(--accent-warning);">
        <h6 class="fw-bold mb-2"><i class="bi bi-hourglass-split me-2" style="color:var(--accent-warning);"></i>Pending Approval ({{ $pendingChanges->count() }})</h6>
        @foreach($pendingChanges as $change)
            <div class="d-flex justify-content-between align-items-center mb-1 p-2 rounded" style="background:var(--glass-bg);">
                <div>
                    <span class="badge badge-glass-warning me-1">{{ ucfirst($change->change_action) }}</span>
                    <span class="fw-medium">{{ $change->change_payload['name'] ?? 'Equipment' }}</span>
                    <small class="ms-2" style="color:var(--text-muted);">by {{ $change->requester?->name }}</small>
                </div>
                <a href="{{ route('procurement.show', $change) }}" class="btn btn-sm btn-outline-info"><i class="bi bi-eye me-1"></i>View</a>
            </div>
        @endforeach
    </div>
    @endif

    @if($equipment->count() > 0)
        <div class="glass-card fade-in delay-1">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Model</th>
                            <th>Serial Number</th>
                            <th>Status</th>
                            <th>Last Maintenance</th>
                            <th>Next Maintenance</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($equipment as $item)
                            <tr>
                                <td class="fw-medium">{{ $item->name }}</td>
                                <td>{{ $item->model ?? '—' }}</td>
                                <td><code class="code-tag">{{ $item->serial_number ?? '—' }}</code></td>
                                <td>
                                    @if($item->status === 'operational')
                                        <span class="badge badge-glass-success">Operational</span>
                                    @elseif($item->status === 'maintenance')
                                        <span class="badge badge-glass-warning">Maintenance</span>
                                    @else
                                        <span class="badge badge-glass-danger">Out of Service</span>
                                    @endif
                                </td>
                                <td>{{ $item->last_maintenance_date?->format('M d, Y') ?? '—' }}</td>
                                <td>
                                    @if($item->next_maintenance_date)
                                        <span class="{{ $item->next_maintenance_date->isPast() ? 'text-danger fw-bold' : '' }}">
                                            {{ $item->next_maintenance_date->format('M d, Y') }}
                                        </span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('radiology.equipment.edit', $item) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil me-1"></i>Edit</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <div class="glass-card text-center py-5 fade-in delay-1">
            <i class="bi bi-tools" style="font-size:2.5rem; color:var(--accent-warning); opacity:0.5;"></i>
            <h5 class="mt-3">No Equipment Registered</h5>
            <p class="mb-3" style="color:var(--text-muted);">Start by adding your first piece of imaging equipment.</p>
            <a href="{{ route('radiology.equipment.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle me-1"></i>Add Equipment</a>
        </div>
    @endif
</div>
@endsection
