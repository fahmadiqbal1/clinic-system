<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #{{ $invoice->id }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
        }
        .container {
            padding: 20px;
            max-width: 800px;
            margin: 0 auto;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #2563eb;
            padding-bottom: 20px;
        }
        .clinic-name {
            font-size: 24px;
            font-weight: bold;
            color: #1e40af;
        }
        .clinic-tagline {
            color: #6b7280;
            font-size: 11px;
        }
        .invoice-title {
            font-size: 18px;
            margin-top: 10px;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        .invoice-meta {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        .invoice-meta-box {
            width: 48%;
        }
        .meta-label {
            font-weight: bold;
            color: #4b5563;
            font-size: 10px;
            text-transform: uppercase;
        }
        .meta-value {
            margin-top: 5px;
            font-size: 11px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th {
            background-color: #2563eb;
            color: white;
            padding: 10px;
            text-align: left;
            font-size: 11px;
        }
        td {
            padding: 10px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 11px;
        }
        .text-right {
            text-align: right;
        }
        .totals {
            float: right;
            width: 250px;
        }
        .totals-row {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        .grand-total {
            font-weight: bold;
            font-size: 14px;
            background-color: #f0f9ff;
            padding: 10px;
            margin-top: 5px;
        }
        .footer {
            clear: both;
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            font-size: 10px;
            color: #6b7280;
        }
        .status-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-paid {
            background-color: #d1fae5;
            color: #065f46;
        }
        .status-pending {
            background-color: #fef3c7;
            color: #92400e;
        }
        .qr-section {
            text-align: center;
            margin-top: 30px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="clinic-name">Aviva HealthCare</div>
            <div class="clinic-tagline">Excellence in Medical Care</div>
            <div class="invoice-title">Tax Invoice</div>
        </div>

        <div class="invoice-meta">
            <div class="invoice-meta-box">
                <div class="meta-label">Invoice To</div>
                <div class="meta-value">
                    <strong>{{ $invoice->patient->full_name }}</strong><br>
                    @if($invoice->patient->phone)
                    Phone: {{ $invoice->patient->phone }}<br>
                    @endif
                    @if($invoice->patient->email)
                    Email: {{ $invoice->patient->email }}
                    @endif
                </div>
            </div>
            <div class="invoice-meta-box" style="text-align: right;">
                <div class="meta-label">Invoice Details</div>
                <div class="meta-value">
                    <strong>Invoice #:</strong> {{ $invoice->id }}<br>
                    <strong>Date:</strong> {{ $invoice->created_at->format('d M Y') }}<br>
                    <strong>Department:</strong> {{ ucfirst($invoice->department) }}<br>
                    <strong>Status:</strong> 
                    <span class="status-badge {{ $invoice->status === 'paid' ? 'status-paid' : 'status-pending' }}">
                        {{ ucfirst($invoice->status) }}
                    </span>
                </div>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width: 50%;">Description</th>
                    <th class="text-right">Qty</th>
                    <th class="text-right">Unit Price</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoice->items as $item)
                <tr>
                    <td>{{ $item->description ?? $item->serviceCatalog?->name ?? 'Service' }}</td>
                    <td class="text-right">{{ $item->quantity }}</td>
                    <td class="text-right">Rs. {{ number_format($item->unit_price, 2) }}</td>
                    <td class="text-right">Rs. {{ number_format($item->line_total, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="totals">
            <div class="totals-row">
                <span>Subtotal:</span>
                <span>Rs. {{ number_format($invoice->total_amount, 2) }}</span>
            </div>
            @if($invoice->discount_amount > 0)
            <div class="totals-row">
                <span>Discount:</span>
                <span>- Rs. {{ number_format($invoice->discount_amount, 2) }}</span>
            </div>
            @endif
            <div class="totals-row grand-total">
                <span>Grand Total:</span>
                <span>Rs. {{ number_format($invoice->net_amount, 2) }}</span>
            </div>
        </div>

        @if($invoice->prescribingDoctor)
        <div style="clear: both; margin-top: 30px;">
            <div class="meta-label">Prescribing Doctor</div>
            <div class="meta-value">Dr. {{ $invoice->prescribingDoctor->name }}</div>
        </div>
        @endif

        @if($qrCode)
        <div class="qr-section">
            <div class="meta-label">FBR Verification Code</div>
            <div style="font-family: monospace; font-size: 8px; word-break: break-all;">
                {{ $qrCode }}
            </div>
        </div>
        @endif

        <div class="footer">
            <p>This is a computer-generated invoice.</p>
            <p>Thank you for choosing Aviva HealthCare.</p>
            <p>For queries, contact: info@avivahealthcare.pk</p>
        </div>
    </div>
</body>
</html>
