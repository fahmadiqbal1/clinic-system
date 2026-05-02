@extends('layouts.app')
@section('title', 'External Labs & Referrals — ' . config('app.name'))

@section('content')
<div class="fade-in">
    <div class="page-header">
        <div>
            <h1 class="page-title"><i class="bi bi-arrow-repeat me-2" style="color:var(--accent-info);"></i>External Labs & Referrals</h1>
            <p class="page-subtitle">Manage MOU partner labs and approve outbound referrals</p>
        </div>
        <a href="{{ route('owner.external-labs.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Add External Lab</a>
    </div>

    {{-- Pending referral approvals --}}
    @if($pendingReferrals->count() > 0)
    <div class="glass-card mb-4 fade-in delay-1" style="border-left:4px solid var(--accent-warning);">
        <h6 class="fw-semibold mb-3"><i class="bi bi-hourglass-split me-2" style="color:var(--accent-warning);"></i>Pending Referral Approvals ({{ $pendingReferrals->count() }})</h6>
        @foreach($pendingReferrals as $ref)
        <div class="p-3 rounded mb-3" style="background:var(--glass-bg); border:1px solid var(--glass-border);">
            <div class="row g-2 align-items-start">
                <div class="col-md-6">
                    <div class="fw-semibold">{{ $ref->patient->full_name }}</div>
                    <div class="small" style="color:var(--text-muted);">
                        <span class="badge-glass me-1">{{ ucfirst($ref->department) }}</span>
                        <strong>{{ $ref->test_name }}</strong> → <span style="color:var(--accent-info);">{{ $ref->externalLab->name }}</span>
                    </div>
                    <div class="small mt-1" style="color:var(--text-muted);">
                        By {{ $ref->referredBy->name }} · {{ $ref->created_at->diffForHumans() }}
                        @if($ref->reason) · <em>{{ $ref->reason }}</em> @endif
                    </div>
                    @if($ref->clinical_notes)
                    <div class="small mt-1 p-2 rounded" style="background:rgba(0,0,0,0.05);">{{ $ref->clinical_notes }}</div>
                    @endif
                </div>
                <div class="col-md-6">
                    <form method="POST" action="{{ route('owner.external-referrals.decide', $ref) }}" class="row g-2">
                        @csrf
                        <div class="col-6">
                            <label class="form-label small mb-1">Patient Price (PKR)</label>
                            <input type="number" name="patient_price" class="form-control form-control-sm" step="0.01" placeholder="{{ $ref->externalLab->mou_commission_pct > 0 ? 'MOU default' : 'Enter price' }}">
                        </div>
                        <div class="col-6">
                            <label class="form-label small mb-1">Our Commission %</label>
                            <input type="number" name="commission_pct" class="form-control form-control-sm" step="0.01" value="{{ $ref->externalLab->mou_commission_pct }}" min="0" max="100">
                        </div>
                        <div class="col-12">
                            <input type="text" name="owner_notes" class="form-control form-control-sm" placeholder="Notes (optional)">
                        </div>
                        <div class="col-12 d-flex gap-2">
                            <button type="submit" name="decision" value="approved" class="btn btn-sm btn-success"><i class="bi bi-check-lg me-1"></i>Approve</button>
                            <button type="submit" name="decision" value="rejected" class="btn btn-sm btn-outline-danger"><i class="bi bi-x me-1"></i>Reject</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @endif

    {{-- Partner Labs List --}}
    <div class="glass-card fade-in delay-2">
        <h6 class="fw-semibold mb-3"><i class="bi bi-building-check me-2"></i>MOU Partner Labs</h6>
        @if($labs->count() > 0)
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Lab</th>
                        <th>City</th>
                        <th>Contact</th>
                        <th>Commission %</th>
                        <th>Referrals</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($labs as $lab)
                    <tr>
                        <td>
                            <div class="fw-medium">{{ $lab->name }}</div>
                            @if($lab->short_name)<div class="small" style="color:var(--text-muted);">{{ $lab->short_name }}</div>@endif
                        </td>
                        <td>{{ $lab->city ?? '—' }}</td>
                        <td>
                            @if($lab->contact_name)<div class="small">{{ $lab->contact_name }}</div>@endif
                            @if($lab->contact_phone)<div class="small" style="color:var(--text-muted);">{{ $lab->contact_phone }}</div>@endif
                        </td>
                        <td class="fw-semibold" style="color:var(--accent-success);">{{ $lab->mou_commission_pct }}%</td>
                        <td>
                            <span class="fw-medium">{{ $lab->referrals_count }}</span>
                            @if($lab->pending_count > 0)
                                <span class="badge bg-warning text-dark ms-1">{{ $lab->pending_count }} pending</span>
                            @endif
                        </td>
                        <td>
                            @if($lab->is_active)
                                <span class="badge-glass" style="background:rgba(var(--accent-success-rgb),0.15);color:var(--accent-success);">Active</span>
                            @else
                                <span class="badge-glass">Inactive</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('owner.external-labs.edit', $lab) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="empty-state py-4">
            <i class="bi bi-building" style="font-size:2rem;opacity:0.3;"></i>
            <h6 class="mt-3 mb-1">No external labs yet</h6>
            <p class="small mb-2" style="color:var(--text-muted);">Add your MOU partner labs (Chugtai, IDC, Agha Khan, etc.)</p>
            <a href="{{ route('owner.external-labs.create') }}" class="btn btn-sm btn-primary">Add First Lab</a>
        </div>
        @endif
    </div>
</div>
@endsection
