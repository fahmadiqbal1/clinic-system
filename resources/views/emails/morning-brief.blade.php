<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Aviva Morning Brief</title>
<style>
  body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif; background:#f4f6f8; color:#1a1a2e; margin:0; padding:0; }
  .wrapper { max-width:620px; margin:32px auto; background:#fff; border-radius:12px; overflow:hidden; box-shadow:0 2px 16px rgba(0,0,0,.08); }
  .header  { background:linear-gradient(135deg,#1a1a2e 0%,#16213e 100%); color:#fff; padding:28px 32px; }
  .header h1 { margin:0; font-size:22px; font-weight:700; letter-spacing:-.3px; }
  .header p  { margin:6px 0 0; font-size:14px; opacity:.7; }
  .body    { padding:28px 32px; }
  .section { margin-bottom:24px; }
  .section h2 { font-size:13px; font-weight:700; text-transform:uppercase; letter-spacing:.6px; color:#6c757d; margin:0 0 10px; border-bottom:1px solid #e9ecef; padding-bottom:6px; }
  .kpi-row { display:flex; gap:12px; margin-bottom:4px; }
  .kpi { flex:1; background:#f8f9fa; border-radius:8px; padding:14px 16px; }
  .kpi .num  { font-size:24px; font-weight:800; color:#1a1a2e; }
  .kpi .lbl  { font-size:11px; color:#6c757d; text-transform:uppercase; letter-spacing:.5px; margin-top:2px; }
  table { width:100%; border-collapse:collapse; font-size:13px; }
  th { text-align:left; font-size:11px; font-weight:600; color:#6c757d; text-transform:uppercase; letter-spacing:.4px; padding:4px 8px 6px; }
  td { padding:6px 8px; border-top:1px solid #f1f3f4; vertical-align:top; }
  .badge-danger  { background:#dc3545; color:#fff; font-size:10px; padding:2px 7px; border-radius:20px; font-weight:600; }
  .badge-warning { background:#ffc107; color:#000; font-size:10px; padding:2px 7px; border-radius:20px; font-weight:600; }
  .badge-info    { background:#0dcaf0; color:#000; font-size:10px; padding:2px 7px; border-radius:20px; font-weight:600; }
  .all-ok { color:#198754; font-size:13px; }
  .cta { margin-top:24px; text-align:center; }
  .cta a { background:#1a1a2e; color:#fff; text-decoration:none; padding:12px 28px; border-radius:8px; font-weight:600; font-size:14px; display:inline-block; }
  .footer { text-align:center; padding:18px 32px; background:#f8f9fa; font-size:11px; color:#adb5bd; border-top:1px solid #e9ecef; }
</style>
</head>
<body>
<div class="wrapper">

  <div class="header">
    <h1>&#9749; Morning Brief</h1>
    <p>Good morning, {{ $owner->name }} &mdash; {{ $brief['date'] }}</p>
  </div>

  <div class="body">

    {{-- KPI row --}}
    <div class="kpi-row">
      <div class="kpi">
        <div class="num">{{ $brief['revenue_today']['count'] }}</div>
        <div class="lbl">Invoices Today</div>
      </div>
      <div class="kpi">
        <div class="num">PKR {{ $brief['revenue_today']['total'] }}</div>
        <div class="lbl">Revenue Today</div>
      </div>
      <div class="kpi">
        <div class="num" style="color:{{ count($brief['pending_ai_requests']) > 0 ? '#dc3545' : '#198754' }}">{{ count($brief['pending_ai_requests']) }}</div>
        <div class="lbl">Pending AI Requests</div>
      </div>
    </div>

    {{-- Critical stock --}}
    <div class="section">
      <h2>&#9888; Critical Stock (qty = 0)</h2>
      @if($brief['critical_stock']->isEmpty())
        <p class="all-ok">&#10003; No critical stock-outs. All items have stock.</p>
      @else
        <table>
          <tr><th>Item</th><th>Stock</th><th>Min Level</th></tr>
          @foreach($brief['critical_stock'] as $item)
          <tr>
            <td>{{ $item->name }}</td>
            <td><span class="badge-danger">0</span></td>
            <td>{{ $item->minimum_stock_level }}</td>
          </tr>
          @endforeach
        </table>
      @endif
    </div>

    {{-- Warning stock --}}
    @if($brief['warning_stock']->isNotEmpty())
    <div class="section">
      <h2>&#9888; Warning Stock (below minimum)</h2>
      <table>
        <tr><th>Item</th><th>Stock</th><th>Min Level</th></tr>
        @foreach($brief['warning_stock'] as $item)
        <tr>
          <td>{{ $item->name }}</td>
          <td><span class="badge-warning">{{ $item->quantity_in_stock }}</span></td>
          <td>{{ $item->minimum_stock_level }}</td>
        </tr>
        @endforeach
      </table>
    </div>
    @endif

    {{-- Pending procurement --}}
    <div class="section">
      <h2>&#128230; Pending Procurement Approvals</h2>
      @if($brief['pending_procurement']->isEmpty())
        <p class="all-ok">&#10003; No pending procurement requests.</p>
      @else
        <table>
          <tr><th>Department</th><th>Created</th><th>Notes</th></tr>
          @foreach($brief['pending_procurement'] as $pr)
          <tr>
            <td>{{ ucfirst($pr->department) }}</td>
            <td style="white-space:nowrap">{{ \Carbon\Carbon::parse($pr->created_at)->format('d M H:i') }}</td>
            <td style="color:#6c757d">{{ Str::limit($pr->notes ?? '', 60) }}</td>
          </tr>
          @endforeach
        </table>
      @endif
    </div>

    {{-- Pending AI action requests --}}
    @if($brief['pending_ai_requests']->isNotEmpty())
    <div class="section">
      <h2>&#129302; Pending AI Action Requests</h2>
      <table>
        <tr><th>Source</th><th>Action</th><th>Created</th></tr>
        @foreach($brief['pending_ai_requests'] as $req)
        <tr>
          <td><span class="badge-info">{{ $req->requested_by_source }}</span></td>
          <td>{{ $req->proposed_action }}</td>
          <td style="white-space:nowrap">{{ \Carbon\Carbon::parse($req->created_at)->format('d M H:i') }}</td>
        </tr>
        @endforeach
      </table>
    </div>
    @endif

    {{-- Overnight anomalies --}}
    @if($brief['overnight_anomalies']->isNotEmpty())
    <div class="section">
      <h2>&#128680; Overnight Anomalies (Admin AI)</h2>
      <table>
        <tr><th>Action</th><th>Detected</th></tr>
        @foreach($brief['overnight_anomalies'] as $anom)
        <tr>
          <td>{{ $anom->proposed_action }}</td>
          <td style="white-space:nowrap">{{ \Carbon\Carbon::parse($anom->created_at)->format('d M H:i') }}</td>
        </tr>
        @endforeach
      </table>
    </div>
    @endif

    <div class="cta">
      <a href="{{ config('app.url') }}/owner/dashboard">Open Dashboard</a>
    </div>

  </div>

  <div class="footer">
    Aviva HealthCare &mdash; {{ config('app.name') }}<br>
    This is an automated brief. Do not reply to this email.
  </div>

</div>
</body>
</html>
