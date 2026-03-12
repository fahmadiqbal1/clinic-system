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

    @can('approve', App\Models\ProcurementRequest::class)
    <form action="{{ route('procurement.bulk-approve') }}" method="POST" id="bulkApproveForm">
        @csrf
        <div id="bulkBar" style="display:none;" class="alert d-flex align-items-center gap-3 mb-3 fade-in" style="background:rgba(var(--accent-success-rgb),0.12); border:1px solid var(--accent-success); border-radius:var(--radius-md);">
            <span id="bulkCount" class="fw-semibold" style="color:var(--accent-success);">0 selected</span>
            <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Approve all selected pending requests?')"><i class="bi bi-check-all me-1"></i>Bulk Approve Selected</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="clearSelection">Clear</button>
        </div>
    @endcan

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
                            @can('approve', App\Models\ProcurementRequest::class)
                            <th style="width:36px;"><input type="checkbox" id="selectAll" title="Select all pending"></th>
                            @endcan
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
                                @can('approve', App\Models\ProcurementRequest::class)
                                <td>
                                    @if($request->status === 'pending' && !$request->isChangeRequest())
                                        <input type="checkbox" name="ids[]" value="{{ $request->id }}" class="bulk-checkbox">
                                    @endif
                                </td>
                                @endcan
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
    @can('approve', App\Models\ProcurementRequest::class)
    </form>
    @endcan
</div>

@can('approve', App\Models\ProcurementRequest::class)
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const checkboxes = document.querySelectorAll('.bulk-checkbox');
    const selectAll = document.getElementById('selectAll');
    const bulkBar = document.getElementById('bulkBar');
    const bulkCount = document.getElementById('bulkCount');
    const clearBtn = document.getElementById('clearSelection');

    function updateBar() {
        const checked = document.querySelectorAll('.bulk-checkbox:checked').length;
        bulkBar.style.display = checked > 0 ? 'flex' : 'none';
        bulkCount.textContent = checked + ' selected';
        if (selectAll) selectAll.indeterminate = checked > 0 && checked < checkboxes.length;
    }

    checkboxes.forEach(cb => cb.addEventListener('change', updateBar));

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            checkboxes.forEach(cb => cb.checked = this.checked);
            updateBar();
        });
    }

    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            checkboxes.forEach(cb => cb.checked = false);
            if (selectAll) selectAll.checked = false;
            updateBar();
        });
    }
});
</script>
@endpush
@endcan
@endsection
