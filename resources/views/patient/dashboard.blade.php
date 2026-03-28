@extends('layouts.app')
@section('title', 'My Health — ' . config('app.name'))

@section('content')
<div class="container mt-4">
    <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2 fade-in">
        <div>
            <h2 class="mb-1"><i class="bi bi-heart-pulse me-2" style="color:var(--accent-primary);"></i>My Health Profile</h2>
            <p class="page-subtitle mb-0">{{ $patient->first_name }} {{ $patient->last_name }}</p>
        </div>
    </div>

    {{-- Check-In Banner --}}
    @if($patient->status === 'registered' && $patient->registered_at?->isToday())
    <div class="card mb-4 fade-in" style="border:2px solid var(--accent-success);">
        <div class="card-body d-flex align-items-center justify-content-between">
            <div>
                <h5 class="mb-1"><i class="bi bi-calendar-check me-2" style="color:var(--accent-success);"></i>Ready for check-in</h5>
                <p class="mb-0 text-muted">You have a visit registered today. Use the kiosk or check in here.</p>
            </div>
            <a href="{{ route('patient.checkin') }}" class="btn btn-success">Check In Now</a>
        </div>
    </div>
    @endif

    {{-- Patient Info --}}
    <div class="card mb-4 fade-in delay-1">
        <div class="card-header"><i class="bi bi-person-badge me-2" style="color:var(--accent-info);"></i>Personal Details</div>
        <div class="card-body">
            <div class="info-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px,1fr));">
                <div class="info-grid-item">
                    <span class="info-label">Phone</span>
                    <span class="info-value">{{ $patient->phone ?? 'N/A' }}</span>
                </div>
                <div class="info-grid-item">
                    <span class="info-label">Gender</span>
                    <span class="info-value">{{ $patient->gender ?? 'N/A' }}</span>
                </div>
                <div class="info-grid-item">
                    <span class="info-label">Date of Birth</span>
                    <span class="info-value">{{ $patient->date_of_birth?->format('d/m/Y') ?? 'N/A' }}</span>
                </div>
                @if($patient->doctor)
                <div class="info-grid-item">
                    <span class="info-label">Assigned Doctor</span>
                    <span class="info-value">{{ $patient->doctor->name }}</span>
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Latest Vitals — Animated Arc Rings --}}
    @php
        $latestVitals = $patient->triageVitals->first();
        // Thresholds: [normal_min, normal_max, borderline_max, absolute_max]
        $vitalDefs = [
            'bp_systolic'   => ['label'=>'BP Systolic', 'unit'=>'mmHg', 'icon'=>'bi-heart-pulse',  'min'=>90,  'max'=>180, 'normal'=>[90,120],  'borderline'=>[120,140], 'color_ok'=>'#34d399','color_warn'=>'#fbbf24','color_bad'=>'#f87171'],
            'temperature'   => ['label'=>'Temperature', 'unit'=>'°C',   'icon'=>'bi-thermometer', 'min'=>35,  'max'=>42,  'normal'=>[36.1,37.2],'borderline'=>[37.2,38], 'color_ok'=>'#34d399','color_warn'=>'#fbbf24','color_bad'=>'#f87171'],
            'pulse_rate'    => ['label'=>'Heart Rate',  'unit'=>'bpm',  'icon'=>'bi-activity',    'min'=>40,  'max'=>160, 'normal'=>[60,100],   'borderline'=>[50,110],  'color_ok'=>'#34d399','color_warn'=>'#fbbf24','color_bad'=>'#f87171'],
            'spo2'          => ['label'=>'SpO₂',        'unit'=>'%',    'icon'=>'bi-lungs',        'min'=>85,  'max'=>100, 'normal'=>[95,100],   'borderline'=>[90,95],   'color_ok'=>'#34d399','color_warn'=>'#fbbf24','color_bad'=>'#f87171'],
        ];
        $vitalsData = [];
        if ($latestVitals) {
            // Parse BP systolic from "120/80" format
            $bp = $latestVitals->blood_pressure ?? null;
            $bpSys = $bp ? (int) explode('/', $bp)[0] : null;
            $vitalsData['bp_systolic'] = $bpSys;
            $vitalsData['temperature'] = $latestVitals->temperature ? (float) $latestVitals->temperature : null;
            $vitalsData['pulse_rate']  = $latestVitals->pulse_rate  ? (int)   $latestVitals->pulse_rate  : null;
            $vitalsData['spo2']        = $latestVitals->oxygen_saturation ? (float) $latestVitals->oxygen_saturation : null;
        }
        $hasAnyVital = $latestVitals && array_filter($vitalsData, fn($v) => $v !== null);
    @endphp
    @if($hasAnyVital)
    <div class="card mb-4 fade-in delay-1">
        <div class="card-header d-flex align-items-center justify-content-between">
            <span><i class="bi bi-activity me-2" style="color:var(--accent-danger);"></i>Latest Vitals</span>
            <small style="color:var(--text-muted);">Recorded {{ $latestVitals->created_at->diffForHumans() }}</small>
        </div>
        <div class="card-body">
            <div class="row g-3 justify-content-center">
                @foreach($vitalDefs as $key => $def)
                    @php
                        $val = $vitalsData[$key] ?? null;
                        if ($val === null) continue;
                        $pct = min(100, max(0, (($val - $def['min']) / ($def['max'] - $def['min'])) * 100));
                        $arcFill = round($pct);
                        if ($val >= $def['normal'][0] && $val <= $def['normal'][1]) {
                            $ringColor = $def['color_ok'];
                            $status = 'Normal';
                        } elseif ($val >= $def['borderline'][0] && $val <= $def['borderline'][1]) {
                            $ringColor = $def['color_warn'];
                            $status = 'Borderline';
                        } else {
                            $ringColor = $def['color_bad'];
                            $status = 'Abnormal';
                        }
                    @endphp
                    <div class="col-6 col-md-3 text-center">
                        <div class="vital-ring-wrap" style="position:relative; display:inline-block;">
                            <canvas class="vital-ring"
                                    data-value="{{ $val }}"
                                    data-pct="{{ $arcFill }}"
                                    data-color="{{ $ringColor }}"
                                    width="110" height="110"></canvas>
                            <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);text-align:center;line-height:1.2;">
                                <div style="font-size:1.1rem;font-weight:700;color:{{ $ringColor }};">{{ $val }}</div>
                                <div style="font-size:0.62rem;color:var(--text-muted);">{{ $def['unit'] }}</div>
                            </div>
                        </div>
                        <div class="mt-1">
                            <div style="font-size:0.78rem;font-weight:600;color:var(--text-primary);"><i class="bi {{ $def['icon'] }} me-1"></i>{{ $def['label'] }}</div>
                            <span style="font-size:0.68rem;padding:1px 7px;border-radius:999px;background:rgba({{ $ringColor === '#34d399' ? '52,211,153' : ($ringColor === '#fbbf24' ? '251,191,36' : '248,113,113') }},0.18);color:{{ $ringColor }};">{{ $status }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
            {{-- Additional vitals --}}
            @if($latestVitals->weight || $latestVitals->height)
            <div class="d-flex gap-3 justify-content-center mt-3 pt-3" style="border-top:1px solid var(--glass-border);">
                @if($latestVitals->weight)<div class="text-center"><div class="stat-label">Weight</div><div class="fw-semibold">{{ $latestVitals->weight }} kg</div></div>@endif
                @if($latestVitals->height)<div class="text-center"><div class="stat-label">Height</div><div class="fw-semibold">{{ $latestVitals->height }} cm</div></div>@endif
                @if($latestVitals->weight && $latestVitals->height)
                    @php $bmi = round($latestVitals->weight / pow($latestVitals->height / 100, 2), 1); @endphp
                    <div class="text-center"><div class="stat-label">BMI</div><div class="fw-semibold" style="color:{{ $bmi < 18.5 || $bmi > 30 ? 'var(--accent-danger)' : ($bmi > 25 ? 'var(--accent-warning)' : 'var(--accent-success)') }};">{{ $bmi }}</div></div>
                @endif
            </div>
            @endif
        </div>
    </div>

    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.vital-ring').forEach(function(canvas) {
            var pct   = parseInt(canvas.dataset.pct, 10);
            var color = canvas.dataset.color;
            var ctx   = canvas.getContext('2d');
            var cx = 55, cy = 55, r = 44;
            var startAngle = Math.PI * 0.75;  // 135deg — bottom-left
            var fullArc    = Math.PI * 1.5;    // 270deg sweep

            // Track (background arc)
            ctx.beginPath();
            ctx.arc(cx, cy, r, startAngle, startAngle + fullArc);
            ctx.lineWidth = 10;
            ctx.strokeStyle = 'rgba(255,255,255,0.08)';
            ctx.lineCap = 'round';
            ctx.stroke();

            // Animated fill arc
            var current = 0;
            var target  = (pct / 100) * fullArc;
            function draw() {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                // redraw track
                ctx.beginPath();
                ctx.arc(cx, cy, r, startAngle, startAngle + fullArc);
                ctx.lineWidth = 10;
                ctx.strokeStyle = 'rgba(255,255,255,0.08)';
                ctx.lineCap = 'round';
                ctx.stroke();
                // draw fill
                if (current > 0) {
                    var grad = ctx.createLinearGradient(cx - r, cy, cx + r, cy);
                    grad.addColorStop(0, color + 'aa');
                    grad.addColorStop(1, color);
                    ctx.beginPath();
                    ctx.arc(cx, cy, r, startAngle, startAngle + current);
                    ctx.lineWidth = 10;
                    ctx.strokeStyle = grad;
                    ctx.lineCap = 'round';
                    ctx.stroke();
                }
            }
            // Ease-out animation
            var startTime = null;
            var duration  = 900;
            function animate(ts) {
                if (!startTime) startTime = ts;
                var progress = Math.min((ts - startTime) / duration, 1);
                var ease = 1 - Math.pow(1 - progress, 3); // ease-out cubic
                current = ease * target;
                draw();
                if (progress < 1) requestAnimationFrame(animate);
            }
            requestAnimationFrame(animate);
        });
    });
    </script>
    @endpush
    @endif

    {{-- Consultation Notes --}}
    @if($patient->consultation_notes)
    <div class="card mb-4 fade-in delay-2">
        <div class="card-header"><i class="bi bi-journal-medical me-2" style="color:var(--accent-warning);"></i>Doctor's Notes</div>
        <div class="card-body">
            <div class="p-3 rounded" style="background:var(--glass-bg); border:1px solid var(--glass-border);">
                {!! nl2br(e($patient->consultation_notes)) !!}
            </div>
        </div>
    </div>
    @endif

    {{-- Prescriptions --}}
    @if($patient->prescriptions->count() > 0)
    <div class="card mb-4 fade-in delay-2">
        <div class="card-header"><i class="bi bi-prescription2 me-2" style="color:var(--accent-success);"></i>Prescriptions</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Medications</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($patient->prescriptions as $rx)
                        <tr>
                            <td style="color:var(--text-muted);">{{ $rx->created_at->format('M d, Y') }}</td>
                            <td>
                                @foreach($rx->items as $item)
                                    <span class="badge badge-glass-secondary me-1 mb-1" title="{{ $item->dosage }} — {{ $item->frequency }}">
                                        {{ $item->medication_name }}
                                    </span>
                                @endforeach
                            </td>
                            <td>
                                @php
                                    $rxStyle = match($rx->status) {
                                        'dispensed' => 'background:rgba(var(--accent-success-rgb),0.15);color:var(--accent-success);',
                                        'cancelled' => 'background:rgba(var(--accent-danger-rgb),0.15);color:var(--accent-danger);',
                                        default => 'background:rgba(var(--accent-info-rgb),0.15);color:var(--accent-info);',
                                    };
                                @endphp
                                <span class="badge-glass" style="{{ $rxStyle }}">{{ ucfirst($rx->status) }}</span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    {{-- Invoices / Tests / Imaging --}}
    @if($invoices->count() > 0)
    <div class="card mb-4 fade-in delay-3">
        <div class="card-header"><i class="bi bi-file-earmark-medical me-2" style="color:var(--accent-info);"></i>Tests &amp; Imaging</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Department</th>
                            <th>Service</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($invoices->whereIn('department', ['lab', 'radiology']) as $inv)
                        <tr>
                            <td style="color:var(--text-muted);">{{ $inv->created_at->format('M d, Y') }}</td>
                            <td>{{ ucfirst($inv->department) }}</td>
                            <td>{{ $inv->service_name }}</td>
                            <td>
                                @php
                                    $wc = $inv->isPaid() && $inv->isWorkCompleted();
                                    $iStyle = $wc
                                        ? 'background:rgba(var(--accent-success-rgb),0.15);color:var(--accent-success);'
                                        : 'background:rgba(var(--accent-warning-rgb),0.15);color:var(--accent-warning);';
                                @endphp
                                <span class="badge-glass" style="{{ $iStyle }}">{{ $wc ? 'Completed' : 'In Progress' }}</span>
                            </td>
                            <td>
                                @if($inv->report_text || $inv->lab_results || ($inv->radiology_images && count($inv->radiology_images) > 0))
                                    <a href="{{ route('patient.invoice', $inv) }}" class="btn btn-outline-info btn-sm"><i class="bi bi-eye me-1"></i>View</a>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    {{-- AI Analyses --}}
    @if($analyses->count() > 0)
    <div class="card mb-4 fade-in delay-3">
        <div class="card-header"><i class="bi bi-robot me-2" style="color:var(--accent-secondary);"></i>AI Health Insights</div>
        <div class="card-body">
            @foreach($analyses as $analysis)
            <div class="mb-3 p-3 rounded" style="background:var(--glass-bg); border:1px solid var(--glass-border);">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="badge badge-glass-secondary">{{ ucfirst($analysis->context_type) }}</span>
                    <small style="color:var(--text-muted);">{{ $analysis->created_at->format('M d, Y H:i') }}</small>
                </div>
                <div style="color:var(--text-secondary); white-space:pre-line; font-size:0.9rem;">
                    {!! nl2br(e($analysis->ai_response)) !!}
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif
</div>
@endsection
