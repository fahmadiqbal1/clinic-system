@extends('layouts.app')
@section('title', 'Revenue Forecast — ' . config('app.name'))

@section('content')
<div class="container mt-4">
    <div class="page-header mb-4 fade-in">
        <h1><i class="bi bi-graph-up-arrow me-2" style="color:var(--accent-primary);"></i>Revenue Forecast</h1>
        <p class="page-subtitle">12-week historical revenue with 4-week moving average projection</p>
    </div>

    {{-- Stat Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-md-4 fade-in delay-1">
            <div class="glass-card p-3 hover-lift">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon stat-icon-success">
                        <i class="bi bi-cash-stack"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Avg Weekly Revenue</div>
                        <div class="stat-value glow-success">PKR {{ number_format($avgWeekly, 0) }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 fade-in delay-2">
            <div class="glass-card p-3 hover-lift">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon stat-icon-primary">
                        <i class="bi bi-trophy"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Best Week</div>
                        <div class="stat-value glow-primary">PKR {{ number_format($bestWeek, 0) }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 fade-in delay-3">
            <div class="glass-card p-3 hover-lift">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon stat-icon-warning">
                        <i class="bi bi-lightning"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Projected Next Week</div>
                        <div class="stat-value glow-warning">PKR {{ number_format($projectedNext, 0) }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Charts --}}
    <div class="row g-3 mb-4">
        <div class="col-lg-8 fade-in delay-4">
            <div class="glass-card p-3">
                <h6 class="mb-3"><i class="bi bi-graph-up me-2" style="color:var(--accent-primary);"></i>Revenue Trend & Forecast</h6>
                <div class="chart-container">
                    <canvas id="forecastChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-4 fade-in delay-5">
            <div class="glass-card p-3">
                <h6 class="mb-3"><i class="bi bi-pie-chart me-2" style="color:var(--accent-secondary);"></i>Revenue by Department</h6>
                <div class="chart-container">
                    <canvas id="deptChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    {{-- Department Comparison Table --}}
    @if(!empty($deptComparison))
    <div class="glass-card p-4 mb-4 fade-in delay-6">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h6 class="mb-0"><i class="bi bi-table me-2" style="color:var(--accent-secondary);"></i>Department Performance (Last Week vs 4-Wk Avg)</h6>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="deptCompTable">
                <thead>
                    <tr>
                        <th class="sortable-th" data-col="0" style="cursor:pointer; user-select:none;">Department <i class="bi bi-arrow-down-up ms-1" style="font-size:0.7rem; opacity:0.5;"></i></th>
                        <th class="sortable-th text-end" data-col="1" style="cursor:pointer; user-select:none;">4-Wk Avg (PKR) <i class="bi bi-arrow-down-up ms-1" style="font-size:0.7rem; opacity:0.5;"></i></th>
                        <th class="sortable-th text-end" data-col="2" style="cursor:pointer; user-select:none;">Last Week (PKR) <i class="bi bi-arrow-down-up ms-1" style="font-size:0.7rem; opacity:0.5;"></i></th>
                        <th class="sortable-th text-end" data-col="3" style="cursor:pointer; user-select:none;">Change <i class="bi bi-arrow-down-up ms-1" style="font-size:0.7rem; opacity:0.5;"></i></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($deptComparison as $row)
                    <tr>
                        <td class="fw-semibold">
                            <span class="d-inline-flex align-items-center gap-2">
                                <span class="rounded-circle d-inline-block" style="width:8px;height:8px;background:var(--accent-primary);"></span>
                                {{ $row['dept'] }}
                            </span>
                        </td>
                        <td class="text-end" style="color:var(--text-secondary);">{{ number_format($row['four_wk_avg']) }}</td>
                        <td class="text-end fw-semibold">{{ number_format($row['last_week']) }}</td>
                        <td class="text-end">
                            @if($row['change_pct'] > 0)
                                <span class="badge-glass badge-glass-success"><i class="bi bi-arrow-up me-1"></i>+{{ $row['change_pct'] }}%</span>
                            @elseif($row['change_pct'] < 0)
                                <span class="badge-glass badge-glass-danger"><i class="bi bi-arrow-down me-1"></i>{{ $row['change_pct'] }}%</span>
                            @else
                                <span class="badge-glass" style="color:var(--text-muted);">—</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Chart defaults for dark theme
    Chart.defaults.color = 'rgba(255,255,255,0.6)';
    Chart.defaults.borderColor = 'rgba(255,255,255,0.08)';

    // Forecast Line Chart
    var forecastCtx = document.getElementById('forecastChart');
    if (forecastCtx) {
        var weekLabels = @json($weekLabels);
        var forecastLabels = @json($forecastLabels);
        var weekRevenue = @json($weekRevenue);
        var forecast = @json($forecast);

        // Pad historical data with nulls for forecast period
        var historicalData = weekRevenue.concat(Array(forecast.length).fill(null));
        // Pad forecast data with nulls for historical period
        var forecastData = Array(weekRevenue.length).fill(null).concat(forecast);
        var allLabels = weekLabels.concat(forecastLabels);

        new Chart(forecastCtx, {
            type: 'line',
            data: {
                labels: allLabels,
                datasets: [
                    {
                        label: 'Historical',
                        data: historicalData,
                        borderColor: '#818cf8',
                        backgroundColor: 'rgba(129,140,248,0.1)',
                        fill: true,
                        tension: 0.3,
                        pointRadius: 4,
                        pointBackgroundColor: '#818cf8'
                    },
                    {
                        label: 'Forecast',
                        data: forecastData,
                        borderColor: '#fbbf24',
                        backgroundColor: 'rgba(251,191,36,0.1)',
                        fill: true,
                        tension: 0.3,
                        borderDash: [6, 3],
                        pointRadius: 4,
                        pointBackgroundColor: '#fbbf24'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } },
                scales: {
                    y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.05)' } },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    // Department Doughnut Chart
    var deptCtx = document.getElementById('deptChart');
    if (deptCtx) {
        var deptData = @json($deptBreakdown);
        var labels = Object.keys(deptData).map(function(l) { return l.charAt(0).toUpperCase() + l.slice(1); });
        var values = Object.values(deptData);
        var colors = ['#818cf8', '#34d399', '#fbbf24', '#f87171', '#67e8f9', '#22d3ee'];

        new Chart(deptCtx, {
            type: 'doughnut',
            data: {
                labels: labels.length ? labels : ['No data'],
                datasets: [{
                    data: values.length ? values : [1],
                    backgroundColor: values.length ? colors.slice(0, values.length) : ['rgba(255,255,255,0.1)'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { padding: 12 } } },
                cutout: '65%'
            }
        });
    }


    // Sortable department comparison table
    var table = document.getElementById('deptCompTable');
    if (table) {
        var sortDir = {}; // track sort direction per column
        table.querySelectorAll('.sortable-th').forEach(function(th) {
            th.addEventListener('click', function() {
                var col    = parseInt(this.dataset.col, 10);
                var tbody  = table.querySelector('tbody');
                var rows   = Array.from(tbody.querySelectorAll('tr'));
                sortDir[col] = !sortDir[col];
                var asc = sortDir[col];
                rows.sort(function(a, b) {
                    var aVal = a.cells[col].textContent.trim().replace(/[^0-9.\-]/g, '');
                    var bVal = b.cells[col].textContent.trim().replace(/[^0-9.\-]/g, '');
                    var aNum = parseFloat(aVal) || 0;
                    var bNum = parseFloat(bVal) || 0;
                    if (!isNaN(aNum) && !isNaN(bNum)) {
                        return asc ? aNum - bNum : bNum - aNum;
                    }
                    return asc
                        ? a.cells[col].textContent.localeCompare(b.cells[col].textContent)
                        : b.cells[col].textContent.localeCompare(a.cells[col].textContent);
                });
                rows.forEach(function(row) { tbody.appendChild(row); });
                // Update arrow icon
                table.querySelectorAll('.sortable-th').forEach(function(h) {
                    h.querySelector('i').style.opacity = '0.5';
                });
                var icon = this.querySelector('i');
                icon.className = asc ? 'bi bi-arrow-up ms-1' : 'bi bi-arrow-down ms-1';
                icon.style.opacity = '1';
            });
        });
    }
});
</script>
@endpush
