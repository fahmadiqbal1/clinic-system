@extends('layouts.app')
@section('title', 'Lab Test #' . $invoice->id . ' — ' . config('app.name'))

@section('content')
<div class="container mt-4">
    {{-- Print Header --}}
    <div class="print-header">
        <h2>{{ config('app.name') }}</h2>
        <p>Lab Test #{{ $invoice->id }} &mdash; {{ $invoice->created_at?->format('M d, Y') }}</p>
    </div>

    <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2 fade-in">
        <div>
            <h2 class="mb-1"><i class="bi bi-droplet me-2" style="color:var(--accent-info);"></i>Lab Test #{{ $invoice->id }}</h2>
            <p class="page-subtitle mb-0">Laboratory Service</p>
        </div>
        <div class="d-flex gap-2 no-print">
            <button onclick="window.print()" class="btn btn-outline-info btn-sm" data-no-disable="true"><i class="bi bi-printer me-1"></i>Print</button>
            <a href="{{ route('laboratory.invoices.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back to Tests</a>
        </div>
    </div>

    {{-- Work Completed Banner --}}
    @if($invoice->isPaid() && $invoice->isWorkCompleted())
    <div class="alert d-flex align-items-center mb-4 fade-in" style="background:rgba(var(--accent-success-rgb),0.15); border:1px solid var(--accent-success); color:var(--accent-success); border-radius:var(--radius-md);">
        <i class="bi bi-check-circle-fill me-3" style="font-size:1.5rem;"></i>
        <div>
            <strong>Work Completed</strong><br>
            <span style="color:var(--text-muted); font-size:0.9rem;">Lab test has been completed and revenue distributed. No further action required.</span>
        </div>
    </div>
    @endif

    {{-- Status & Info --}}
    <div class="card mb-4 fade-in delay-1">
        <div class="card-header"><i class="bi bi-info-circle me-2" style="color:var(--accent-info);"></i>Test Details</div>
        <div class="card-body">
            <div class="info-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px,1fr));">
                <div class="info-grid-item">
                    <span class="info-label">Status</span>
                    @php $sStyle = match($invoice->status) { 'completed' => 'background:rgba(var(--accent-success-rgb),0.15);color:var(--accent-success);', 'paid' => 'background:rgba(var(--accent-primary-rgb),0.15);color:var(--accent-primary);', 'cancelled' => 'background:rgba(var(--accent-danger-rgb),0.15);color:var(--accent-danger);', default => 'background:rgba(var(--accent-warning-rgb),0.15);color:var(--accent-warning);', }; @endphp
                    <span class="badge-glass" style="{{ $sStyle }}">{{ ucfirst($invoice->status ?? 'pending') }}</span>
                </div>
                <div class="info-grid-item">
                    <span class="info-label">Order Date</span>
                    <span class="info-value">{{ $invoice->created_at?->format('M d, Y H:i') ?? 'N/A' }}</span>
                </div>
                <div class="info-grid-item">
                    <span class="info-label">Patient Name</span>
                    <span class="info-value">{{ $invoice->patient?->first_name }} {{ $invoice->patient?->last_name }}</span>
                </div>
                <div class="info-grid-item">
                    <span class="info-label">Test Name</span>
                    <span class="info-value">{{ $invoice->service_name ?? 'N/A' }}</span>
                </div>
                <div class="info-grid-item">
                    <span class="info-label">Payment Status</span>
                    @php
                        $payStyle = match(true) {
                            $invoice->status === 'paid' => 'background:rgba(var(--accent-success-rgb),0.15);color:var(--accent-success);',
                            in_array($invoice->status, ['completed']) => 'background:rgba(var(--accent-info-rgb),0.15);color:var(--accent-info);',
                            default => 'background:rgba(var(--accent-warning-rgb),0.15);color:var(--accent-warning);',
                        };
                    @endphp
                    <span class="badge-glass" style="{{ $payStyle }}">{{ $invoice->status === 'paid' ? 'Paid' : 'Unpaid' }}</span>
                </div>
                @if($invoice->prescribing_doctor_id)
                <div class="info-grid-item">
                    <span class="info-label">Prescribing Doctor</span>
                    <span class="info-value">{{ $invoice->prescribingDoctor?->name ?? 'N/A' }}</span>
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Invoice Items Breakdown (multi-test) --}}
    @if($invoice->items->count() > 0)
    <div class="card mb-4 fade-in delay-1">
        <div class="card-header"><i class="bi bi-list-check me-2" style="color:var(--accent-warning);"></i>Ordered Tests ({{ $invoice->items->count() }})</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Test</th>
                            <th>Code</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($invoice->items as $idx => $item)
                            <tr>
                                <td style="color:var(--text-muted);">{{ $idx + 1 }}</td>
                                <td class="fw-medium">{{ $item->description }}</td>
                                <td><span class="code-tag">{{ $item->serviceCatalog?->code ?? '—' }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    {{-- Lab Report --}}
    <div class="card mb-4 fade-in delay-2">
        <div class="card-header"><i class="bi bi-file-earmark-medical me-2" style="color:var(--accent-success);"></i>Lab Report</div>
        <div class="card-body">
            @if($invoice->report_text)
                <div class="p-3 rounded mb-3" style="background:var(--glass-bg); border:1px solid var(--glass-border);">
                    {!! nl2br(e($invoice->report_text)) !!}
                </div>
            @else
                <p style="color:var(--text-muted);">No report submitted yet.</p>
            @endif

            @if(in_array($invoice->status, ['pending', 'in_progress']) || ($invoice->status === 'paid' && !$invoice->isWorkCompleted()))
                <form action="{{ route('laboratory.invoices.save-report', $invoice) }}" method="POST" class="mt-3">
                    @csrf
                    <div class="mb-3">
                        <label for="report" class="form-label">Write Report</label>
                        <textarea name="report" id="report" class="form-control" rows="5" required minlength="3">{{ old('report', $invoice->report_text) }}</textarea>
                        @error('report')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Report</button>
                </form>
            @endif
        </div>
    </div>

    {{-- Structured Lab Results --}}
    @php
        // ---- Normalise lab_results into grouped format ----
        // New format: {"<item_id>": [{test_name,result,unit,reference_range}, ...]}
        // Old format: [{test_name,result,unit,reference_range}, ...]  (flat array)
        $rawResults = $invoice->lab_results ?? [];
        $isLegacyFlat = is_array($rawResults) && array_is_list($rawResults);

        if ($isLegacyFlat && count($rawResults) > 0) {
            // Wrap legacy flat results under "general" key
            $groupedResults = ['general' => $rawResults];
        } elseif (!$isLegacyFlat) {
            $groupedResults = (array) $rawResults;
        } else {
            $groupedResults = [];
        }

        // Map item IDs to item models for section headers
        $itemMap = $invoice->items->keyBy('id');
        $totalParamCount = collect($groupedResults)->sum(fn ($rows) => count($rows));

        // Build sections: one per invoice item (or a single "general" section)
        $sections = [];
        if ($invoice->items->count() > 0) {
            foreach ($invoice->items as $item) {
                $key = (string) $item->id;
                $defaults = $item->serviceCatalog?->default_parameters ?? [];
                $saved = $groupedResults[$key] ?? [];
                $sections[] = [
                    'key'        => $key,
                    'label'      => $item->description ?? $item->serviceCatalog?->name ?? 'Test',
                    'code'       => $item->serviceCatalog?->code ?? '',
                    'defaults'   => $defaults,
                    'saved'      => $saved,
                ];
            }
            // Include any "general" legacy results as a separate section
            if (isset($groupedResults['general'])) {
                $sections[] = [
                    'key'      => 'general',
                    'label'    => 'General Results',
                    'code'     => '',
                    'defaults' => [],
                    'saved'    => $groupedResults['general'],
                ];
            }
        } else {
            // No items — single generic section
            $sections[] = [
                'key'      => 'general',
                'label'    => $invoice->service_name ?? 'Lab Test',
                'code'     => '',
                'defaults' => [],
                'saved'    => $groupedResults['general'] ?? [],
            ];
        }

        $canEdit = in_array($invoice->status, ['pending', 'in_progress'])
                || ($invoice->status === 'paid' && $invoice->performed_by_user_id && !$invoice->isWorkCompleted());
    @endphp

    <div class="card mb-4 fade-in delay-2">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-table me-2" style="color:var(--accent-warning);"></i>Structured Results</span>
            @if($totalParamCount > 0)
                <span class="badge bg-secondary">{{ $totalParamCount }} parameter(s) recorded</span>
            @endif
        </div>
        <div class="card-body">
            {{-- ============ READ-ONLY DISPLAY (only when form is hidden) ============ --}}
            @if($totalParamCount > 0 && !$canEdit)
                @foreach($sections as $sec)
                    @if(count($sec['saved']) > 0)
                    <div class="mb-4">
                        <h6 class="fw-semibold mb-2">
                            <i class="bi bi-clipboard2-pulse me-1" style="color:var(--accent-info);"></i>
                            {{ $sec['label'] }}
                            @if($sec['code'])
                                <span class="code-tag ms-1">{{ $sec['code'] }}</span>
                            @endif
                        </h6>
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead>
                                    <tr>
                                        <th>Parameter</th>
                                        <th>Result</th>
                                        <th>Unit</th>
                                        <th>Reference Range</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($sec['saved'] as $row)
                                        <tr>
                                            <td class="fw-medium">{{ $row['test_name'] ?? '' }}</td>
                                            <td class="fw-semibold" style="color:var(--accent-primary);">{{ $row['result'] ?? '' }}</td>
                                            <td style="color:var(--text-muted);">{{ $row['unit'] ?? '—' }}</td>
                                            <td style="color:var(--text-muted);">{{ $row['reference_range'] ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    @endif
                @endforeach
            @elseif(!$canEdit)
                <p style="color:var(--text-muted);" class="mb-3"><i class="bi bi-info-circle me-1"></i>No structured results recorded yet.</p>
            @endif

            {{-- ============ EDITABLE FORM ============ --}}
            @if($canEdit)
                <form action="{{ route('laboratory.invoices.save-results', $invoice) }}" method="POST" id="labResultsForm">
                    @csrf

                    <div class="accordion" id="resultsAccordion">
                        @foreach($sections as $sIdx => $sec)
                        @php
                            // Use saved results if available, otherwise pre-populate from service catalog defaults
                            $rows = count($sec['saved']) > 0
                                ? $sec['saved']
                                : collect($sec['defaults'])->map(fn ($d) => [
                                    'test_name'       => $d['test_name'] ?? '',
                                    'result'          => '',
                                    'unit'            => $d['unit'] ?? '',
                                    'reference_range' => $d['reference_range'] ?? '',
                                ])->toArray();
                            if (count($rows) === 0) {
                                $rows = [['test_name' => '', 'result' => '', 'unit' => '', 'reference_range' => '']];
                            }
                        @endphp
                        <div class="accordion-item" style="background:var(--glass-bg); border-color:var(--glass-border);">
                            <h2 class="accordion-header">
                                <button class="accordion-button {{ $sIdx > 0 ? 'collapsed' : '' }}" type="button"
                                        data-bs-toggle="collapse" data-bs-target="#section-{{ $sec['key'] }}"
                                        style="background:var(--glass-bg); color:var(--text-primary);">
                                    <i class="bi bi-clipboard2-pulse me-2" style="color:var(--accent-info);"></i>
                                    {{ $sec['label'] }}
                                    @if($sec['code'])
                                        <span class="code-tag ms-2">{{ $sec['code'] }}</span>
                                    @endif
                                    <span class="badge bg-secondary ms-auto me-2 param-count" data-section="{{ $sec['key'] }}">{{ count($rows) }}</span>
                                </button>
                            </h2>
                            <div id="section-{{ $sec['key'] }}" class="accordion-collapse collapse {{ $sIdx === 0 ? 'show' : '' }}" data-bs-parent="#resultsAccordion">
                                <div class="accordion-body p-2">
                                    <div class="table-responsive">
                                        <table class="table table-sm mb-0 results-table" data-section="{{ $sec['key'] }}">
                                            <thead>
                                                <tr>
                                                    <th style="min-width:180px;">Parameter *</th>
                                                    <th style="min-width:140px;">Result *</th>
                                                    <th style="min-width:80px;">Unit</th>
                                                    <th style="min-width:120px;">Ref. Range</th>
                                                    <th style="width:40px;"></th>
                                                </tr>
                                            </thead>
                                            <tbody class="results-body" data-section="{{ $sec['key'] }}">
                                                @foreach($rows as $rIdx => $row)
                                                <tr>
                                                    <td><input type="text" name="results[{{ $sec['key'] }}][{{ $rIdx }}][test_name]" class="form-control form-control-sm" value="{{ $row['test_name'] }}" placeholder="e.g. Hemoglobin" required></td>
                                                    <td><input type="text" name="results[{{ $sec['key'] }}][{{ $rIdx }}][result]" class="form-control form-control-sm" value="{{ $row['result'] }}" placeholder="e.g. 14.2" required></td>
                                                    <td><input type="text" name="results[{{ $sec['key'] }}][{{ $rIdx }}][unit]" class="form-control form-control-sm" value="{{ $row['unit'] }}" placeholder="g/dL"></td>
                                                    <td><input type="text" name="results[{{ $sec['key'] }}][{{ $rIdx }}][reference_range]" class="form-control form-control-sm" value="{{ $row['reference_range'] }}" placeholder="12.0–17.5"></td>
                                                    <td><button type="button" class="btn btn-outline-danger btn-sm remove-row"><i class="bi bi-x"></i></button></td>
                                                </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                    <button type="button" class="btn btn-outline-secondary btn-sm mt-2 add-row-btn" data-section="{{ $sec['key'] }}">
                                        <i class="bi bi-plus-circle me-1"></i>Add Parameter
                                    </button>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>

                    <div class="d-flex gap-2 mt-3">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save All Results</button>
                    </div>
                </form>

                <script>
                    document.addEventListener('DOMContentLoaded', function () {
                        // Track row indices per section
                        const sectionIndices = {};
                        document.querySelectorAll('.results-body').forEach(tbody => {
                            sectionIndices[tbody.dataset.section] = tbody.children.length;
                        });

                        // Add row
                        document.querySelectorAll('.add-row-btn').forEach(btn => {
                            btn.addEventListener('click', function () {
                                const section = this.dataset.section;
                                const tbody = document.querySelector(`.results-body[data-section="${section}"]`);
                                const idx = sectionIndices[section]++;
                                const tr = document.createElement('tr');
                                tr.innerHTML = `
                                    <td><input type="text" name="results[${section}][${idx}][test_name]" class="form-control form-control-sm" required></td>
                                    <td><input type="text" name="results[${section}][${idx}][result]" class="form-control form-control-sm" required></td>
                                    <td><input type="text" name="results[${section}][${idx}][unit]" class="form-control form-control-sm"></td>
                                    <td><input type="text" name="results[${section}][${idx}][reference_range]" class="form-control form-control-sm"></td>
                                    <td><button type="button" class="btn btn-outline-danger btn-sm remove-row"><i class="bi bi-x"></i></button></td>
                                `;
                                tbody.appendChild(tr);
                                updateParamCount(section);
                            });
                        });

                        // Remove row (delegated)
                        document.getElementById('labResultsForm').addEventListener('click', function (e) {
                            if (e.target.closest('.remove-row')) {
                                const row = e.target.closest('tr');
                                const tbody = row.closest('.results-body');
                                if (tbody.children.length > 1) {
                                    row.remove();
                                    updateParamCount(tbody.dataset.section);
                                }
                            }
                        });

                        function updateParamCount(section) {
                            const badge = document.querySelector(`.param-count[data-section="${section}"]`);
                            const tbody = document.querySelector(`.results-body[data-section="${section}"]`);
                            if (badge && tbody) {
                                badge.textContent = tbody.children.length;
                            }
                        }
                    });
                </script>
            @endif
        </div>
    </div>

    {{-- MedGemma AI Second Opinion --}}
    @php
        $labHasResults = !empty($invoice->lab_results);
        $labHasReport = !empty($invoice->report_text);
        if (!$labHasResults && !$labHasReport) {
            $labReadinessNote = '<strong>Tip:</strong> Save your structured results and/or report text first, then request AI analysis for the most accurate second opinion.';
        } elseif (!$labHasResults) {
            $labReadinessNote = '<strong>Tip:</strong> Structured test results have not been entered yet. Add results for a more detailed AI interpretation.';
        } elseif (!$labHasReport) {
            $labReadinessNote = 'Results are ready for analysis. You can also add your report text for MedGemma to verify your findings.';
        } else {
            $labReadinessNote = null;
        }
    @endphp
    @include('components.ai-analysis.card', [
        'analyses' => $aiAnalyses,
        'formAction' => route('ai-analysis.lab', $invoice),
        'contextLabel' => 'lab results',
        'readinessNote' => $labReadinessNote,
    ])

    {{-- Actions --}}
    <div class="d-flex gap-2 mb-4 fade-in delay-3">
        @if($invoice->status === 'pending')
            <div class="alert alert-warning mb-0 d-flex align-items-center gap-2">
                <i class="bi bi-exclamation-triangle"></i>
                <span><strong>Awaiting Payment</strong> — Invoice must be paid by the receptionist before work can begin.</span>
            </div>
        @endif
        @if($invoice->status === 'paid' && !$invoice->performed_by_user_id)
            <form action="{{ route('laboratory.invoices.start-work', $invoice) }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-warning" onclick="return confirm('Start work on this test?')"><i class="bi bi-play-circle me-1"></i>Start Work</button>
            </form>
        @endif
        @if($invoice->status === 'paid' && $invoice->performed_by_user_id && $invoice->report_text && !$invoice->isWorkCompleted())
            <form action="{{ route('laboratory.invoices.mark-complete', $invoice) }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-success" onclick="return confirm('Mark this test as completed?')"><i class="bi bi-check-circle me-1"></i>Mark Complete</button>
            </form>
        @endif
        <a href="{{ route('laboratory.invoices.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>
</div>
@endsection
