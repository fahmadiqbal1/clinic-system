@extends('layouts.app')
@section('title', 'Clinic Rooms — ' . config('app.name'))

@section('content')
<div class="fade-in">
    <div class="page-header">
        <div>
            <h1 class="page-title"><i class="bi bi-door-open me-2" style="color:var(--accent-primary);"></i>Clinic Rooms</h1>
            <p class="page-subtitle">Manage consultation rooms, procedure suites, and shared spaces</p>
        </div>
        <a href="{{ route('owner.rooms.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Add Room</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
            <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if($rooms->count() > 0)
    <div class="glass-card fade-in delay-1">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Specialty</th>
                        <th>Status</th>
                        <th>Order</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rooms as $room)
                    <tr>
                        <td class="text-muted small">{{ $room->id }}</td>
                        <td>
                            <div class="fw-medium">{{ $room->name }}</div>
                            @if($room->equipment_notes)
                                <div class="small" style="color:var(--text-muted);">{{ Str::limit($room->equipment_notes, 60) }}</div>
                            @endif
                        </td>
                        <td><span class="badge-glass text-capitalize">{{ str_replace('_', ' ', $room->type) }}</span></td>
                        <td class="small">{{ $room->specialty ?: '—' }}</td>
                        <td>
                            @if($room->is_active)
                                <span class="badge-glass" style="background:rgba(var(--accent-success-rgb),0.15);color:var(--accent-success);"><i class="bi bi-circle-fill me-1" style="font-size:.5rem;"></i>Active</span>
                            @else
                                <span class="badge-glass"><i class="bi bi-circle me-1" style="font-size:.5rem;"></i>Inactive</span>
                            @endif
                        </td>
                        <td class="small text-muted">{{ $room->sort_order }}</td>
                        <td>
                            <a href="{{ route('owner.rooms.edit', $room) }}" class="btn btn-sm btn-outline-secondary me-1"><i class="bi bi-pencil"></i></a>
                            <form method="POST" action="{{ route('owner.rooms.destroy', $room) }}" class="d-inline" onsubmit="return confirm('Delete room \'{{ addslashes($room->name) }}\'?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @else
    <div class="empty-state fade-in delay-1">
        <i class="bi bi-door-closed" style="font-size:2rem;opacity:0.3;"></i>
        <h6 class="mt-3 mb-1">No rooms configured</h6>
        <p class="small mb-2" style="color:var(--text-muted);">Add your first consultation room or procedure suite</p>
        <a href="{{ route('owner.rooms.create') }}" class="btn btn-sm btn-primary">Add First Room</a>
    </div>
    @endif
</div>
@endsection
