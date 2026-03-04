@extends('layouts.app')
@section('title', 'Procurement Requests — ' . config('app.name'))

@section('content')
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="page-header mb-1"><i class="bi bi-cart3 me-2" style="color:var(--accent-primary);"></i>Procurement Requests</h1>
            <p class="page-subtitle">Manage inventory and service procurement across departments</p>
        </div>
        @can('create', App\Models\ProcurementRequest::class)
            <a href="{{ route('procurement.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>New Request</a>
        @endcan
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="glass-card fade-in delay-1">
        @if ($requests->isEmpty())
            <div class="text-center py-5">
                <i class="bi bi-cart-x" style="font-size:3rem; color:var(--text-muted);"></i>
                <p class="mt-3" style="color:var(--text-muted);">No procurement requests found.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th class="sortable-th">ID</th>
                            <th class="sortable-th">Department</th>
                            <th class="sortable-th">Type</th>
                            <th class="sortable-th">Requested By</th>
                            <th class="sortable-th">Status</th>
                            <th class="sortable-th">Items</th>
                            <th class="sortable-th">Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($requests as $request)
                            <tr>
                                <td><span class="code-tag">#{{ $request->id }}</span></td>
                                <td>{{ ucfirst($request->department) }}</td>
                                <td>
                                    <span class="badge {{ $request->type === 'inventory' ? 'badge-glass-primary' : 'badge-glass-info' }}">
                                        {{ ucfirst($request->type) }}
                                    </span>
                                </td>
                                <td>{{ $request->requester?->name ?? 'Unknown' }}</td>
                                <td>
                                    <span class="badge
                                        @if ($request->status === 'pending') badge-glass-warning
                                        @elseif ($request->status === 'approved') badge-glass-success
                                        @elseif ($request->status === 'rejected') badge-glass-danger
                                        @elseif ($request->status === 'received') badge-glass-info
                                        @else badge-glass-secondary
                                        @endif
                                    ">{{ ucfirst($request->status) }}</span>
                                </td>
                                <td>{{ $request->items->count() }} item(s)</td>
                                <td style="color:var(--text-muted);">{{ $request->created_at?->format('M d, Y') ?? 'N/A' }}</td>
                                <td>
                                    <a href="{{ route('procurement.show', $request) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye me-1"></i>View</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-3 px-3 pb-3">
                {{ $requests->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
