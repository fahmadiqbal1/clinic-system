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
            <a href="{{ route('invoices.pdf', $invoice) }}" class="btn btn-outline-success btn-sm" data-no-disable="true"><i class="bi bi-file-earmark-pdf me-1"></i>Download PDF</a>
            <button onclick="window.print()" class="btn btn-outline-info btn-sm" data-no-disable="true"><i class="bi bi-printer me-1"></i>Print</button>
            @if($invoice->isWorkCompleted() || $invoice->status === 'completed')
                <a href="{{ route('laboratory.invoices.report-pdf', $invoice) }}" class="btn btn-outline-success btn-sm"><i class="bi bi-file-earmark-pdf me-1"></i>Download PDF Report</a>
            @endif
            <button type="button" class="btn btn-outline-warning btn-sm" data-bs-toggle="modal" data-bs-target="#referExternalModal"><i class="bi bi-arrow-right-circle me-1"></i>Refer to External Lab</button>
            <a href="{{ route('laboratory.invoices.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back to Tests</a>
        </div>
    </div>

    {{-- External Referral Modal --}}
    @php $externalLabs = \App\Models\ExternalLab::where('is_active', true)->orderBy('name')->get(); @endphp
    <div class="modal fade" id="referExternalModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('external-referrals.store') }}">
                    @csrf
                    <input type="hidden" name="patient_id" value="{{ $invoice->patient_id }}">
                    <input type="hidden" name="invoice_id" value="{{ $invoice->id }}">
                    <input type="hidden" name="department" value="lab">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-arrow-right-circle me-2" style="color:var(--accent-warning);"></i>Refer to External Lab</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        @if($externalLabs->isEmpty())
                            <div class="alert alert-warning mb-0">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                No MOU partner labs configured.
                                @if(auth()->user()->hasRole('Owner'))
                                    <a href="{{ route('owner.external-labs.index') }}" class="alert-link">Add an external lab partner</a> to enable referrals.
                                @else
                                    Ask the owner to add MOU partner labs at <strong>Owner → External Labs</strong> first.
                                @endif
                            </div>
                        @else
                        <div class="mb-3">
                            <label class="form-label">External Lab <span class="text-danger">*</span></label>
                            <select name="external_lab_id" class="form-select" required>
                                <option value="">Select lab...</option>
                                @foreach($externalLabs as $lab)
                                    <option value="{{ $lab->id }}">{{ $lab->name }}{{ $lab->city ? ' — '.$lab->city : '' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Test / Service Name <span class="text-danger">*</span></label>
                            <input type="text" name="test_name" class="form-control" value="{{ $invoice->service_name }}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Reason for Referral</label>
                            <input type="text" name="reason" class="form-control" placeholder="e.g. Machine not operational, service unavailable">
                        </div>
                        <div class="mb-0">
                            <label class="form-label">Clinical Notes</label>
                            <textarea name="clinical_notes" class="form-control" rows="3" placeholder="Relevant clinical context for the external lab..."></textarea>
                        </div>
                        @endif
                    </div>
                    @if(!$externalLabs->isEmpty())
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning"><i class="bi bi-send me-1"></i>Submit for Approval</button>
                    </div>
                    @endif
                </form>
            </div>
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
                    <div class="mb-2">
                        <label class="form-label d-flex align-items-center gap-2">
                            Write Report
                            <button type="button" id="labSttBtn" class="btn btn-sm btn-outline-secondary ms-auto" style="display:none;" title="Dictate report">
                                <i class="bi bi-mic" id="labSttIcon"></i> <span id="labSttLabel">Dictate</span>
                            </button>
                            <span id="labSttStatus" class="badge bg-danger" style="display:none; font-size:0.7rem;">● REC</span>
                        </label>
                        {{-- Template chips --}}
                        <div class="d-flex flex-wrap gap-1 mb-2">
                            <small class="text-muted w-100">Insert template:</small>
                            @foreach([
                                'All parameters within normal limits.' => 'Normal',
                                'Results show borderline values. Clinical correlation recommended.' => 'Borderline',
                                'Abnormal findings noted. Please review with ordering physician.' => 'Abnormal',
                                'Haemoglobin low — consistent with anaemia. Further workup advised.' => 'Anaemia',
                                'Elevated WBC — possible infection or inflammatory process.' => 'Elevated WBC',
                                'Blood glucose elevated — recommend fasting repeat and HbA1c.' => 'High Glucose',
                                'Liver enzymes elevated. Clinical correlation and repeat testing recommended.' => 'Liver',
                                'Lipid panel abnormal — dietary review and cardiology follow-up advised.' => 'Lipids',
                            ] as $text => $label)
                            <button type="button" class="btn btn-xs btn-outline-secondary lab-template-btn" data-text="{{ $text }}">{{ $label }}</button>
                            @endforeach
                        </div>
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
                {{-- Embed defaults as JSON for the Fill Normal JS ── --}}
                <script>
                window.labSectionDefaults = @json(collect($sections)->keyBy('key')->map(fn($s) => $s['defaults']));
                </script>

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
                                    <div class="d-flex gap-2 mt-2">
                                        <button type="button" class="btn btn-outline-secondary btn-sm add-row-btn" data-section="{{ $sec['key'] }}">
                                            <i class="bi bi-plus-circle me-1"></i>Add Parameter
                                        </button>
                                        <button type="button" class="btn btn-outline-success btn-sm fill-normal-btn" data-section="{{ $sec['key'] }}" title="Auto-fill result fields using reference range midpoints">
                                            <i class="bi bi-lightning-fill me-1"></i>Fill All Normal
                                        </button>
                                    </div>
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

                        // ── Fill All Normal ────────────────────────────────────
                        document.querySelectorAll('.fill-normal-btn').forEach(btn => {
                            btn.addEventListener('click', function () {
                                const section = this.dataset.section;
                                const tbody   = document.querySelector(`.results-body[data-section="${section}"]`);
                                if (!tbody) return;

                                Array.from(tbody.querySelectorAll('tr')).forEach(row => {
                                    const resultInput = row.querySelector('[name*="[result]"]');
                                    const rangeInput  = row.querySelector('[name*="[reference_range]"]');
                                    if (!resultInput || resultInput.value.trim()) return; // skip if already filled

                                    const range = (rangeInput ? rangeInput.value : '').trim();
                                    if (!range) return;

                                    // Numeric range: "X-Y" or "X – Y"
                                    const numMatch = range.match(/^([\d.]+)\s*[-–]\s*([\d.]+)$/);
                                    if (numMatch) {
                                        const lo = parseFloat(numMatch[1]);
                                        const hi = parseFloat(numMatch[2]);
                                        const mid = ((lo + hi) / 2).toFixed(1);
                                        resultInput.value = mid;
                                        return;
                                    }

                                    // Single numeric: "< X" or "> X"
                                    const ltMatch = range.match(/^[<≤]\s*([\d.]+)$/);
                                    if (ltMatch) { resultInput.value = (parseFloat(ltMatch[1]) * 0.8).toFixed(1); return; }
                                    const gtMatch = range.match(/^[>≥]\s*([\d.]+)$/);
                                    if (gtMatch) { resultInput.value = (parseFloat(gtMatch[1]) * 1.1).toFixed(1); return; }

                                    // Text normals
                                    const lower = range.toLowerCase();
                                    if (lower.includes('negative'))          { resultInput.value = 'Negative'; return; }
                                    if (lower.includes('absent'))            { resultInput.value = 'Absent'; return; }
                                    if (lower.includes('not detected'))      { resultInput.value = 'Not Detected'; return; }
                                    if (lower.includes('normal'))            { resultInput.value = 'Normal'; return; }
                                    if (lower.includes('clear'))             { resultInput.value = 'Clear'; return; }
                                });

                                updateParamCount(section);
                            });
                        });
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
            <form id="labStartWorkForm" action="{{ route('laboratory.invoices.start-work', $invoice) }}" method="POST">@csrf</form>
            <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#labStartModal">
                <i class="bi bi-play-circle me-1"></i>Start Work
            </button>
        @endif
        @if($invoice->status === 'paid' && $invoice->performed_by_user_id && $invoice->report_text && !$invoice->isWorkCompleted())
            <form id="labCompleteForm" action="{{ route('laboratory.invoices.mark-complete', $invoice) }}" method="POST">@csrf</form>
            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#labCompleteModal">
                <i class="bi bi-check-circle me-1"></i>Mark Complete
            </button>
        @endif
        <a href="{{ route('laboratory.invoices.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>

    {{-- Start Work modal --}}
    <div class="modal fade" id="labStartModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-play-circle me-2" style="color:var(--accent-warning);"></i>Start Lab Work</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-1">You are about to begin work on:</p>
                    <p class="fw-semibold mb-0">{{ $invoice->service_name }} — Patient: {{ $invoice->patient?->first_name }} {{ $invoice->patient?->last_name }}</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-warning" onclick="document.getElementById('labStartWorkForm').submit()"><i class="bi bi-play-circle me-1"></i>Start Work</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Mark Complete modal --}}
    <div class="modal fade" id="labCompleteModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-color:rgba(var(--accent-success-rgb),0.5);">
                <div class="modal-header" style="background:rgba(var(--accent-success-rgb),0.1);">
                    <h5 class="modal-title"><i class="bi bi-check-circle me-2" style="color:var(--accent-success);"></i>Mark Test Complete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">Confirm that all of the following are done before marking complete:</p>
                    <ul class="mb-0">
                        <li>Report text has been written and saved</li>
                        <li>All structured result parameters have been entered</li>
                        <li>Results have been reviewed for accuracy</li>
                    </ul>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Go Back</button>
                    <button type="button" class="btn btn-success" onclick="document.getElementById('labCompleteForm').submit()"><i class="bi bi-check-circle me-1"></i>Confirm Complete</button>
                </div>
            </div>
        </div>
    </div>

    @include('components.invoice-print-layout', ['invoice' => $invoice])
</div>
@endsection

@push('styles')
@include('components.invoice-print-styles')
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {

    // ── Report template chips ───────────────────────────────────────────────
    document.querySelectorAll('.lab-template-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var ta = document.getElementById('report');
            if (!ta) return;
            var cur = ta.value.trim();
            ta.value = cur ? (cur + '\n' + btn.dataset.text) : btn.dataset.text;
        });
    });

    // ── STT for report ──────────────────────────────────────────────────────
    (function () {
        var SR = window.SpeechRecognition || window.webkitSpeechRecognition;
        if (!SR) return;
        var btn  = document.getElementById('labSttBtn');
        var icon = document.getElementById('labSttIcon');
        var lbl  = document.getElementById('labSttLabel');
        var stat = document.getElementById('labSttStatus');
        var ta   = document.getElementById('report');
        if (!btn || !ta) return;
        btn.style.display = '';
        var rec = new SR(); rec.continuous = true; rec.interimResults = true; rec.lang = 'en-US';
        var listening = false;
        rec.onresult = function (e) {
            var interim = '', final = '';
            for (var i = e.resultIndex; i < e.results.length; i++) {
                if (e.results[i].isFinal) final += e.results[i][0].transcript;
                else interim += e.results[i][0].transcript;
            }
            if (final) { var c = ta.value; ta.value = c + (c && !c.endsWith(' ') && !c.endsWith('\n') ? ' ' : '') + final; }
            stat.textContent = interim ? ('● ' + interim.substring(0,40) + (interim.length>40?'…':'')) : '● REC';
        };
        rec.onerror = function (e) { if (e.error==='no-speech') return; listening=false; setIdle(); if(e.error==='not-allowed') alert('Microphone access denied.'); };
        rec.onend   = function () { if (listening) { try{rec.start();}catch(e){} } else setIdle(); };
        function setIdle()      { icon.className='bi bi-mic'; lbl.textContent='Dictate'; stat.style.display='none'; btn.classList.remove('btn-danger'); btn.classList.add('btn-outline-secondary'); }
        function setRecording() { icon.className='bi bi-mic-fill'; lbl.textContent='Stop'; stat.style.display=''; stat.textContent='● REC'; btn.classList.remove('btn-outline-secondary'); btn.classList.add('btn-danger'); }
        btn.addEventListener('click', function () {
            if (listening) { listening=false; rec.stop(); } else { listening=true; setRecording(); try{rec.start();}catch(e){} }
        });
    }());

});
</script>
@if($invoice->isPaid() && $invoice->fbr_qr_code)
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" integrity="sha512-CNgIRecGo7nphbeZ04Sc13ka07paqdeTu0WR1IM4kNcpmBAUSHSe2s9qnDN7oD6eblnBHyH3P1pAzrBDxhxNSw==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script>
(function() {
    var c = document.getElementById('pi-qr-container');
    if (c) new QRCode(c, { text: {{ json_encode($invoice->fbr_qr_code) }}, width: 80, height: 80, colorDark: '#000', colorLight: '#fff', correctLevel: QRCode.CorrectLevel.M });
})();
</script>
@endif
@endpush
