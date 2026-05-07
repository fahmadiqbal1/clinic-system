@extends('layouts.app')
@section('title', 'Appointments — ' . config('app.name'))

@push('styles')
<style>
/* FullCalendar overrides to match Glass Prism design */
.fc { font-family: var(--font-sans, 'Inter', sans-serif); }
.fc-toolbar-title { font-size: 1.1rem; font-weight: 600; }
.fc-button { background: rgba(255,255,255,0.08) !important; border: 1px solid rgba(255,255,255,0.15) !important; color: var(--text-primary, #e2e8f0) !important; border-radius: 6px !important; font-size: 0.8rem !important; }
.fc-button:hover, .fc-button-active { background: rgba(99,102,241,0.3) !important; border-color: var(--accent-primary, #6366f1) !important; }
.fc-button-primary:not(:disabled).fc-button-active { background: var(--accent-primary, #6366f1) !important; border-color: var(--accent-primary, #6366f1) !important; }
.fc-daygrid-day, .fc-timegrid-slot { background: transparent !important; }
.fc-col-header-cell { background: rgba(255,255,255,0.04) !important; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.05em; }
.fc-event { border-radius: 4px !important; font-size: 0.78rem !important; border: none !important; padding: 2px 4px !important; cursor: pointer !important; }
.pre-booked-event { border: 2px dashed rgba(255,255,255,0.5) !important; opacity: 0.85; }
.fc-timegrid-now-indicator-line { border-color: var(--accent-danger, #ef4444) !important; }
.fc-scrollgrid { border-color: rgba(255,255,255,0.1) !important; }
.fc-scrollgrid td, .fc-scrollgrid th { border-color: rgba(255,255,255,0.08) !important; }
.fc-day-today { background: rgba(99,102,241,0.06) !important; }
.fc-list-event:hover td { background: rgba(255,255,255,0.04) !important; }
.fc-list-day-cushion { background: rgba(255,255,255,0.05) !important; }
/* Nav tabs custom */
.view-tab { cursor: pointer; padding: 6px 18px; border-radius: 6px; font-size: 0.85rem; font-weight: 500; border: 1px solid rgba(255,255,255,0.15); background: rgba(255,255,255,0.05); color: var(--text-secondary, #94a3b8); transition: all .2s; }
.view-tab.active { background: var(--accent-primary, #6366f1); border-color: var(--accent-primary, #6366f1); color: #fff; }
</style>
@endpush

@section('content')
<div class="container mt-4">

    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-3 fade-in">
        <div>
            <h1 class="page-header mb-1"><i class="bi bi-calendar-check me-2" style="color:var(--accent-primary);"></i>Appointments</h1>
            <p class="page-subtitle mb-0">Manage patient appointment scheduling</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            {{-- View toggle --}}
            <div class="d-flex gap-1">
                <button class="view-tab active" id="tabCalendar" onclick="switchView('calendar')"><i class="bi bi-calendar3 me-1"></i>Calendar</button>
                <button class="view-tab" id="tabList" onclick="switchView('list')"><i class="bi bi-list-ul me-1"></i>List</button>
            </div>
            <a href="{{ route('receptionist.appointments.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle me-1"></i>New Appointment</a>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    {{-- CALENDAR VIEW                                                         --}}
    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    <div id="viewCalendar" class="glass-card fade-in" style="padding: 1rem;">
        <div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
            <span class="small text-muted me-2">Legend:</span>
            <span class="badge" style="background:#3b82f6;">Scheduled</span>
            <span class="badge" style="background:#22c55e;">Confirmed</span>
            <span class="badge" style="background:#f59e0b;">In Progress</span>
            <span class="badge" style="background:#ef4444;">Cancelled</span>
            <span class="badge" style="background:#6b7280;">No-Show</span>
            <span class="ms-2 small text-muted"><i class="bi bi-dash me-1"></i>Dashed border = pre-booked (not yet registered)</span>
        </div>
        <div id="clinicCalendar" style="min-height: 620px;"></div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    {{-- LIST VIEW                                                             --}}
    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    <div id="viewList" class="fade-in" style="display:none;">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <div class="me-auto d-flex flex-wrap gap-1">
                <a href="{{ route('receptionist.appointments.index', ['filter' => 'upcoming']) }}" class="btn btn-sm {{ ($filter ?? 'upcoming') === 'upcoming' ? 'btn-primary' : 'btn-outline-primary' }}">Upcoming</a>
                <a href="{{ route('receptionist.appointments.index', ['filter' => 'today']) }}" class="btn btn-sm {{ ($filter ?? '') === 'today' ? 'btn-info' : 'btn-outline-info' }}">Today</a>
                <a href="{{ route('receptionist.appointments.index', ['filter' => 'all']) }}" class="btn btn-sm {{ ($filter ?? '') === 'all' ? 'btn-secondary' : 'btn-outline-secondary' }}">All</a>
                <a href="{{ route('receptionist.appointments.index', ['filter' => 'cancelled']) }}" class="btn btn-sm {{ ($filter ?? '') === 'cancelled' ? 'btn-danger' : 'btn-outline-danger' }}">Cancelled</a>
            </div>
        </div>

        @if($appointments->isEmpty())
            <div class="glass-card">
                <div class="text-center py-5">
                    <i class="bi bi-calendar" style="font-size:3rem; color:var(--text-muted);"></i>
                    <p class="mt-3" style="color:var(--text-muted);">No appointments found.</p>
                </div>
            </div>
        @else
            <div class="glass-card">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Patient</th>
                                <th>Doctor</th>
                                <th>Room</th>
                                <th>Date/Time</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($appointments as $appointment)
                                <tr>
                                    <td style="color:var(--text-muted);">{{ $appointment->id }}</td>
                                    <td class="fw-medium">
                                        @if($appointment->patient)
                                            {{ $appointment->patient->first_name }} {{ $appointment->patient->last_name }}
                                        @else
                                            <span class="text-warning"><i class="bi bi-person-dash me-1"></i>{{ $appointment->pre_booked_name ?? 'Walk-in' }}</span>
                                        @endif
                                    </td>
                                    <td>Dr. {{ $appointment->doctor->name ?? 'N/A' }}</td>
                                    <td>{{ $appointment->room?->name ?? '—' }}</td>
                                    <td>{{ $appointment->scheduled_at->format('d/m/Y H:i') }}</td>
                                    <td>{{ $appointment->type_label }}</td>
                                    <td>
                                        @php
                                            $badge = match($appointment->status) {
                                                'scheduled'   => 'badge-glass-primary',
                                                'confirmed'   => 'badge-glass-success',
                                                'cancelled'   => 'badge-glass-danger',
                                                'completed'   => 'badge-glass-success',
                                                'in_progress' => 'badge-glass-warning',
                                                'no_show'     => 'badge-glass-secondary',
                                                default       => 'badge-glass-secondary',
                                            };
                                        @endphp
                                        <span class="badge {{ $badge }}">{{ $appointment->status_label }}</span>
                                    </td>
                                    <td>
                                        <a href="{{ route('receptionist.appointments.show', $appointment) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye me-1"></i>View</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="mt-3">{{ $appointments->appends(request()->query())->links() }}</div>
        @endif
    </div>
</div>

{{-- ════════════════════════════════════════════════════════════════════════ --}}
{{-- Event Detail Modal                                                       --}}
{{-- ════════════════════════════════════════════════════════════════════════ --}}
<div class="modal fade" id="apptModal" tabindex="-1" aria-labelledby="apptModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: var(--glass-bg, rgba(30,35,60,0.92)); border: 1px solid rgba(255,255,255,0.12); backdrop-filter: blur(20px);">
            <div class="modal-header border-0">
                <h5 class="modal-title" id="apptModalLabel"><i class="bi bi-calendar-event me-2" style="color:var(--accent-primary);"></i><span id="modalPatient">—</span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="modalPreBookedBanner" class="alert alert-warning py-2 mb-3" style="display:none;">
                    <i class="bi bi-person-dash me-1"></i> Pre-booked — patient not yet registered. <a href="{{ route('receptionist.pre-booked.index') }}" class="alert-link">Register now →</a>
                </div>
                <table class="table table-sm mb-0" style="color:var(--text-primary);">
                    <tbody>
                        <tr><th style="width:30%;">Doctor</th><td id="modalDoctor">—</td></tr>
                        <tr><th>Room</th><td id="modalRoom">—</td></tr>
                        <tr><th>Date / Time</th><td id="modalTime">—</td></tr>
                        <tr><th>Type</th><td id="modalType">—</td></tr>
                        <tr><th>Status</th><td><span id="modalStatus" class="badge">—</span></td></tr>
                        <tr><th>Source</th><td id="modalSource">—</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                <a href="#" id="modalDetailLink" class="btn btn-primary btn-sm"><i class="bi bi-arrow-right me-1"></i>View Full Details</a>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
{{-- FullCalendar 6 — bundled CDN (no npm required) --}}
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>
<script>
const EVENTS  = @json($calendarEvents);
let calInstance = null;

// ── View toggle ─────────────────────────────────────────────────────────────
function switchView(v) {
    document.getElementById('viewCalendar').style.display = v === 'calendar' ? '' : 'none';
    document.getElementById('viewList').style.display     = v === 'list'     ? '' : 'none';
    document.getElementById('tabCalendar').classList.toggle('active', v === 'calendar');
    document.getElementById('tabList').classList.toggle('active',     v === 'list');

    // Initialise calendar lazily on first open (avoids render-before-visible issue)
    if (v === 'calendar' && !calInstance) {
        initCalendar();
    }
    if (calInstance) calInstance.updateSize();
}

// ── FullCalendar init ────────────────────────────────────────────────────────
function initCalendar() {
    const el = document.getElementById('clinicCalendar');
    calInstance = new FullCalendar.Calendar(el, {
        initialView: 'timeGridWeek',
        headerToolbar: {
            left:   'prev,next today',
            center: 'title',
            right:  'dayGridMonth,timeGridWeek,timeGridDay,listWeek',
        },
        height:        'auto',
        slotMinTime:   '07:00:00',
        slotMaxTime:   '22:00:00',
        nowIndicator:  true,
        businessHours: { daysOfWeek: [0,1,2,3,4,5,6], startTime: '09:00', endTime: '17:00' },
        events:        EVENTS,
        eventTimeFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
        eventClick:    showModal,
        eventClassNames: function(info) {
            return info.event.extendedProps.preBooked ? ['pre-booked-event'] : [];
        },
    });
    calInstance.render();
}

// ── Event click → modal ──────────────────────────────────────────────────────
function showModal(info) {
    const p = info.event.extendedProps;
    const start = info.event.start;
    const end   = info.event.end;

    document.getElementById('modalPatient').textContent = info.event.title;
    document.getElementById('modalDoctor').textContent  = p.doctor || '—';
    document.getElementById('modalRoom').textContent    = p.room   || '—';
    document.getElementById('modalType').textContent    = p.type   || '—';

    // Format time range
    const fmt = d => d ? d.toLocaleString('en-PK', {
        dateStyle: 'medium', timeStyle: 'short', hour12: true
    }) : '';
    document.getElementById('modalTime').textContent = fmt(start) + (end ? ' – ' + end.toLocaleTimeString('en-PK', {hour:'2-digit', minute:'2-digit', hour12:true}) : '');

    // Status badge
    const statusEl = document.getElementById('modalStatus');
    statusEl.textContent = p.status || '—';
    const colorMap = { Confirmed:'success', Scheduled:'primary', 'In Progress':'warning', Cancelled:'danger', 'No Show':'secondary' };
    statusEl.className = 'badge bg-' + (colorMap[p.status] || 'secondary');

    // Source
    const src = p.source || '';
    document.getElementById('modalSource').innerHTML = src === 'omnidimension'
        ? '<span class="badge bg-info text-dark"><i class="bi bi-robot me-1"></i>OmniDimension AI</span>'
        : (src === 'phone' ? '<i class="bi bi-telephone me-1"></i>Phone' : (src.charAt(0).toUpperCase() + src.slice(1)) || '—');

    // Pre-booked banner
    document.getElementById('modalPreBookedBanner').style.display = p.preBooked ? '' : 'none';

    document.getElementById('modalDetailLink').href = p.detailUrl;

    new bootstrap.Modal(document.getElementById('apptModal')).show();
}

// ── Auto-init calendar on page load ─────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
    // Small delay so glass-card is fully painted before FullCalendar measures
    setTimeout(initCalendar, 80);
});
</script>
@endpush
