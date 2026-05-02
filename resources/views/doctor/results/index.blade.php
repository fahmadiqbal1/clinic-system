@extends('layouts.app')
@section('title', 'Investigation Results — ' . config('app.name'))

@section('content')
<div class="fade-in">
    <div class="page-header">
        <div>
            <h1 class="page-title"><i class="bi bi-file-earmark-medical me-2" style="color:var(--accent-success);"></i>Investigation Results</h1>
            <p class="page-subtitle">Completed lab and radiology results for your patients</p>
        </div>
    </div>

    {{-- Filters --}}
    <div class="glass-card mb-4">
        <form method="GET" action="{{ route('doctor.results.index') }}" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small mb-1">Type</label>
                <select name="department" class="form-select form-select-sm">
                    <option value="">All Types</option>
                    <option value="lab" {{ ($filters['department'] ?? '') === 'lab' ? 'selected' : '' }}>Laboratory</option>
                    <option value="radiology" {{ ($filters['department'] ?? '') === 'radiology' ? 'selected' : '' }}>Radiology</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">From</label>
                <input type="date" name="from" class="form-control form-control-sm" value="{{ $filters['from'] ?? '' }}">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">To</label>
                <input type="date" name="to" class="form-control form-control-sm" value="{{ $filters['to'] ?? '' }}">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-sm btn-outline-primary">Filter</button>
                <a href="{{ route('doctor.results.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>

    @if($results->count() > 0)
    <div class="glass-card fade-in delay-1">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Patient</th>
                        <th>Test / Study</th>
                        <th>Type</th>
                        <th>Completed</th>
                        <th>Results</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($results as $result)
                    <tr>
                        <td class="fw-medium">{{ $result->patient?->full_name ?? '—' }}</td>
                        <td>{{ $result->service_name }}</td>
                        <td>
                            @if($result->department === 'lab')
                                <span class="badge-glass" style="background:rgba(var(--accent-info-rgb),0.15);color:var(--accent-info);"><i class="bi bi-droplet me-1"></i>Lab</span>
                            @else
                                <span class="badge-glass" style="background:rgba(var(--accent-warning-rgb),0.15);color:var(--accent-warning);"><i class="bi bi-broadcast me-1"></i>Radiology</span>
                            @endif
                        </td>
                        <td style="color:var(--text-secondary);">{{ $result->updated_at->format('M d, Y') }}</td>
                        <td>
                            @if($result->department === 'lab' && $result->lab_results)
                                <span class="text-success small"><i class="bi bi-table me-1"></i>Structured results</span>
                            @elseif($result->report_text)
                                <span class="text-success small"><i class="bi bi-file-text me-1"></i>Report available</span>
                            @else
                                <span style="color:var(--text-muted);" class="small">—</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('doctor.results.show', $result) }}" class="btn btn-sm btn-outline-primary">View Results</a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-center mt-3">
            {{ $results->links() }}
        </div>
    </div>
    @else
    <div class="empty-state fade-in delay-1">
        <i class="bi bi-file-earmark-medical" style="font-size:2.5rem;opacity:0.3;"></i>
        <h6 class="mt-3 mb-1">No results available</h6>
        <p class="small mb-0" style="color:var(--text-muted);">Completed lab and radiology reports will appear here.</p>
    </div>
    @endif
</div>
@endsection
