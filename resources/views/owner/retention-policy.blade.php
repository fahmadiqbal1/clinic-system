@extends('layouts.app')
@section('title', 'Retention Policy — ' . config('app.name'))

@section('content')
<div class="container mt-4">
    <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4 fade-in">
        <div>
            <h2 class="mb-1"><i class="bi bi-clock-history me-2" style="color:var(--accent-primary);"></i>Data Retention Policy</h2>
            <p class="page-subtitle mb-0">Configure how long each audit event type is retained. Leave blank for indefinite retention.</p>
        </div>
        <a href="{{ route('owner.dashboard') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Dashboard
        </a>
    </div>

    @if(session('success'))
    <div class="alert alert-success fade-in">
        <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
    </div>
    @endif

    <form method="POST" action="{{ route('owner.retention-policy.update') }}">
        @csrf
        @method('PATCH')

        <div class="glass-card p-4 fade-in mb-4">
            <h5 class="mb-3"><i class="bi bi-table me-2"></i>Retention Schedule</h5>
            <p class="text-muted small mb-4">
                SOC 2 recommends indefinite retention for clinical records, 7 years for financial records,
                and 2 years for AI audit trails. Values here are advisory — automated purging is not active
                in v1. These settings are recorded in the evidence bundle.
            </p>

            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Event Type</th>
                            <th>Retention (years)</th>
                            <th class="text-muted small">Current value</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($policies as $key => $policy)
                        <tr>
                            <td>
                                <strong>{{ $policy['label'] }}</strong>
                                <div class="text-muted small">
                                    Default:
                                    @if($policy['default'] === null)
                                        Indefinite
                                    @else
                                        {{ $policy['default'] }} years
                                    @endif
                                </div>
                            </td>
                            <td style="width:200px">
                                <input
                                    type="number"
                                    name="{{ $key }}_years"
                                    class="form-control @error("{$key}_years") is-invalid @enderror"
                                    placeholder="Indefinite"
                                    min="1"
                                    max="100"
                                    value="{{ old("{$key}_years", $policy['years']) }}"
                                >
                                @error("{$key}_years")
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </td>
                            <td class="text-muted small">
                                @if($policy['years'] === null)
                                    <span class="badge bg-secondary">Indefinite</span>
                                @else
                                    <span class="badge bg-info text-dark">{{ $policy['years'] }} yr</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-floppy me-1"></i>Save Retention Policy
            </button>
            <a href="{{ route('owner.platform-settings.index') }}" class="btn btn-outline-secondary">
                Platform Settings
            </a>
        </div>
    </form>

    <div class="glass-card p-4 fade-in mt-4">
        <h5 class="mb-3"><i class="bi bi-file-earmark-zip me-2"></i>Export Evidence Bundle</h5>
        <p class="text-muted mb-2">
            Generate a SOC 2 evidence bundle (audit chain + AI invocations + chain-verify proof + flag snapshot)
            via the CLI:
        </p>
        <pre class="bg-dark text-light rounded p-3 small mb-0"><code>php artisan soc2:evidence --from=YYYY-MM-DD --to=YYYY-MM-DD</code></pre>
        <p class="text-muted small mt-2 mb-0">
            Output ZIP is written to <code>storage/app/soc2/</code>.
            Omit <code>--from</code> to export all history.
        </p>
    </div>
</div>
@endsection
