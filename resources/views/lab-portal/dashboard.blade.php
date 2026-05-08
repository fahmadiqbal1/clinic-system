@extends('layouts.app')
@section('title', $lab->name . ' — Partner Portal')

@section('content')
<div class="fade-in">
    <div class="page-header mb-4">
        <div>
            <h1 class="page-title"><i class="bi bi-building-check me-2" style="color:var(--accent-info);"></i>{{ $lab->name }}</h1>
            <p class="page-subtitle">Partner Lab Portal &mdash; {{ $lab->city }}</p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row g-4">
        {{-- Left column: Lab details + MOU --}}
        <div class="col-md-5">
            {{-- Lab Info --}}
            <div class="glass-card mb-4">
                <h6 class="form-section-title"><i class="bi bi-info-circle me-2"></i>Lab Details</h6>
                <div class="info-grid">
                    <div class="info-grid-item">
                        <span class="info-label">Name</span>
                        <span class="info-value fw-medium">{{ $lab->name }}</span>
                    </div>
                    @if($lab->short_name)
                    <div class="info-grid-item">
                        <span class="info-label">Short Name</span>
                        <span class="info-value">{{ $lab->short_name }}</span>
                    </div>
                    @endif
                    @if($lab->city)
                    <div class="info-grid-item">
                        <span class="info-label">City</span>
                        <span class="info-value">{{ $lab->city }}</span>
                    </div>
                    @endif
                    @if($lab->contact_phone)
                    <div class="info-grid-item">
                        <span class="info-label">Phone</span>
                        <span class="info-value">{{ $lab->contact_phone }}</span>
                    </div>
                    @endif
                    @if($lab->mou_commission_pct)
                    <div class="info-grid-item">
                        <span class="info-label">MOU Commission</span>
                        <span class="info-value">{{ $lab->mou_commission_pct }}%</span>
                    </div>
                    @endif
                    <div class="info-grid-item">
                        <span class="info-label">Status</span>
                        <span class="info-value">
                            @if($lab->is_active)
                                <span class="badge-glass" style="background:rgba(var(--accent-success-rgb),0.15);color:var(--accent-success);"><i class="bi bi-circle-fill me-1" style="font-size:0.5rem;"></i>Active</span>
                            @else
                                <span class="badge-glass" style="background:rgba(var(--accent-danger-rgb),0.15);color:var(--accent-danger);">Inactive</span>
                            @endif
                        </span>
                    </div>
                </div>
            </div>

            {{-- MOU Document --}}
            <div class="glass-card mb-4">
                <h6 class="form-section-title"><i class="bi bi-file-earmark-text me-2"></i>MOU Agreement</h6>
                @if($lab->mou_document_path)
                    <p class="small mb-3" style="color:var(--text-secondary);">Your signed MOU is on file. Download a copy at any time.</p>
                    <a href="{{ route('lab-portal.mou') }}" class="btn btn-outline-primary btn-sm" target="_blank">
                        <i class="bi bi-download me-1"></i>Download MOU
                    </a>
                @else
                    <p class="small mb-0" style="color:var(--text-muted);">No MOU document uploaded yet. Please contact the clinic to arrange your agreement.</p>
                @endif
            </div>

            {{-- Referrals summary --}}
            @if($referrals->count() > 0)
            <div class="glass-card">
                <h6 class="form-section-title"><i class="bi bi-arrow-repeat me-2"></i>Recent Referrals</h6>
                @foreach($referrals as $ref)
                <div class="d-flex justify-content-between align-items-center py-2" style="border-bottom:1px solid var(--glass-border);">
                    <div>
                        <div class="small fw-medium">{{ $ref->patient?->first_name }} {{ $ref->patient?->last_name }}</div>
                        <div class="small" style="color:var(--text-muted);">{{ $ref->test_name ?? '—' }} · {{ $ref->created_at->format('d M Y') }}</div>
                    </div>
                    <span class="badge-glass @if($ref->status === 'approved') text-success @elseif($ref->status === 'rejected') text-danger @else text-warning @endif">
                        {{ ucfirst($ref->status) }}
                    </span>
                </div>
                @endforeach
            </div>
            @endif
        </div>

        {{-- Right column: Price lists --}}
        <div class="col-md-7">
            {{-- Upload new price list --}}
            <div class="glass-card mb-4" style="border:1px solid rgba(var(--accent-primary-rgb),0.25);">
                <h6 class="form-section-title"><i class="bi bi-cloud-upload me-2" style="color:var(--accent-primary);"></i>Upload Price List</h6>
                <p class="small mb-3" style="color:var(--text-secondary);">Upload your latest test price list (PDF, image, or CSV). The clinic team will review and update the system accordingly.</p>
                <form method="POST" action="{{ route('lab-portal.price-list') }}" enctype="multipart/form-data">
                    @csrf
                    @error('price_list_file')
                        <div class="alert alert-danger py-2 small mb-2">{{ $message }}</div>
                    @enderror
                    <div class="d-flex gap-2 align-items-center flex-wrap">
                        <input type="file" name="price_list_file" class="form-control form-control-sm" style="max-width:320px;"
                               accept=".pdf,.jpg,.jpeg,.png,.csv" required>
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="bi bi-upload me-1"></i>Submit
                        </button>
                    </div>
                    <div class="form-text mt-1">Accepted: PDF, JPG, PNG, CSV · Max 20 MB</div>
                </form>
            </div>

            {{-- Price list history --}}
            <div class="glass-card">
                <h6 class="form-section-title"><i class="bi bi-clock-history me-2"></i>Submission History</h6>
                @if($priceLists->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead>
                            <tr>
                                <th>File</th>
                                <th>Submitted</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($priceLists as $pl)
                            <tr>
                                <td>
                                    <i class="bi bi-file-earmark me-1" style="color:var(--accent-info);"></i>
                                    {{ $pl->original_filename }}
                                </td>
                                <td style="color:var(--text-muted);">{{ $pl->created_at->format('d M Y H:i') }}</td>
                                <td>
                                    @if($pl->status === 'processed')
                                        <span class="badge bg-success">Processed</span>
                                    @elseif($pl->status === 'failed')
                                        <span class="badge bg-danger">Failed</span>
                                    @else
                                        <span class="badge bg-warning text-dark">Pending</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                    <p class="small mb-0" style="color:var(--text-muted);">No price lists submitted yet.</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
