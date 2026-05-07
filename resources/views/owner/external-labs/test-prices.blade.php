@extends('layouts.app')
@section('title', $lab->name . ' — Test Prices — ' . config('app.name'))

@section('content')
<div class="fade-in">
    <div class="page-header mb-4">
        <div>
            <h1 class="page-title"><i class="bi bi-flask me-2"></i>{{ $lab->name }} — Test Prices</h1>
            <p class="text-muted mb-0">External lab referral pricing only</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('owner.external-labs.price-list.upload', $lab) }}" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-upload me-1"></i>Upload Price List
            </a>
            <a href="{{ route('owner.external-labs.edit', $lab) }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Back to Lab
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="alert alert-info d-flex align-items-center mb-4" role="alert">
        <i class="bi bi-info-circle-fill me-2 fs-5"></i>
        <div>
            <strong>These prices are for external lab referrals only</strong> and do not affect your
            onsite lab pricing in the service catalog. Onsite lab pricing remains completely isolated.
        </div>
    </div>

    <div class="glass-card">
        @if($testPrices->isEmpty())
            <div class="text-center py-5">
                <i class="bi bi-table fs-1 text-muted"></i>
                <p class="mt-3 text-muted">No test prices on record yet.</p>
                <p class="text-muted">Upload a price list to extract pricing automatically, or add entries manually.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Test Name</th>
                            <th>Code</th>
                            <th>Price</th>
                            <th>Commission %</th>
                            <th>Effective From</th>
                            <th>Valid Until</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($testPrices as $price)
                        <tr>
                            <td class="fw-medium">{{ $price->test_name }}</td>
                            <td><span class="text-muted">{{ $price->test_code ?? '—' }}</span></td>
                            <td>
                                <span class="fw-semibold">{{ $price->currency }} {{ number_format($price->price, 2) }}</span>
                            </td>
                            <td>
                                @if($price->commission_pct !== null)
                                    {{ $price->commission_pct }}%
                                    <small class="text-muted d-block">(overrides lab default)</small>
                                @else
                                    <span class="text-muted">Lab default ({{ $lab->mou_commission_pct ?? 0 }}%)</span>
                                @endif
                            </td>
                            <td>{{ $price->effective_from->format('d M Y') }}</td>
                            <td>
                                @if($price->effective_until)
                                    {{ $price->effective_until->format('d M Y') }}
                                    @if($price->effective_until->isPast())
                                        <span class="badge bg-danger ms-1">Expired</span>
                                    @endif
                                @else
                                    <span class="text-muted">No expiry</span>
                                @endif
                            </td>
                            <td>
                                @if($price->is_active)
                                    <span class="badge bg-success">Active</span>
                                @else
                                    <span class="badge bg-secondary">Inactive</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $testPrices->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
