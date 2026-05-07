@extends('layouts.app')
@section('title', 'Doctor Credentials — ' . config('app.name'))

@section('content')
<div class="fade-in">
    <div class="page-header">
        <div>
            <h1 class="page-title"><i class="bi bi-patch-check me-2" style="color:var(--accent-primary);"></i>Doctor Credentials</h1>
            <p class="page-subtitle">Review and verify medical licences and degree certificates</p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
            <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if($doctors->count() > 0)
    <div class="glass-card fade-in delay-1">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Doctor</th>
                        <th>Specialty</th>
                        <th>Submitted</th>
                        <th>Verified</th>
                        <th>Pending</th>
                        <th>Total Files</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($doctors as $doctor)
                    <tr>
                        <td>
                            <div class="fw-medium">{{ $doctor->name }}</div>
                            <div class="small" style="color:var(--text-muted);">{{ $doctor->email }}</div>
                        </td>
                        <td class="small">{{ $doctor->specialty ?: '—' }}</td>
                        <td class="small">
                            {{ $doctor->credentials_submitted_at ? $doctor->credentials_submitted_at->format('d M Y') : '—' }}
                        </td>
                        <td class="small">
                            @if($doctor->credentials_verified_at)
                                <span class="badge-glass" style="background:rgba(var(--accent-success-rgb),0.15);color:var(--accent-success);">
                                    <i class="bi bi-check-circle me-1"></i>{{ $doctor->credentials_verified_at->format('d M Y') }}
                                </span>
                            @elseif($doctor->credentials_submitted_at)
                                <span class="badge-glass" style="background:rgba(var(--accent-warning-rgb),0.15);color:var(--accent-warning);">
                                    <i class="bi bi-hourglass-split me-1"></i>Pending
                                </span>
                            @else
                                <span class="badge-glass"><i class="bi bi-x-circle me-1"></i>Not submitted</span>
                            @endif
                        </td>
                        <td>
                            @if($doctor->pending_count > 0)
                                <span class="badge bg-warning text-dark">{{ $doctor->pending_count }}</span>
                            @else
                                <span class="text-muted small">0</span>
                            @endif
                        </td>
                        <td class="text-muted small">{{ $doctor->total_credentials }}</td>
                        <td>
                            <a href="{{ route('owner.credentials.doctor', $doctor) }}" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-folder2-open me-1"></i>View
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @else
    <div class="empty-state fade-in delay-1">
        <i class="bi bi-people" style="font-size:2rem;opacity:0.3;"></i>
        <h6 class="mt-3 mb-1">No doctors found</h6>
        <p class="small mb-0" style="color:var(--text-muted);">Add doctor accounts via User Management first</p>
    </div>
    @endif
</div>
@endsection
