@extends('layouts.app')
@section('title', 'My Price Lists — Vendor Portal')

@section('content')
<div class="container mt-4">
    <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2 fade-in">
        <div>
            <h2 class="mb-1"><i class="bi bi-list-check me-2" style="color:var(--accent-primary);"></i>Price Lists</h2>
            <p class="page-subtitle mb-0">{{ $vendor->name }} · All uploaded price lists</p>
        </div>
        <a href="{{ route('vendor.price-lists.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-upload me-1"></i>Upload New</a>
    </div>

    <div class="glass-card fade-in">
        @if($priceLists->isEmpty())
            <p class="text-muted mb-0">No price lists uploaded yet.</p>
        @else
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr><th>File</th><th>Type</th><th>Status</th><th>Items</th><th>Flagged</th><th>Uploaded</th><th>Applied</th><th></th></tr>
                </thead>
                <tbody>
                @foreach($priceLists as $pl)
                @php $statusColors = ['pending'=>'warning','processing'=>'info','extracted'=>'primary','applied'=>'success','flagged'=>'danger','failed'=>'danger']; @endphp
                <tr>
                    <td>{{ $pl->original_filename }}</td>
                    <td><span class="badge bg-secondary">{{ strtoupper($pl->file_type) }}</span></td>
                    <td><span class="badge bg-{{ $statusColors[$pl->status] ?? 'secondary' }}">{{ ucfirst($pl->status) }}</span></td>
                    <td>{{ $pl->item_count ?? '—' }}</td>
                    <td>{{ $pl->flagged_count ? '<span class="text-danger fw-bold">'.$pl->flagged_count.'</span>' : '—' }}</td>
                    <td>{{ $pl->created_at->format('M d, Y') }}</td>
                    <td>{{ $pl->applied_at?->format('M d, Y') ?? '—' }}</td>
                    <td><a href="{{ route('vendor.price-lists.show', $pl) }}" class="btn btn-outline-info btn-xs">View</a></td>
                </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        {{ $priceLists->links() }}
        @endif
    </div>
</div>
@endsection
