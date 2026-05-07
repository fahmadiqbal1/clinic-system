@extends('layouts.app')
@section('title', 'Vendor Portal — ' . config('app.name'))

@section('content')
<div class="container mt-4">
    <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2 fade-in">
        <div>
            <h2 class="mb-1"><i class="bi bi-building me-2" style="color:var(--accent-primary);"></i>{{ $vendor->name }}</h2>
            <p class="page-subtitle mb-0">Vendor Portal · {{ ucfirst($vendor->category ?? 'General') }}</p>
        </div>
        <a href="{{ route('vendor.price-lists.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-upload me-1"></i>Upload Price List
        </a>
    </div>

    {{-- MOU Status --}}
    @if($vendor->mou_valid_until)
    <div class="alert {{ $vendor->mou_valid_until->isPast() ? 'alert-danger' : 'alert-info' }} fade-in">
        <i class="bi bi-file-earmark-text me-2"></i>
        MOU valid until <strong>{{ $vendor->mou_valid_until->format('M d, Y') }}</strong>
        @if($vendor->mou_valid_until->isPast())
            — <strong>Expired.</strong> Please contact the clinic to renew.
        @elseif($vendor->mou_valid_until->diffInDays(now()) < 30)
            — Expires in {{ $vendor->mou_valid_until->diffForHumans() }}.
        @endif
        @if($vendor->mou_commission_pct)
            &nbsp;·&nbsp; Commission rate: <strong>{{ $vendor->mou_commission_pct }}%</strong>
        @endif
    </div>
    @endif

    {{-- Summary Cards --}}
    <div class="row g-3 mb-4 fade-in">
        <div class="col-6 col-md-4">
            <div class="glass-card text-center p-3">
                <div class="metric-value text-warning">{{ $pendingCount }}</div>
                <div class="metric-label">Processing</div>
            </div>
        </div>
        <div class="col-6 col-md-4">
            <div class="glass-card text-center p-3">
                <div class="metric-value text-success">{{ $appliedCount }}</div>
                <div class="metric-label">Applied</div>
            </div>
        </div>
        <div class="col-6 col-md-4">
            <div class="glass-card text-center p-3">
                <div class="metric-value text-danger">{{ $flaggedCount }}</div>
                <div class="metric-label">Need Review</div>
            </div>
        </div>
    </div>

    {{-- Recent Uploads --}}
    <div class="glass-card fade-in">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="mb-0"><i class="bi bi-clock-history me-2"></i>Recent Price Lists</h6>
            <a href="{{ route('vendor.price-lists.index') }}" class="btn btn-outline-secondary btn-sm">View All</a>
        </div>
        @if($recentPriceLists->isEmpty())
            <p class="text-muted mb-0">No price lists uploaded yet. <a href="{{ route('vendor.price-lists.create') }}">Upload your first price list</a>.</p>
        @else
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead><tr><th>File</th><th>Type</th><th>Status</th><th>Uploaded</th><th></th></tr></thead>
                <tbody>
                @foreach($recentPriceLists as $pl)
                <tr>
                    <td>{{ $pl->original_filename }}</td>
                    <td><span class="badge bg-secondary">{{ strtoupper($pl->file_type) }}</span></td>
                    <td>
                        @php $statusColors = ['pending'=>'warning','processing'=>'info','extracted'=>'primary','applied'=>'success','flagged'=>'danger','failed'=>'danger']; @endphp
                        <span class="badge bg-{{ $statusColors[$pl->status] ?? 'secondary' }}">{{ ucfirst($pl->status) }}</span>
                    </td>
                    <td>{{ $pl->created_at->diffForHumans() }}</td>
                    <td><a href="{{ route('vendor.price-lists.show', $pl) }}" class="btn btn-outline-info btn-xs">View</a></td>
                </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>
@endsection
