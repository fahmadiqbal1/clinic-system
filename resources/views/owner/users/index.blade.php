@extends('layouts.app')
@section('title', 'Users — ' . config('app.name'))

@section('content')
<div class="container py-4">
    {{-- Page Header --}}
    <div class="page-header mb-4">
        <div>
            <h1 class="page-title"><i class="bi bi-people me-2"></i>User Management</h1>
            <p class="text-muted mb-0">Create and manage clinic staff accounts</p>
        </div>
        <a href="{{ route('owner.users.create') }}" class="btn btn-primary">
            <i class="bi bi-person-plus me-1"></i> Create User
        </a>
    </div>

    {{-- Active / Inactive Tabs --}}
    <ul class="nav nav-tabs mb-3">
        <li class="nav-item">
            <a class="nav-link {{ ($status ?? 'active') === 'active' ? 'active' : '' }}" href="{{ route('owner.users.index', ['status' => 'active']) }}">
                <i class="bi bi-check-circle me-1"></i>Active Users
                <span class="badge bg-success ms-1">{{ $activeCount }}</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ ($status ?? 'active') === 'inactive' ? 'active' : '' }}" href="{{ route('owner.users.index', ['status' => 'inactive']) }}">
                <i class="bi bi-x-circle me-1"></i>Inactive Users
                <span class="badge bg-secondary ms-1">{{ $inactiveCount }}</span>
            </a>
        </li>
    </ul>

    @if($users->count() > 0)
        <div class="glass-panel p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="sortable-th">Name</th>
                            <th class="sortable-th">Email</th>
                            <th class="sortable-th">Role</th>
                            <th class="sortable-th">Compensation</th>
                            <th class="sortable-th">Status</th>
                            <th class="sortable-th d-none d-md-table-cell">Created</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($users as $user)
                            <tr>
                                <td><strong>{{ $user->name }}</strong></td>
                                <td class="text-muted">{{ $user->email }}</td>
                                <td>
                                    @if($user->roles->count() > 0)
                                        <span class="badge-glass">{{ $user->roles->first()?->name ?? 'No Role' }}</span>
                                        @if($user->is_independent)
                                            <br><span class="badge mt-1" style="background:rgba(255,193,7,0.15); color:#ffc107; font-size:0.65rem;">
                                                <i class="bi bi-person-workspace me-1"></i>Independent
                                            </span>
                                        @endif
                                    @else
                                        <span class="text-muted small">No Role</span>
                                    @endif
                                </td>
                                <td>
                                    @php $compType = $user->compensation_type ?? 'commission'; @endphp
                                    <span class="badge-glass">
                                        <i class="bi bi-{{ $compType === 'salaried' ? 'cash-coin' : ($compType === 'hybrid' ? 'collection' : 'percent') }} me-1"></i>
                                        {{ ucfirst($compType) }}
                                    </span>
                                    @if($user->base_salary)
                                        <br><small class="text-muted">{{ currency($user->base_salary) }}/mo</small>
                                    @endif
                                </td>
                                <td>
                                    @if($user->is_active ?? true)
                                        <span class="toggle-status active"><i class="bi bi-check-circle-fill me-1"></i>Active</span>
                                    @else
                                        <span class="toggle-status inactive"><i class="bi bi-x-circle me-1"></i>Inactive</span>
                                    @endif
                                </td>
                                <td class="d-none d-md-table-cell text-muted small">
                                    {{ $user->created_at?->format('M d, Y') ?? 'N/A' }}
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('owner.users.edit', $user) }}" class="btn btn-sm btn-outline-info">
                                        <i class="bi bi-pencil me-1"></i>Edit
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-center mt-3">
                {{ $users->links() }}
            </div>
        </div>
    @else
        <div class="glass-panel text-center py-5">
            <i class="bi bi-people" style="font-size:3rem;opacity:0.3;"></i>
            <h5 class="mt-3">No users yet</h5>
            <p class="text-muted mb-3">Create your first staff member to get started.</p>
            <a href="{{ route('owner.users.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-person-plus me-1"></i>Create User
            </a>
        </div>
    @endif
</div>
@endsection
